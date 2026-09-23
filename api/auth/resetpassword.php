<?php
// ========================================
// RESET PASSWORD HANDLER - Maveren Capital
// ========================================

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../backend/email.php';
require_once __DIR__ . '/../utilities/security.php';   // mvcHashPassword()

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// These four never opened a session - they authenticate with an emailed OTP,
// not a cookie. CSRF verification is session-bound though (the token lives in
// $_SESSION), and the visitor already holds a session from the page that
// rendered the form, so the session is resumed here purely to read it back.
mvcSessionStart();
mvcCsrfEnforce();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Keyed by EMAIL, not by a client-supplied user_id. forgotpassword.php used to
// return the user_id in its JSON response, so an unauthenticated caller was
// handed half of a two-part credential and only had to guess a 6-digit OTP -
// with no attempt limit, a keyspace of 10^6 falls in minutes.
$input = json_decode(file_get_contents('php://input'), true);
$email = trim((string)($input['email'] ?? ''));
$otp = trim($input['otp'] ?? '');
$new_password = trim($input['new_password'] ?? '');
$verify_only = !empty($input['verify_only']);

// How many wrong codes before the OTP is burned and the member has to request
// a fresh one.
const MAX_OTP_ATTEMPTS = 5;

if ($email === '' || $otp === '') {
    echo json_encode(['status' => 'error', 'message' => 'Email and OTP are required']);
    exit;
}

try {
    // ✅ Establish database connection
    $pdo = getPDO();

    // Throttle before doing any work. Scope 'reset' is independent of
    // the login buckets, so abuse here cannot lock anyone out of signing in.
    mvcEnforceRateLimit($pdo, 'reset', $email);
    mvcRecordAttempt($pdo, 'reset', mvcClientIp());

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user_id = (int) ($stmt->fetchColumn() ?: 0);

    // Same message for an unknown address and a wrong code, so this endpoint
    // cannot be used to enumerate accounts either.
    if (!$user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    // --- Load the pending reset for this account (not keyed on the OTP, so a
    //     wrong guess can still be counted) ---
    $stmt = $pdo->prepare(
        "SELECT *, (expires_at < NOW()) AS is_expired
           FROM password_resets
          WHERE user_id = :uid
       ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute(['uid' => $user_id]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    if ((int) $reset['otp_attempts'] >= MAX_OTP_ATTEMPTS) {
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = :uid")->execute(['uid' => $user_id]);
        echo json_encode(['status' => 'error', 'message' => 'Too many incorrect codes. Please request a new one.']);
        exit;
    }

    // Judged by MySQL, which is also what stamped expires_at.
    if ((int) $reset['is_expired'] === 1) {
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = :uid")->execute(['uid' => $user_id]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    // Constant-time compare, and count the miss.
    if (!hash_equals((string) $reset['otp'], $otp)) {
        $pdo->prepare("UPDATE password_resets SET otp_attempts = otp_attempts + 1 WHERE id = :id")
            ->execute(['id' => $reset['id']]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    // --- OTP Verification Only ---
    if ($verify_only) {
        echo json_encode(['status' => 'success', 'message' => 'OTP verified']);
        exit;
    }

    // --- Validate password ---
    if (strlen($new_password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long']);
        exit;
    }

    // --- Update password ---
    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :uid");
    $stmt->execute([
        'password' => mvcHashPassword($new_password),
        'uid' => $user_id
    ]);

    // --- Retrieve user email for notification ---
    $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Clean up OTP ---
    $pdo->prepare("DELETE FROM password_resets WHERE user_id = :uid")->execute(['uid' => $user_id]);

    // --- Send Confirmation Email ---
    if (!empty($user['email'])) {
        sendEmail([
            'to' => $user['email'],
            'template' => 'password_reset_success',
            'variables' => [
                'user_name'  => $user['full_name'] ?? 'User',
                // The template renders "Account: {{user_email}}". Omitting it
                // delivered the literal placeholder to the member.
                'user_email' => $user['email'],
            ],
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Password reset successful',
        'data' => ['redirect' => '/login']
    ]);

} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Something went wrong, please try again later.']);
}
?>
