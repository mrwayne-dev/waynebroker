<?php
// ========================================
// USER LOGIN - Maveren Capital
// ========================================

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../utilities/helpers.php';
require_once __DIR__ . '/../backend/email.php';
require_once __DIR__ . '/../../api/utilities/security.php';   // hashing, throttling, sessions

// Hardened + proxy-aware. The inline options this replaced tested
// $_SERVER['HTTPS'] === 'on', which is unset behind a TLS-terminating proxy,
// and did not set use_strict_mode.
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();

    // --- Get input data ---
    $input = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim($input['email'] ?? ''));
    $password = trim($input['password'] ?? '');

    if (!$email || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
        exit;
    }

    // --- Brute-force throttle ---
    //
    // Two independent buckets: per-IP (one attacker spraying many accounts)
    // and per-email (many IPs targeting one account). The 'login' scope is
    // separate from 'admin_login', so failed member logins from an office IP
    // no longer lock that office out of the admin panel.
    $ip = mvcClientIp();
    if (mvcRateLimited($pdo, 'login', $ip) || mvcRateLimited($pdo, 'login', $email)) {
        logSecurityEvent('login_lockout', ['scope' => 'user', 'ip' => $ip, 'email' => $email, 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Please wait ~15 minutes and try again.']);
        exit;
    }

    /** Record a failure against both buckets. */
    $recordFailure = function () use ($pdo, $ip, $email) {
        mvcRecordAttempt($pdo, 'login', $ip);
        mvcRecordAttempt($pdo, 'login', $email);
    };

    // --- Retrieve user ---
    $stmt = $pdo->prepare("SELECT id, name, full_name, email, password, status, role, profile_picture, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $recordFailure();
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
        exit;
    }

    // --- Verify password FIRST ---
    //
    // The disabled-account check used to run before this, which let an
    // unauthenticated caller learn that an address exists and is disabled
    // without ever supplying a password - a free account-enumeration oracle.
    // Nothing about the account is disclosed until the password is proven.
    if (!password_verify($password, $user['password'])) {
        $recordFailure();
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
        exit;
    }

    // --- Now that the password is proven, check the account is usable ---
    if (isset($user['status']) && strtolower($user['status']) !== 'active') {
        $recordFailure();
        echo json_encode(['status' => 'error', 'message' => 'Your account has been disabled. Contact support.']);
        exit;
    }

    // --- Opportunistic hash upgrade ---
    //
    // This is the whole bcrypt -> argon2id migration path. No mass reset, no
    // forced change: the one moment the plaintext exists is right here, so
    // the hash is rewritten in place. Accounts that never sign in again keep
    // their bcrypt hash, which still verifies.
    mvcUpgradePasswordHash($pdo, 'users', (int) $user['id'], $password, $user['password']);

    // --- Block unverified accounts (existing accounts are backfilled to verified) ---
    if (isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please verify your email to continue. We can send you a new code.',
            'data' => ['requires_verification' => true, 'user_id' => (int)$user['id']]
        ]);
        exit;
    }

    // --- Setup session (regenerate ID first to prevent session fixation) ---
    mvcSessionElevate();
    $displayName = $user['full_name'] ?: ($user['name'] ?? 'User');
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $displayName;
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['profile_picture'] = $user['profile_picture'] ?: '/assets/images/avatar/default.png';

    // --- Prepare login details ---
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $browser = getUserBrowser($_SERVER['HTTP_USER_AGENT'] ?? '');
    $location = getLocationFromIP($ip);
    $loginTime = date('Y-m-d H:i:s');

    // --- Update user last_login ---
    //
    // Was guarded by a SHOW COLUMNS probe because the column was never in the
    // schema, so this write silently never ran and the admin users table showed
    // a blank Last Login for everyone. The column is added in
    // dbschema/migrations/2026_07_31_auth_hardening.sql; the probe was also an
    // extra round trip on every single login.
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    // --- Send Login Alert Email to the User ---
    sendEmail([
        'to' => $user['email'],
        'template' => 'login_alert',
        'variables' => [
            'user_name' => $displayName,
            'login_time' => $loginTime,
            'ip' => $ip,
            'browser' => $browser,
            'location' => $location,
        ],
    ]);

    // --- Notify Admin of User Login ---
    sendEmail([
        'to' => ADMIN_CONTACT_EMAIL,
        'template' => 'admin_user_login_notification',
        'variables' => [
            'user_name' => $displayName,
            'user_email' => $user['email'],
            'login_time' => $loginTime,
            'ip' => $ip,
            'browser' => $browser,
            'location' => $location,
        ],
    ]);

    logLoginEvent($pdo, 'user', $user['id'], $ip, $browser, $location);

    // --- Respond to frontend ---
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful!',
        'data' => ['redirect' => '/dashboard']
    ]);

    exit;

} catch (Exception $e) {
    error_log('User login error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again later.']);
    exit;
}
?>
