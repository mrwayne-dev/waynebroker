<?php
// ========================================
// EMAIL VERIFICATION - Maveren Capital
// Verifies the OTP issued at registration (or resends it),
// then opens the logged-in session on success.
// ========================================

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../backend/email.php';
require_once __DIR__ . '/../../api/utilities/security.php';   // OTP throttling + sessions

// Hardened + proxy-aware - see the note in login.php.
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Wrong codes before the OTP is burned and a fresh one must be requested.
const MAX_OTP_ATTEMPTS = 5;

// Gap enforced between two verification codes going to the same account.
const RESEND_COOLDOWN_SECONDS = 90;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id  = intval($input['user_id'] ?? 0);
$otp      = trim($input['otp'] ?? '');
$resend   = !empty($input['resend']);

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid verification request.']);
    exit;
}

try {
    $pdo = getPDO();

    // --- Fetch the account ---
    $stmt = $pdo->prepare("SELECT id, name, full_name, email, role, profile_picture, email_verified FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
        exit;
    }

    $displayName = $user['full_name'] ?: ($user['name'] ?? 'User');

    // Already verified - nothing to do.
    if ((int)$user['email_verified'] === 1) {
        echo json_encode(['status' => 'error', 'message' => 'This account is already verified. Please sign in.']);
        exit;
    }

    // ============================================================
    // RESEND: regenerate + re-send a fresh OTP (90s cooldown)
    // ============================================================
    if ($resend) {
        // Cooldown measured entirely on the DB clock. This used to be
        // `time() - strtotime($row['created_at'])`, comparing a PHP timestamp
        // against a column stamped by MySQL's CURRENT_TIMESTAMP. The two run
        // in different zones, and where MySQL was ahead that subtraction went
        // NEGATIVE - always below 90 - so this branch rejected EVERY resend
        // and quoted a wait of several hours. Registration mailed one code and
        // there was no way on the platform to obtain a second.
        $wait = mvcOtpCooldownRemaining($pdo, 'email_verifications', $user_id, RESEND_COOLDOWN_SECONDS);
        if ($wait > 0) {
            echo json_encode(['status' => 'error', 'message' => "Please wait {$wait}s before requesting another code."]);
            exit;
        }

        $newOtp = random_int(100000, 999999);
        $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare(
            "INSERT INTO email_verifications (user_id, otp, expires_at)
             VALUES (?, ?, " . mvcOtpExpirySql() . ")"
        )->execute([$user_id, $newOtp]);

        // Report a send failure instead of claiming success - the member is
        // watching an inbox and needs to know to try again.
        $sent = sendEmail([
            'to' => $user['email'],
            'template' => 'email_verification',
            'variables' => ['user_name' => $displayName, 'otp' => $newOtp],
        ]);

        if (!mvcEmailSent($sent)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'We could not send the code just now. Please try again in a minute.',
            ]);
            exit;
        }

        echo json_encode(['status' => 'success', 'message' => 'A new code has been sent to your email.']);
        exit;
    }

    // ============================================================
    // VERIFY: validate the OTP
    // ============================================================
    if ($otp === '') {
        echo json_encode(['status' => 'error', 'message' => 'Please enter the 6-digit code.']);
        exit;
    }

    // Throttle the endpoint itself, then count wrong guesses against the code.
    //
    // This was the sharpest hole in the system: a 6-digit code with NO attempt
    // counter, where success opens a fully privileged session. 10^6 is minutes
    // of work unattended. resetpassword.php already had this cap; verification
    // did not.
    mvcEnforceRateLimit($pdo, 'otp');
    mvcRecordAttempt($pdo, 'otp', mvcClientIp());

    // Loaded by user_id ALONE, not user_id+otp, so a wrong guess is still
    // found and can be counted.
    //
    // is_expired is computed by MySQL so the deadline is judged by the clock
    // that wrote it, rather than by strtotime() against PHP's time().
    $vstmt = $pdo->prepare(
        "SELECT *, (expires_at < NOW()) AS is_expired
           FROM email_verifications
          WHERE user_id = ?
       ORDER BY id DESC
          LIMIT 1"
    );
    $vstmt->execute([$user_id]);
    $verify = $vstmt->fetch(PDO::FETCH_ASSOC);

    if (!$verify) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code.']);
        exit;
    }

    if ((int) $verify['otp_attempts'] >= MAX_OTP_ATTEMPTS) {
        $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user_id]);
        echo json_encode(['status' => 'error', 'message' => 'Too many incorrect codes. Please request a new one.']);
        exit;
    }

    if ((int) $verify['is_expired'] === 1) {
        $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user_id]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code.']);
        exit;
    }

    // Constant-time compare, and count the miss.
    if (!hash_equals((string) $verify['otp'], $otp)) {
        $pdo->prepare("UPDATE email_verifications SET otp_attempts = otp_attempts + 1 WHERE id = ?")
            ->execute([$verify['id']]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code.']);
        exit;
    }

    // --- Mark verified + clean up ---
    $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user_id]);

    // --- Open the logged-in session (same fields as login.php) ---
    //
    // Regenerate first: this request goes from anonymous to fully
    // authenticated, and it was previously reusing whatever session id the
    // caller arrived with.
    mvcSessionElevate();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $displayName;
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['profile_picture'] = $user['profile_picture'] ?: '/assets/images/avatar/default.png';

    // --- Send Welcome Email (now that the email is confirmed) ---
    sendEmail([
        'to' => $user['email'],
        'template' => 'welcome_user',
        'variables' => ['user_name' => $displayName],
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Email verified! Redirecting...',
        'data' => ['redirect' => '/dashboard']
    ]);
    exit;

} catch (Exception $e) {
    error_log('Email verification error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again later.']);
    exit;
}
?>
