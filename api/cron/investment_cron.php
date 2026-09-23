<?php
// ============================================================
// FILE: /api/cron/investment_cron.php
// PURPOSE: Credits scheduled ROI payouts and releases principal
//          at maturity for Maveren Capital investments.
//
// MODEL: each investment carries a cadence (weekly | monthly), an
// roi_percent PER PERIOD, and a next_payout_date. Every run:
//   1. pays every period that is due (catching up if the cron was
//      down for several periods),
//   2. releases the principal once the maturity date has passed.
//
// SCHEDULE: run daily. Running it more than once a day is safe -
// nothing is due until next_payout_date arrives.
//   0 2 * * *  php /path/to/api/cron/investment_cron.php
// ============================================================

// Restrict execution to CLI or localhost - prevents unauthenticated web
// triggering of financial batch processing (payouts).
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit("Access Denied\n");
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../backend/email.php';

// NO date_default_timezone_set() here.
//
// This used to force UTC, which overrode the date_default_timezone_set(TIMEZONE)
// that constants.php performs one line above and left the cron running on a
// different clock from the rest of the platform. getPDO() sets the MySQL session
// zone from PHP's, so the whole cron connection went to UTC while every web
// request runs America/New_York:
//
//     CRON   PHP=14:06:23  offset=+00:00  MySQL NOW()=14:06:23
//     WEB    PHP=10:06:23  offset=-04:00  MySQL NOW()=10:06:23
//
// The consequence that was actually occurring: the payout and principal rows
// this file inserts carried a created_at four hours ahead of every row the app
// writes into the same transactions table, so a member's own history was
// internally inconsistent depending on which process produced each line.
//
// There is a second, LATENT hazard that was not firing. The two queries deciding
// what is due compare against MySQL CURDATE() (:76 and :173) while the rest of
// the file uses PHP date(); under UTC both sat ahead of the platform's date, so
// between 20:00 and midnight the cron's "today" would have been the platform's
// "tomorrow" and a payout could have been credited a day early. It never was:
// hostingserver2 is itself America/New_York (verified - /etc/localtime, and
// `date` reports EDT -0400), so the 01:00 crontab entry fired at 01:00 app-time,
// which is 05:00 UTC and the same calendar date. 01:00 is outside the dangerous
// window. Worth knowing before anyone reschedules this into the evening.
//
// The platform has ONE clock and it is TIMEZONE. Inheriting it is what keeps
// CURDATE(), date(), and every timestamp the app writes in agreement.

$logFile = __DIR__ . '/../../logs/investment_cron.log';
function cronLog(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    if (php_sapi_name() === 'cli') echo $line;
}

// --- Cadence helpers: must stay identical to api/backend/invest.php ---
function cadenceDays(string $cadence): int {
    return $cadence === 'monthly' ? 30 : 7;
}
function nextPayoutDate(string $cadence, string $from): string {
    return $cadence === 'monthly'
        ? date('Y-m-d', strtotime($from . ' +1 month'))
        : date('Y-m-d', strtotime($from . ' +7 days'));
}
function generateReference(string $prefix): string {
    return strtoupper($prefix . '-' . uniqid() . '-' . rand(1000, 9999));
}

cronLog('Cron started');

try {
    $pdo = getPDO();
} catch (Exception $e) {
    cronLog('DB connection failed: ' . $e->getMessage());
    exit("DB connection failed.\n");
}

$today = date('Y-m-d');
$paidPeriods = 0;
$releasedCount = 0;
$errorCount = 0;

// ------------------------------------------------------------
// STEP 1 - pay every period that is due
// ------------------------------------------------------------
$stmt = $pdo->query("
    SELECT inv.id, inv.user_id, inv.plan_name, inv.cadence, inv.amount, inv.roi_percent,
           inv.payouts_total, inv.payouts_made, inv.next_payout_date, inv.maturity_date,
           inv.roi_earned, u.email, u.full_name AS user_name
    FROM investments inv
    JOIN users u ON u.id = inv.user_id
    WHERE inv.status = 'active' AND inv.next_payout_date <= CURDATE()
");
$due = $stmt->fetchAll(PDO::FETCH_ASSOC);

cronLog('Investments with a payout due: ' . count($due));

foreach ($due as $inv) {
    $inv_id     = (int) $inv['id'];
    $user_id    = (int) $inv['user_id'];
    $amount     = (float) $inv['amount'];
    $roi_pct    = (float) $inv['roi_percent'];
    $cadence    = $inv['cadence'];
    $total      = (int) $inv['payouts_total'];
    $made       = (int) $inv['payouts_made'];
    $nextDate   = $inv['next_payout_date'];
    $per_payout = round($amount * $roi_pct / 100, 2);

    // Catch-up: if the cron missed runs, pay every period that has since
    // come due - but never more than the plan's total payout count.
    $periodsDue = 0;
    $cursor     = $nextDate;
    while ($cursor <= $today && ($made + $periodsDue) < $total) {
        $periodsDue++;
        $cursor = nextPayoutDate($cadence, $cursor);
    }

    if ($periodsDue < 1) {
        // Already fully paid; STEP 2 releases the principal at maturity.
        continue;
    }

    $payout = round($per_payout * $periodsDue, 2);

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE investments
                       SET payouts_made     = payouts_made + ?,
                           roi_earned       = roi_earned + ?,
                           next_payout_date = ?
                       WHERE id = ? AND status = 'active'")
            ->execute([$periodsDue, $payout, $cursor, $inv_id]);

        $pdo->prepare("UPDATE wallets
                       SET balance = balance + ?, total_earnings = total_earnings + ?
                       WHERE user_id = ?")
            ->execute([$payout, $payout, $user_id]);

        $reference = generateReference('MVC-ROI');
        $details = json_encode([
            'investment_id' => $inv_id,
            'plan_name'     => $inv['plan_name'],
            'cadence'       => $cadence,
            'periods_paid'  => $periodsDue,
            'per_payout'    => $per_payout,
        ]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
                       VALUES (?, 'roi_payout', 'system', ?, ?, 'completed', ?, ?)")
            ->execute([$user_id, $payout, $reference, $details, date('Y-m-d H:i:s')]);

        $pdo->commit();
        $paidPeriods += $periodsDue;

        cronLog(sprintf('Investment #%d (%s %s): paid %d period(s) = %.2f to user #%d',
                        $inv_id, $inv['plan_name'], $cadence, $periodsDue, $payout, $user_id));

        if (function_exists('sendEmail') && !empty($inv['email'])) {
            sendEmail([
                'to' => $inv['email'],
                'template' => 'investment_payout',
                'variables' => [
                    'user_name'     => $inv['user_name'] ?? 'Investor',
                    'plan_name'     => $inv['plan_name'],
                    'amount'        => number_format($payout, 2),
                    'cadence'       => $cadence,
                    'payouts_made'  => $made + $periodsDue,
                    'payouts_total' => $total,
                    'next_payout'   => date('M d, Y', strtotime($cursor)),
                    'reference'     => $reference,
                ]
            ]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorCount++;
        cronLog("ERROR paying investment #$inv_id: " . $e->getMessage());
    }
}

// ------------------------------------------------------------
// STEP 2 - release principal on matured positions
// ------------------------------------------------------------
$stmt = $pdo->query("
    SELECT inv.id, inv.user_id, inv.plan_name, inv.amount, inv.roi_earned,
           u.email, u.full_name AS user_name
    FROM investments inv
    JOIN users u ON u.id = inv.user_id
    WHERE inv.status = 'active' AND inv.maturity_date <= CURDATE()
");
$matured = $stmt->fetchAll(PDO::FETCH_ASSOC);

cronLog('Investments at maturity: ' . count($matured));

foreach ($matured as $inv) {
    $inv_id    = (int) $inv['id'];
    $user_id   = (int) $inv['user_id'];
    $principal = (float) $inv['amount'];

    try {
        $pdo->beginTransaction();

        // Only the principal moves here - ROI was credited period by period
        // in STEP 1, so adding it again would pay the member twice.
        // The status guard also makes a concurrent run a no-op.
        $upd = $pdo->prepare("UPDATE investments SET status = 'completed' WHERE id = ? AND status = 'active'");
        $upd->execute([$inv_id]);
        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            continue;
        }

        $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
            ->execute([$principal, $user_id]);

        $reference = generateReference('MVC-INVREL');
        $details = json_encode([
            'investment_id'  => $inv_id,
            'plan_name'      => $inv['plan_name'],
            'principal'      => $principal,
            'roi_paid_total' => (float) $inv['roi_earned'],
        ]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
                       VALUES (?, 'investment_release', 'system', ?, ?, 'completed', ?, ?)")
            ->execute([$user_id, $principal, $reference, $details, date('Y-m-d H:i:s')]);

        $pdo->commit();
        $releasedCount++;

        cronLog(sprintf('Investment #%d (%s): released principal %.2f to user #%d',
                        $inv_id, $inv['plan_name'], $principal, $user_id));

        if (function_exists('sendEmail') && !empty($inv['email'])) {
            sendEmail([
                'to' => $inv['email'],
                'template' => 'investment_matured',
                'variables' => [
                    'user_name'  => $inv['user_name'] ?? 'Investor',
                    'plan_name'  => $inv['plan_name'],
                    'principal'  => number_format($principal, 2),
                    'roi_earned' => number_format((float) $inv['roi_earned'], 2),
                    'payout'     => number_format($principal + (float) $inv['roi_earned'], 2),
                    'reference'  => $reference,
                ]
            ]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorCount++;
        cronLog("ERROR releasing investment #$inv_id: " . $e->getMessage());
    }
}

cronLog(sprintf('Cron finished - %d period(s) paid, %d position(s) released, %d error(s)',
                $paidPeriods, $releasedCount, $errorCount));
exit($errorCount > 0 ? 1 : 0);
