<?php
// ========================================
// ADMIN LOGIN - Maveren Capital (Finalized)
// ========================================

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../backend/email.php';
require_once __DIR__ . '/../utilities/helpers.php';
require_once __DIR__ . '/../../api/utilities/security.php';   // hashing, throttling, sessions

// Hardened + proxy-aware - see the note in login.php.
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();

    // --- Parse JSON input ---
    $input = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim($input['email'] ?? ''));
    $password = trim($input['password'] ?? '');

    if (!$email || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
        exit;
    }

    // --- Brute-force throttle ---
    //
    // 'admin_login' is its own scope. Previously admin and member login shared
    // one IP-keyed bucket, so ten failed MEMBER logins from an office locked
    // the admin panel out from that same office.
    $ip = mvcClientIp();
    if (mvcRateLimited($pdo, 'admin_login', $ip) || mvcRateLimited($pdo, 'admin_login', $email)) {
        logSecurityEvent('login_lockout', ['scope' => 'admin', 'ip' => $ip, 'email' => $email, 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Please wait ~15 minutes and try again.']);
        exit;
    }

    // --- Retrieve admin ---
    $stmt = $pdo->prepare("SELECT id, name, full_name, email, password, status FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($password, $admin['password'])) {
        mvcRecordAttempt($pdo, 'admin_login', $ip);
        mvcRecordAttempt($pdo, 'admin_login', $email);
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
        exit;
    }

    // --- Check account status (AFTER the password is proven) ---
    if (strtolower($admin['status']) !== 'active') {
        mvcRecordAttempt($pdo, 'admin_login', $ip);
        echo json_encode(['status' => 'error', 'message' => 'Your admin account is disabled.']);
        exit;
    }

    // --- Opportunistic bcrypt -> argon2id upgrade; see login.php ---
    mvcUpgradePasswordHash($pdo, 'admins', (int) $admin['id'], $password, $admin['password']);

    // --- Update last login timestamp ---
    $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

    // --- Prepare session (regenerate ID first to prevent session fixation) ---
    mvcSessionElevate();
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name'] = $admin['full_name'] ?: ($admin['name'] ?? 'Administrator');
    $_SESSION['admin_logged_in'] = true;

    // --- Log + Email Alert ---
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $browser = getUserBrowser($_SERVER['HTTP_USER_AGENT'] ?? '');
    $location = getLocationFromIP($ip);
    $loginTime = date('Y-m-d H:i:s');

    sendEmail([
        'to' => $admin['email'],
        'template' => 'admin_login_alert',
        'variables' => [
            'admin_name' => $_SESSION['admin_name'],
            'login_time' => $loginTime,
            'ip' => $ip,
            'browser' => $browser,
            'location' => $location,
        ],
    ]);

    logLoginEvent($pdo, 'admin', $admin['id'], $ip, $browser, $location);

    // --- Final response ---
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful!',
        'data' => ['redirect' => '/admin']
    ]);
    exit;

} catch (Exception $e) {
    error_log('Admin login error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again later.']);
    exit;
}
?>
