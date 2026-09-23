<?php
// ========================================
// ADMIN REGISTRATION - Maveren Capital
// ========================================

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../backend/email.php';
require_once __DIR__ . '/../utilities/security.php';   // mvcHashPassword()

mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();

    // Throttle before doing any work. Scope 'register' is independent of
    // the login buckets, so abuse here cannot lock anyone out of signing in.
    mvcEnforceRateLimit($pdo, 'register');
    mvcRecordAttempt($pdo, 'register', mvcClientIp());
    $input = json_decode(file_get_contents('php://input'), true);

    $username    = trim($input['username'] ?? '');
    $email       = strtolower(trim($input['email'] ?? ''));
    $password    = trim($input['password'] ?? '');
    $invite_code = trim($input['invite_code'] ?? '');

    if (!$username || !$email || !$password || !$invite_code) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }

    // --- Verify admin invite code (constant-time; empty server code never matches) ---
    if (ADMIN_INVITE_CODE === '' || !hash_equals(ADMIN_INVITE_CODE, $invite_code)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid admin invite code.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long.']);
        exit;
    }

    // One email = one role: reject if used by an admin OR a user
    $check = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered.']);
        exit;
    }
    $userCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $userCheck->execute([$email]);
    if ($userCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'This email cannot be used for an admin account.']);
        exit;
    }

    $hashed = mvcHashPassword($password);
    $name = $username;

    // Role assigned at registration.
    //
    // Was hardcoded 'manager', which made a fresh install unusable: the two
    // MVC_ROLE_OWNER screens (deposit addresses, wallet balances) were locked
    // to everybody, no screen in the console edits admins.role, and the only
    // way out was scripts/set_admin_role.php over SSH.
    //
    // Registration is gated on ADMIN_INVITE_CODE, so anyone who reaches this
    // line already holds the shared secret out of .env - the invite code, not
    // the role column, is what decides who may become an admin at all.
    //
    // Consequence worth stating plainly: everyone with that code now gets full
    // control of the deposit addresses members send funds to. Rotate
    // ADMIN_INVITE_CODE in .env if it is ever shared beyond the operators, and
    // use scripts/set_admin_role.php to drop an account to manager or support.
    $role = 'super_admin';

    // Insert into admins table
    $stmt = $pdo->prepare("
        INSERT INTO admins (name, full_name, email, password, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([$name, $name, $email, $hashed, $role]);
    $admin_id = $pdo->lastInsertId();

    // Set session
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_name'] = $name;
    // Display only - every gate re-reads the role from the database on each
    // request (mvcAdminRole), so a stale session copy can never grant access.
    $_SESSION['admin_role'] = $role;
    $_SESSION['admin_logged_in'] = true;

    // Send welcome email
    sendEmail([
        'to' => $email,
        'template' => 'welcome_admin',
        // welcome_admin renders {{admin_email}} and {{admin_role}}; both were
        // omitted, so every new admin received two literal placeholders.
        'variables' => [
            'admin_name'  => $name,
            'admin_email' => $email,
            'admin_role'  => $role,
        ],
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Admin registration successful!',
        'data' => ['redirect' => '/admin']
    ]);
    exit;

} catch (Exception $e) {
    error_log('Admin registration error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again later.']);
    exit;
}
?>
