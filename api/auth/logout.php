<?php
// ========================================
// USER LOGOUT - Maveren Capital
// ========================================

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../backend/email.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../api/utilities/security.php';
    // Hardened + proxy-aware: use_strict_mode, and a cookie_secure that
    // survives a TLS-terminating proxy (the inline options this replaced
    // tested $_SERVER['HTTPS'] === 'on', which is unset behind one).
    mvcSessionStart();

    // CSRF. Safe methods return immediately; anything else must present the
    // session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
    mvcCsrfEnforce();
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// --- Only allow POST requests ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    // --- Ensure user is logged in ---
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'No active session found.']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['email'] ?? 'no-reply@maverencapital.com';
    $full_name = $_SESSION['full_name'] ?? 'User';

    // --- Log logout event ---
    error_log("Logout event: User #$user_id ($email) at " . date('Y-m-d H:i:s'));

    // --- Send Logout Notification ---
    try {
        sendEmail([
            'to' => $email,
            'template' => 'logout_notification',
           'variables' => [
            'user_name'   => $full_name,
            'logout_time' => date('d M Y, h:i:s A T')
        ],

        ]);
        error_log("Logout email sent successfully to $email");
    } catch (Throwable $e) {
        error_log("Logout email failed: " . $e->getMessage());
    }

    // --- Destroy session ---
    session_unset();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();

    echo json_encode([
        'status' => 'success',
        'message' => 'Logout successful.',
        'data' => ['redirect' => '/login']
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Logout error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error occurred. Please try again.']);
}
?>
