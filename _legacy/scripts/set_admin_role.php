<?php
// ============================================================
// FILE: scripts/set_admin_role.php
// PURPOSE: Set an existing admin's role.
//
// api/auth/admin_register.php hardcodes role='manager' for every account it
// creates - there is no way to register a super_admin through the UI, and no
// screen in the console that edits admins.role. So the first super_admin on a
// fresh install has to be promoted out of band, and this is that step.
//
// Roles (see mvcRequireAdminRole in api/utilities/security.php):
//   super_admin  everything, including deposit addresses and wallet balances
//   manager      approve deposits/withdrawals, plans, users, announcements
//   support      read-only
//
// USAGE
//   php scripts/set_admin_role.php --email=<addr> --role=<role>
//   php scripts/set_admin_role.php --list
//
// CLI only, and it refuses to leave the platform with no super_admin.
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Access Denied: CLI only\n");
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

const VALID_ROLES = ['super_admin', 'manager', 'support'];

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$show = static function (PDO $pdo): void {
    echo "Admins:\n";
    $rows = $pdo->query("SELECT id, email, full_name, name, role, status, last_login
                         FROM admins ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { echo "  (none)\n"; return; }
    foreach ($rows as $r) {
        printf("  #%-3d %-40s %-12s %-9s %s\n",
            $r['id'], $r['email'], $r['role'], $r['status'],
            $r['full_name'] ?: $r['name']);
    }
};

if (!empty($args['list']) || empty($args['email']) || empty($args['role'])) {
    $show($pdo);
    if (empty($args['list'])) {
        echo "\nUsage: php scripts/set_admin_role.php --email=<addr> --role=<"
             . implode('|', VALID_ROLES) . ">\n";
    }
    exit(0);
}

$email = trim((string) $args['email']);
$role  = trim((string) $args['role']);

if (!in_array($role, VALID_ROLES, true)) {
    exit("Unknown role '{$role}'. Valid: " . implode(', ', VALID_ROLES) . "\n");
}

$stmt = $pdo->prepare("SELECT id, email, role, status FROM admins WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo "No admin with that email.\n\n";
    $show($pdo);
    exit(1);
}

if ($admin['role'] === $role) {
    echo "#{$admin['id']} {$admin['email']} is already {$role}. Nothing to do.\n";
    exit(0);
}

// Refuse to remove the last super_admin. Demoting the only one locks the
// deposit addresses and wallet-balance screens for everybody, and the only way
// back is this script - which is exactly the situation worth preventing.
if ($admin['role'] === 'super_admin' && $role !== 'super_admin') {
    $others = (int) $pdo->query(
        "SELECT COUNT(*) FROM admins WHERE role = 'super_admin' AND status = 'active'"
    )->fetchColumn();
    if ($others <= 1) {
        exit("Refusing: #{$admin['id']} is the only active super_admin.\n"
           . "Promote another account first.\n");
    }
}

$upd = $pdo->prepare("UPDATE admins SET role = ? WHERE id = ?");
$upd->execute([$role, $admin['id']]);

printf("#%d %s: %s -> %s (%d row)\n",
    $admin['id'], $admin['email'], $admin['role'], $role, $upd->rowCount());

echo "\n";
$show($pdo);
