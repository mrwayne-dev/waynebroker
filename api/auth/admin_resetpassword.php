<?php
// ========================================
// ADMIN RESET PASSWORD - Maveren Capital
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

// Keyed by EMAIL with an attempt cap - see the note in resetpassword.php.
$input = json_decode(file_get_contents('php://input'), true);
$email = trim((string)($input['email'] ?? ''));
$otp = trim($input['otp'] ?? '');
$new_password = trim($input['new_password'] ?? '');
$verify_only = !empty($input['verify_only']);

const MAX_OTP_ATTEMPTS = 5;

if ($email === '' || $otp === '') {
    echo json_encode(['status' => 'error', 'message' => 'Email and OTP are required']);
    exit;
}

try {
    $pdo = getPDO();

    // Throttle before doing any work. Scope 'reset' is independent of
    // the login buckets, so abuse here cannot lock anyone out of signing in.
    mvcEnforceRateLimit($pdo, 'reset', $email);
    mvcRecordAttempt($pdo, 'reset', mvcClientIp());

    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $admin_id = (int) ($stmt->fetchColumn() ?: 0);

    if (!$admin_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT *, (expires_at < NOW()) AS is_expired
           FROM admin_password_resets
          WHERE admin_id = :aid
       ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute(['aid' => $admin_id]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    if ((int) $reset['otp_attempts'] >= MAX_OTP_ATTEMPTS) {
        $pdo->prepare("DELETE FROM admin_password_resets WHERE admin_id = :aid")->execute(['aid' => $admin_id]);
        echo json_encode(['status' => 'error', 'message' => 'Too many incorrect codes. Please request a new one.']);
        exit;
    }

    // Judged by MySQL, which is also what stamped expires_at.
    if ((int) $reset['is_expired'] === 1) {
        $pdo->prepare("DELETE FROM admin_password_resets WHERE admin_id = :aid")->execute(['aid' => $admin_id]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    if (!hash_equals((string) $reset['otp'], $otp)) {
        $pdo->prepare("UPDATE admin_password_resets SET otp_attempts = otp_attempts + 1 WHERE id = :id")
            ->execute(['id' => $reset['id']]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }

    // --- Verification only ---
    if ($verify_only) {
        echo json_encode(['status' => 'success', 'message' => 'OTP verified']);
        exit;
    }

    // --- Validate new password ---
    if (strlen($new_password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long']);
        exit;
    }

    // --- Update admin password ---
    $stmt = $pdo->prepare("UPDATE admins SET password = :password WHERE id = :aid");
    $stmt->execute([
        'password' => mvcHashPassword($new_password),
        'aid' => $admin_id
    ]);

    // --- Clean up OTP ---
    $pdo->prepare("DELETE FROM admin_password_resets WHERE admin_id = :aid")->execute(['aid' => $admin_id]);

    // --- Retrieve email for confirmation ---
    $stmt = $pdo->prepare("SELECT email, full_name FROM admins WHERE id = :aid");
    $stmt->execute(['aid' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Send confirmation email ---
    if (!empty($admin['email'])) {
        sendEmail([
            'to' => $admin['email'],
            'template' => 'password_reset_success',
            'variables' => [
                'user_name'  => $admin['full_name'] ?? 'Administrator',
                'user_email' => $admin['email'],   // see resetpassword.php
            ],
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Password reset successfully!',
        // Was '/admin/login', which matches no rewrite rule - .htaccess uses
        // dot notation for admin pages, so that 404'd after a successful reset.
        'data' => ['redirect' => '/admin.login']
    ]);

} catch (Exception $e) {
    error_log("Admin Reset Password Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Something went wrong, please try again later.']);
}
?>
