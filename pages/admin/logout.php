<?php
// ============================================================
// FILE: /pages/admin/logout.php
// Signs an administrator out and returns them to the ADMIN login.
//
// Previously both dashboards shared pages/user/logout.php, which only
// reads $_SESSION['user_id']. An admin session carries admin_id, so the
// lookup found nothing, no notification was sent, and the redirect
// dropped the admin on the member login page.
// ============================================================
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();

require_once __DIR__ . '/../../api/utilities/logout.php';

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$actor   = mvcResolveActor('admins', $adminId, $_SESSION['admin_name'] ?? 'Administrator');

// The session already carries the admin email; fall back to it if the row
// lookup came up empty (e.g. the account was removed mid-session).
if (!$actor['email'] && !empty($_SESSION['admin_email'])) {
    $actor['email'] = $_SESSION['admin_email'];
}

mvcPerformLogout(
    $actor['email'],
    $actor['name'],
    'admin_logout_notification',
    '/admin.login'
);
