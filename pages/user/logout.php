<?php
// ============================================================
// FILE: /pages/user/logout.php
// Signs a member out and returns them to the member login.
// The sequence itself lives in api/utilities/logout.php, shared
// with the admin equivalent.
// ============================================================
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();

require_once __DIR__ . '/../../api/utilities/logout.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$actor  = mvcResolveActor('users', $userId, $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'User');

mvcPerformLogout(
    $actor['email'],
    $actor['name'],
    'logout_notification',
    '/login'
);
