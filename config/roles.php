<?php

// Role definitions and permissions
// Usage: Use in auth checks, e.g., if ($_SESSION['role'] === ROLE_SUPER_ADMIN) { ... }

if (!defined('ROLE_USER')) define('ROLE_USER', 'user');
if (!defined('ROLE_SUPPORT_ADMIN')) define('ROLE_SUPPORT_ADMIN', 'support_admin');
if (!defined('ROLE_SUPER_ADMIN')) define('ROLE_SUPER_ADMIN', 'super_admin');

// Simple permission mapping (expand as needed)
$permissions = [
    ROLE_USER => [
        'view_dashboard' => true,
        'view_transactions' => true,
        'enroll_lymoralearn' => true,
        'contact_admin' => true,
    ],
    ROLE_SUPPORT_ADMIN => [
        'view_admin_dashboard' => true,
        'manage_orders' => true, // Assigned orders only
        'complete_orders' => true,
    ],
    ROLE_SUPER_ADMIN => [
        'view_admin_dashboard' => true,
        'manage_users' => true,
        'manage_transactions' => true,
        'manage_all_orders' => true,
        'assign_orders' => true,
        'manage_admins' => true,
    ],
];

// Function to check permission
function hasPermission($role, $permission) {
    global $permissions;
    return isset($permissions[$role][$permission]) && $permissions[$role][$permission];
}

?>