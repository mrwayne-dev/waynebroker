<?php
// ============================================================
// FILE: /scripts/seed_test_accounts.php
// PURPOSE: Create (or reset) the two accounts used to click through
//          the user and admin dashboards locally, plus enough wallet,
//          investment and transaction data that the dashboard pages
//          render real figures instead of empty states.
//
// USAGE:  php scripts/seed_test_accounts.php
//         php scripts/seed_test_accounts.php --password='SomethingElse'
//
// Safe to re-run: every write is an upsert keyed on email or on a
// deterministic transaction reference, so a second run resets the
// passwords and refreshes the demo data rather than duplicating it.
//
// NOT a production script. It writes a known password and marks the
// user's email as already verified.
// ============================================================

// CLI only. This mints credentials, so it must never be web-triggerable
// even from localhost.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Access Denied: CLI only\n");
}

require_once __DIR__ . '/../config/env.php';

// CLI-only keeps this off the web, but not off a production shell - and it
// writes a known password against a real email. Refuse outright unless the
// environment says this is a development box.
//
// config/env.php normalises .env's APP_ENV into the APP_ENV *constant*, which
// is only ever 'local' or 'production' - reading $_ENV directly would miss it,
// because nothing ever puts it there.
if (!defined('APP_ENV') || APP_ENV !== 'local') {
    exit("Refusing to run: APP_ENV is not local. This script mints known credentials.\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utilities/security.php';   // mvcHashPassword()

// ------------------------------------------------------------
// Config
// ------------------------------------------------------------
// .test addresses, deliberately undeliverable: these accounts exist only to
// click through the dashboards locally, and a real inbox here would receive
// the verification and payout mail this script's data triggers.
const SEED_USER_EMAIL  = 'member@maverencapital.test';
const SEED_ADMIN_EMAIL = 'admin@maverencapital.test';

$password = 'MvcTest!2026';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--password=')) {
        $password = substr($arg, strlen('--password='));
    }
}
if (strlen($password) < 8) {
    exit("Password must be at least 8 characters (the register endpoint enforces this).\n");
}

// mvcHashPassword(): argon2id under the single policy defined in
// api/utilities/security.php, the same helper every endpoint uses, so a
// seeded account verifies exactly like a registered one.
$hash = mvcHashPassword($password);

$out = fn(string $s) => fwrite(STDOUT, $s . "\n");

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    exit('Could not connect to the database: ' . $e->getMessage() . "\n");
}

$pdo->beginTransaction();

try {
    // --------------------------------------------------------
    // 1. User account
    // --------------------------------------------------------
    // email_verified = 1 is REQUIRED: api/auth/login.php blocks sign-in for
    // unverified accounts, and no OTP email can be delivered locally.
    $pdo->prepare(
        "INSERT INTO users (name, full_name, email, password, email_verified, role, status, phone, country)
         VALUES (?, ?, ?, ?, 1, 'user', 'active', ?, ?)
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            full_name = VALUES(full_name),
            password = VALUES(password),
            email_verified = 1,
            role = 'user',
            status = 'active'"
    )->execute(['Test Member', 'Test Member', SEED_USER_EMAIL, $hash, '+44 7700 900123', 'United Kingdom']);

    $user_id = (int) $pdo->query(
        'SELECT id FROM users WHERE email = ' . $pdo->quote(SEED_USER_EMAIL)
    )->fetchColumn();

    if (!$user_id) {
        throw new RuntimeException('User row was not created.');
    }

    // --------------------------------------------------------
    // 2. Admin account
    // --------------------------------------------------------
    // profile_picture is set explicitly: the schema default points at
    // admin_default.png, which does not exist under /assets/images/avatar.
    $pdo->prepare(
        "INSERT INTO admins (name, full_name, email, password, role, status, profile_picture)
         VALUES (?, ?, ?, ?, 'super_admin', 'active', '/assets/images/avatar/default.png')
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            full_name = VALUES(full_name),
            password = VALUES(password),
            role = 'super_admin',
            status = 'active',
            profile_picture = VALUES(profile_picture)"
    )->execute(['Test Admin', 'Test Admin', SEED_ADMIN_EMAIL, $hash]);

    $admin_id = (int) $pdo->query(
        'SELECT id FROM admins WHERE email = ' . $pdo->quote(SEED_ADMIN_EMAIL)
    )->fetchColumn();

    // --------------------------------------------------------
    // 3. Demo investments (one weekly, one monthly)
    // --------------------------------------------------------
    // Cleared and re-inserted so re-running does not stack duplicates.
    $pdo->prepare('DELETE FROM investments WHERE user_id = ?')->execute([$user_id]);

    $plans = $pdo->query(
        'SELECT id, title, cadence, roi_percent, duration_days FROM plans ORDER BY id'
    )->fetchAll();

    $byCadence = [];
    foreach ($plans as $p) {
        $byCadence[$p['cadence']][] = $p;
    }

    $insertInvestment = $pdo->prepare(
        'INSERT INTO investments (user_id, plan_id, plan_name, cadence, amount, roi_percent,
                                  duration_days, payouts_total, payouts_made, next_payout_date,
                                  maturity_date, roi_earned, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $seeded = [];
    // Wallet totals and the transaction ledger are DERIVED from the positions
    // below, so the dashboard's own sums (it recomputes earnings from
    // investments.roi_earned) agree with wallets.total_earnings.
    $deposited     = 12000.00;
    $totalInvested = 0.00;
    $totalEarnings = 0.00;
    $txns = [
        ['deposit', 'secure_exchange', $deposited, 'SEED-DEP-0001', '-70 days'],
    ];

    // Weekly plan, part-way through its term.
    if (!empty($byCadence['weekly'][1])) {
        $p = $byCadence['weekly'][1];
        $amount = 5000.00;
        $perPayout = round($amount * ((float) $p['roi_percent'] / 100), 2);
        $payoutsTotal = (int) floor($p['duration_days'] / 7);
        $payoutsMade = 6;
        $startedDaysAgo = $payoutsMade * 7 + 2;
        $earned = round($perPayout * $payoutsMade, 2);

        $insertInvestment->execute([
            $user_id, $p['id'], $p['title'], 'weekly', $amount, $p['roi_percent'],
            $p['duration_days'], $payoutsTotal, $payoutsMade,
            date('Y-m-d', strtotime('+5 days')),
            date('Y-m-d', strtotime("-$startedDaysAgo days +{$p['duration_days']} days")),
            $earned, 'active',
            date('Y-m-d H:i:s', strtotime("-$startedDaysAgo days")),
        ]);

        $totalInvested += $amount;
        $totalEarnings += $earned;
        $txns[] = ['investment', 'wallet', $amount, 'SEED-INV-0001', "-$startedDaysAgo days"];

        // One ledger row per payout actually made, so the transactions page
        // shows the same schedule the position claims.
        for ($i = 1; $i <= $payoutsMade; $i++) {
            $daysAgo = $startedDaysAgo - ($i * 7);
            $txns[] = ['roi_payout', 'system', $perPayout,
                       sprintf('SEED-ROI-W%04d', $i), "-$daysAgo days"];
        }

        $seeded[] = sprintf('%s (weekly) $%s, %d/%d payouts made, $%s earned',
            $p['title'], number_format($amount, 2), $payoutsMade, $payoutsTotal,
            number_format($earned, 2));
    }

    // Monthly plan, freshly opened, nothing paid out yet.
    if (!empty($byCadence['monthly'][0])) {
        $p = $byCadence['monthly'][0];
        $amount = 3500.00;
        $payoutsTotal = (int) round($p['duration_days'] / 30);

        $insertInvestment->execute([
            $user_id, $p['id'], $p['title'], 'monthly', $amount, $p['roi_percent'],
            $p['duration_days'], $payoutsTotal, 0,
            date('Y-m-d', strtotime('+18 days')),
            date('Y-m-d', strtotime("-12 days +{$p['duration_days']} days")),
            0.00, 'active',
            date('Y-m-d H:i:s', strtotime('-12 days')),
        ]);

        $totalInvested += $amount;
        $txns[] = ['investment', 'wallet', $amount, 'SEED-INV-0002', '-12 days'];

        $seeded[] = sprintf('%s (monthly) $%s, 0/%d payouts made',
            $p['title'], number_format($amount, 2), $payoutsTotal);
    }

    // --------------------------------------------------------
    // 4. Wallet
    // --------------------------------------------------------
    // api/auth/register.php creates this row on sign-up; the wallet and
    // dashboard pages assume it exists.
    $walletFigures = [
        'balance'             => round($deposited - $totalInvested + $totalEarnings, 2),
        'total_deposited'     => $deposited,
        'total_withdrawn'     => 0.00,
        'total_investments'   => $totalInvested,
        'total_earnings'      => $totalEarnings,
        'pending_withdrawals' => 0.00,
    ];

    $wallet_id = (int) ($pdo->query(
        "SELECT id FROM wallets WHERE user_id = $user_id"
    )->fetchColumn() ?: 0);

    if ($wallet_id) {
        $pdo->prepare(
            'UPDATE wallets SET balance = ?, total_deposited = ?, total_withdrawn = ?,
                    total_investments = ?, total_earnings = ?, pending_withdrawals = ?
             WHERE id = ?'
        )->execute([...array_values($walletFigures), $wallet_id]);
    } else {
        $pdo->prepare(
            'INSERT INTO wallets (user_id, balance, total_deposited, total_withdrawn,
                                  total_investments, total_earnings, pending_withdrawals)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$user_id, ...array_values($walletFigures)]);
    }

    // --------------------------------------------------------
    // 5. Demo transactions
    // --------------------------------------------------------
    // References are deterministic (SEED-*) so the upsert is idempotent; the
    // app's own references come from generateReference() and cannot collide.
    // Stale seed rows are cleared first in case the schedule above changed.
    $pdo->prepare("DELETE FROM transactions WHERE user_id = ? AND reference LIKE 'SEED-%'")
        ->execute([$user_id]);

    $insertTxn = $pdo->prepare(
        "INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
         VALUES (?, ?, ?, ?, ?, 'completed', ?, ?)
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            type = VALUES(type),
            method = VALUES(method),
            amount = VALUES(amount),
            details = VALUES(details),
            created_at = VALUES(created_at)"
    );

    foreach ($txns as [$type, $method, $amount, $reference, $when]) {
        $insertTxn->execute([
            $user_id, $type, $method, $amount, $reference,
            json_encode(['seeded' => true, 'note' => 'local test data']),
            date('Y-m-d H:i:s', strtotime($when)),
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('Seed failed, nothing was written: ' . $e->getMessage() . "\n");
}

// ------------------------------------------------------------
// Report
// ------------------------------------------------------------
$out('');
$out('Seeded test accounts');
$out('--------------------');
$out(sprintf('  user   #%-4d %s', $user_id, SEED_USER_EMAIL));
$out(sprintf('  admin  #%-4d %s  (super_admin)', $admin_id, SEED_ADMIN_EMAIL));
$out(sprintf('  password for both: %s', $password));
$out('');
$out('Sign in at  /login        for the user dashboard  (/dashboard)');
$out('            /admin.login  for the admin dashboard (/admin)');
$out('');
if ($seeded) {
    $out('Demo investments:');
    foreach ($seeded as $line) {
        $out('  - ' . $line);
    }
    $out('');
}
$out(sprintf('Wallet: balance $%s, deposited $%s, invested $%s, earned $%s',
    number_format($walletFigures['balance'], 2),
    number_format($walletFigures['total_deposited'], 2),
    number_format($walletFigures['total_investments'], 2),
    number_format($walletFigures['total_earnings'], 2)));
$out('');
