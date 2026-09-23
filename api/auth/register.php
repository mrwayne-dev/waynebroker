<?php
// ========================================
// USER REGISTRATION - Maveren Capital
// ========================================

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../backend/email.php';
require_once __DIR__ . '/../utilities/security.php';   // mvcHashPassword()

mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    // ✅ Establish PDO connection
    $pdo = getPDO();

    // Throttle before doing any work. Scope 'register' is independent of
    // the login buckets, so abuse here cannot lock anyone out of signing in.
    mvcEnforceRateLimit($pdo, 'register');
    mvcRecordAttempt($pdo, 'register', mvcClientIp());

    $input = json_decode(file_get_contents('php://input'), true);
    $first_name = trim($input['first_name'] ?? '');
    $last_name  = trim($input['last_name'] ?? '');
    $email      = strtolower(trim($input['email'] ?? ''));
    $password   = trim($input['password'] ?? '');

    // Optional profile fields captured at sign-up so the member does not have
    // to re-enter them on the profile page. Excluded from the required check
    // below on purpose - leaving them blank must be allowed.
    //
    // Address is NOT collected here: it stays optional and profile-only.
    $country    = trim($input['country'] ?? '');
    $location   = trim($input['location'] ?? '');

    // --- Validate input (the four REQUIRED fields only) ---
    if (!$first_name || !$last_name || !$email || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }

    // Bound the optional fields to their column widths so an oversized paste
    // is rejected with a readable message rather than truncated by MySQL.
    if (mb_strlen($country) > 80) {
        echo json_encode(['status' => 'error', 'message' => 'That country name is too long.']);
        exit;
    }
    if (mb_strlen($location) > 255) {
        echo json_encode(['status' => 'error', 'message' => 'That location is too long.']);
        exit;
    }

    // --- Baseline password policy ---
    if (strlen($password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long.']);
        exit;
    }

    // --- One email = one role: reject if used by a user OR an admin ---
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered.']);
        exit;
    }
    $adminCheck = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $adminCheck->execute([$email]);
    if ($adminCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'This email cannot be used for a user account.']);
        exit;
    }

    // --- Hash password ---
    $hashed = mvcHashPassword($password);
    $full_name = "{$first_name} {$last_name}";

    // --- Insert new user (unverified - no session until email is confirmed) ---
    //
    // first_name / last_name are stored as discrete columns as well as being
    // joined into full_name. They used to be concatenated and discarded, so
    // the profile page could never show them separately.
    //
    // country / location go in as NULL when blank, never as ''. The profile
    // API normalises '' to NULL on write, and get_profile turns NULL back into
    // '' for the form - so a field the member skipped renders empty instead of
    // carrying a value they never typed.
    $stmt = $pdo->prepare("
        INSERT INTO users (name, first_name, last_name, full_name, email, password,
                           country, location, email_verified, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([
        $full_name,
        $first_name,
        $last_name,
        $full_name,
        $email,
        $hashed,
        $country !== '' ? $country : null,
        $location !== '' ? $location : null,
    ]);
    $user_id = $pdo->lastInsertId();

    // --- Create wallet entry ---
    $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);

    // --- Generate + store email verification OTP ---
    //
    // expires_at is stamped by MySQL, not by PHP. The two clocks run in
    // different zones, and this row's sibling created_at defaults to
    // CURRENT_TIMESTAMP - so a PHP-formatted deadline landed hours away from
    // the timestamp it is supposed to sit ten minutes after, and every later
    // comparison against it was off by the same amount.
    $otp = random_int(100000, 999999);
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare(
        "INSERT INTO email_verifications (user_id, otp, expires_at)
         VALUES (?, ?, " . mvcOtpExpirySql() . ")"
    )->execute([$user_id, $otp]);

    // --- Send Verification Email (welcome email is sent after verification) ---
    $sent = sendEmail([
        'to' => $email,
        'template' => 'email_verification',
        'variables' => [
            'user_name' => $full_name,
            'otp' => $otp,
        ],
    ]);

    // The account and its OTP exist either way, so a failed send is not a
    // failed registration - the member just needs the truth and the resend
    // link. Keep requires_verification set so the UI still advances to the
    // OTP step rather than dead-ending on the sign-up form.
    if (!mvcEmailSent($sent)) {
        echo json_encode([
            'status'  => 'success',
            'message' => 'Account created, but we could not send your code. Use "Resend code" in a moment.',
            'data'    => ['requires_verification' => true, 'user_id' => $user_id]
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'We sent a 6-digit verification code to your email.',
        'data' => ['requires_verification' => true, 'user_id' => $user_id]
    ]);

} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
}
?>
