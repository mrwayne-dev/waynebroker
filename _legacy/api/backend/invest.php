<?php
// ===============================================
// FILE: /api/backend/investment.php
// PURPOSE: Investment controller for Maveren Capital
// ACTIONS: get_summary, get_plans, start_investment, get_active, unlock_investment
// ===============================================

require_once __DIR__ . '/../utilities/security.php';
// Hardened + proxy-aware session cookie - see api/utilities/security.php.
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

header('Content-Type: application/json');
// CORS removed.
//
// These endpoints are same-origin only - every caller is assets/js/*.js on
// this host - so no CORS headers are needed at all, and the ones that were
// here actively hurt:
//
//   Access-Control-Allow-Origin: *
//   Access-Control-Allow-Credentials: true
//
// A wildcard origin combined with credentials is rejected outright by every
// browser, so this never worked as written; what it did do was advertise
// intent and guarantee that the day someone "fixed" it by echoing back the
// Origin header, any site on the internet could read a member's dashboard.
// The X-CSRF-Token header the client now sends also requires a preflight
// cross-origin, and with no CORS headers that preflight simply fails - which
// is the desired outcome.
//
// The OPTIONS short-circuit is kept: browsers may still preflight, and it
// should return cleanly rather than fall through to the auth check.

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// includes
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/email.php'; // uses sendEmail()
require_once __DIR__ . '/../utilities/security.php';   // rate limiting

// auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}
$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? ($_SESSION['name'] ?? 'User');
$user_email = $_SESSION['email'] ?? '';

// get pdo
try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// parse input
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST ?: $_GET;
$action = trim($input['action'] ?? 'get_summary');

// helper responses
function jsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

function generateReference($prefix = 'MVC-INV') {
    return strtoupper($prefix . '-' . uniqid() . '-' . rand(1000, 9999));
}

// ---------------------------------------------------------------
// Cadence helpers - the single source of truth for how a term is
// sliced into payouts. The cron uses the same rules, so a preview
// shown to the member always matches what actually gets credited.
// ---------------------------------------------------------------

/** Days in one payout period. */
function cadenceDays(string $cadence): int {
    return $cadence === 'monthly' ? 30 : 7;
}

/** Whole payout periods that fit inside the term. */
function payoutCount(string $cadence, int $duration_days): int {
    return (int) floor($duration_days / cadenceDays($cadence));
}

/** Advance a date by exactly one payout period. */
function nextPayoutDate(string $cadence, string $from): string {
    return $cadence === 'monthly'
        ? date('Y-m-d', strtotime($from . ' +1 month'))
        : date('Y-m-d', strtotime($from . ' +7 days'));
}

/**
 * Full projection for an amount against a plan.
 * Returns per-payout value, payout count, total ROI and totals.
 *
 * NOTE ON MATURITY: the maturity date is derived by walking the payout
 * schedule forward, NOT by adding duration_days. Monthly payouts advance by
 * CALENDAR month (so they land on the same date each month, as advertised)
 * while payoutCount() slices the term into 30-day periods -- those two
 * disagree, and a naive "+duration_days" maturity can fall BEFORE the final
 * scheduled payout. When that happens the cron closes the position before
 * paying it and the member silently loses a payout. Deriving maturity from
 * the schedule guarantees the last payout always lands on maturity day.
 */
function projectInvestment(float $amount, float $roi_percent, string $cadence, int $duration_days): array {
    $payouts    = payoutCount($cadence, $duration_days);
    $per_payout = round($amount * $roi_percent / 100, 2);
    $total_roi  = round($per_payout * $payouts, 2);

    $today = date('Y-m-d');
    $first = nextPayoutDate($cadence, $today);

    // Walk the schedule to the final payout; that date IS maturity.
    $cursor = $today;
    for ($i = 0; $i < max($payouts, 1); $i++) {
        $cursor = nextPayoutDate($cadence, $cursor);
    }

    return [
        'cadence'          => $cadence,
        'payouts_total'    => $payouts,
        'per_payout'       => $per_payout,
        'total_roi'        => $total_roi,
        'total_return'     => round($amount + $total_roi, 2),
        'effective_percent'=> $amount > 0 ? round($total_roi / $amount * 100, 2) : 0.0,
        'first_payout_date'=> $first,
        'maturity_date'    => $cursor,
    ];
}


// --------------------- ACTION: get_plans ---------------------
// Optional filter: {"cadence":"weekly"|"monthly"} - omit for all.
if ($action === 'get_plans') {
    $cadence = $input['cadence'] ?? '';
    if ($cadence !== '' && !in_array($cadence, ['weekly', 'monthly'], true)) {
        jsonResponse('error', 'Invalid cadence filter.');
    }

    if ($cadence !== '') {
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE status = 'active' AND cadence = ? ORDER BY min_amount ASC");
        $stmt->execute([$cadence]);
    } else {
        $stmt = $pdo->query("SELECT * FROM plans WHERE status = 'active' ORDER BY cadence DESC, min_amount ASC");
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $plans = [];
    foreach ($rows as $r) {
        $duration = (int) $r['duration_days'];
        $cad      = $r['cadence'];
        $payouts  = payoutCount($cad, $duration);
        $roi      = (float) $r['roi_percent'];

        $plans[] = [
            'id'            => (int) $r['id'],
            'title'         => $r['title'],
            'cadence'       => $cad,
            'description'   => $r['description'],
            'details'       => $r['details'],
            'summary'       => $r['summary'],

            // financials - roi_percent is PER PERIOD, not annualised
            'roi_percent'   => $roi,
            'duration_days' => $duration,
            'payouts_total' => $payouts,
            // headline figure for the card: what the whole term returns
            'total_percent' => round($roi * $payouts, 2),

            // JS expects min/max
            'min'           => (float) $r['min_amount'],
            'max'           => (float) $r['max_amount'],

            'risk'          => $r['risk'],
            'icon'          => $r['icon'],
            'accent'        => $r['accent'],
        ];
    }

    jsonResponse('success', 'Plans loaded.', ['plans' => $plans]);
}


// --------------------- ACTION: preview ---------------------
// Live projection for the amount box - same maths the cron runs.
if ($action === 'preview') {
    $plan_id = (int) ($input['plan_id'] ?? 0);
    $amount  = (float) ($input['amount'] ?? 0);

    if ($plan_id <= 0) jsonResponse('error', 'Invalid plan selected.');

    $pstmt = $pdo->prepare("SELECT title, cadence, roi_percent, duration_days, min_amount, max_amount
                            FROM plans WHERE id = ? AND status = 'active' LIMIT 1");
    $pstmt->execute([$plan_id]);
    $plan = $pstmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) jsonResponse('error', 'Invalid plan selected.');

    $min = (float) $plan['min_amount'];
    $max = (float) $plan['max_amount'];

    $projection = projectInvestment($amount, (float) $plan['roi_percent'], $plan['cadence'], (int) $plan['duration_days']);
    $projection['plan_name']  = $plan['title'];
    $projection['in_range']   = ($amount >= $min && $amount <= $max);
    $projection['min_amount'] = $min;
    $projection['max_amount'] = $max;

    jsonResponse('success', 'Preview calculated.', ['preview' => $projection]);
}


// --------------------- ACTION: get_summary ---------------------
if ($action === 'get_summary') {
    try {
        $stmt = $pdo->prepare("SELECT
                COALESCE(SUM(CASE WHEN status='active' THEN amount END),0) AS total_active,
                COALESCE(SUM(roi_earned),0)                                AS total_roi,
                COUNT(CASE WHEN status='active' THEN 1 END)                AS ongoing_count,
                MIN(CASE WHEN status='active' THEN next_payout_date END)   AS next_payout,
                MIN(CASE WHEN status='active' THEN maturity_date END)      AS next_maturity
            FROM investments WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_active = (float) ($row['total_active'] ?? 0.00);
        $total_roi    = (float) ($row['total_roi'] ?? 0.00);
        $ongoing      = (int) ($row['ongoing_count'] ?? 0);

        // Value of the next payout due across every active position on that date.
        $next_payout_amount = 0.00;
        if (!empty($row['next_payout'])) {
            $ps = $pdo->prepare("SELECT COALESCE(SUM(amount * roi_percent / 100), 0)
                                 FROM investments
                                 WHERE user_id = ? AND status = 'active' AND next_payout_date = ?");
            $ps->execute([$user_id, $row['next_payout']]);
            $next_payout_amount = round((float) $ps->fetchColumn(), 2);
        }

        $wstmt = $pdo->prepare("SELECT balance, total_investments, total_earnings FROM wallets WHERE user_id = ?");
        $wstmt->execute([$user_id]);
        $wallet = $wstmt->fetch(PDO::FETCH_ASSOC) ?: ['balance' => 0.00, 'total_investments' => 0.00, 'total_earnings' => 0.00];

        jsonResponse('success', 'Investment summary loaded.', [
            'summary' => [
                'active_investments_value' => round($total_active, 2),
                'total_roi'                => round($total_roi, 2),
                'ongoing_plans_count'      => $ongoing,
                'portfolio_value'          => round($total_active + (float) $wallet['balance'], 2),
                'next_payout_date'         => $row['next_payout']   ? date('M d, Y', strtotime($row['next_payout']))   : '—',
                'next_payout_amount'       => $next_payout_amount,
                'next_maturity'            => $row['next_maturity'] ? date('M d, Y', strtotime($row['next_maturity'])) : '—',
            ],
            'wallet' => [
                'balance'           => (float) $wallet['balance'],
                'total_investments' => (float) $wallet['total_investments'],
                'total_earnings'    => (float) $wallet['total_earnings'],
            ]
        ]);
    } catch (Exception $e) {
        error_log('Investment summary error: ' . $e->getMessage());
        jsonResponse('error', 'Failed to load investment summary.');
    }
}

// --------------------- ACTION: get_active ---------------------
if ($action === 'get_active') {
    try {
        $stmt = $pdo->prepare("SELECT id, plan_name, cadence, amount, roi_percent, duration_days,
                                      payouts_total, payouts_made, next_payout_date, status,
                                      maturity_date, roi_earned, created_at
                               FROM investments WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $payouts_total = (int) $r['payouts_total'];
            $payouts_made  = (int) $r['payouts_made'];
            $out[] = [
                'id'            => (int) $r['id'],
                'plan'          => $r['plan_name'],
                'cadence'       => $r['cadence'],
                'amount'        => (float) $r['amount'],
                'roi_percent'   => (float) $r['roi_percent'],
                'per_payout'    => round((float) $r['amount'] * (float) $r['roi_percent'] / 100, 2),
                'duration_days' => (int) $r['duration_days'],
                'payouts_total' => $payouts_total,
                'payouts_made'  => $payouts_made,
                'progress_pct'  => $payouts_total > 0 ? round($payouts_made / $payouts_total * 100, 1) : 0.0,
                'status'        => $r['status'],
                'next_payout'   => ($r['status'] === 'active' && $r['next_payout_date'])
                                    ? date('M d, Y', strtotime($r['next_payout_date'])) : null,
                'maturity_date' => $r['maturity_date'] ? date('M d, Y', strtotime($r['maturity_date'])) : null,
                'roi_earned'    => (float) $r['roi_earned'],
                'date_started'  => date('M d, Y', strtotime($r['created_at'])),
            ];
        }
        jsonResponse('success', 'Active investments loaded.', ['investments' => $out]);
    } catch (Exception $e) {
        error_log('get_active error: ' . $e->getMessage());
        jsonResponse('error', 'Failed to load active investments.');
    }
}


// --------------------- ACTION: get_matured ---------------------
// Positions past their maturity date that still need the principal released.
if ($action === 'get_matured') {
    $stmt = $pdo->prepare("SELECT id, plan_name, cadence, amount, roi_percent, roi_earned,
                                  payouts_made, payouts_total, maturity_date
                           FROM investments
                           WHERE user_id = ? AND status = 'active' AND maturity_date <= CURDATE()");
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        // roi_earned is the running total the cron has already credited -
        // never recompute it here, or a partially paid position double-counts.
        $r['roi_earned']    = (float) $r['roi_earned'];
        $r['amount']        = (float) $r['amount'];
        $r['total_payout']  = round($r['amount'] + $r['roi_earned'], 2);
        $r['maturity_date'] = date('M d, Y', strtotime($r['maturity_date']));
    }
    unset($r);
    jsonResponse('success', 'Matured plans loaded.', ['matured' => $rows]);
}


// --------------------- ACTION: start_investment ---------------------
if ($action === 'start_investment') {
    mvcEnforceRateLimit($pdo, 'invest', (string) $user_id);
    mvcRecordAttempt($pdo, 'invest', mvcClientIp());

    $plan_id = (int) ($input['plan_id'] ?? 0);
    $amount = (float) ($input['amount'] ?? 0);

    if ($plan_id <= 0) jsonResponse('error', 'Invalid plan selected.');
    if ($amount <= 0) jsonResponse('error', 'Enter a valid investment amount.');

    // Fetch the selected plan from the catalog
    $pstmt = $pdo->prepare("SELECT title, cadence, roi_percent, duration_days, min_amount, max_amount
                            FROM plans WHERE id = ? AND status = 'active' LIMIT 1");
    $pstmt->execute([$plan_id]);
    $plan = $pstmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) jsonResponse('error', 'Invalid plan selected.');

    // Range validation
    $min = (float) $plan['min_amount'];
    $max = (float) $plan['max_amount'];
    if ($amount < $min || $amount > $max) {
        jsonResponse('error', 'Amount must be between $' . number_format($min, 2) .
                              ' and $' . number_format($max, 2) . ' for this plan.');
    }

    $cadence       = $plan['cadence'];
    $roi_percent   = (float) $plan['roi_percent'];
    $duration_days = (int) $plan['duration_days'];

    $projection = projectInvestment($amount, $roi_percent, $cadence, $duration_days);
    if ($projection['payouts_total'] < 1) {
        jsonResponse('error', 'This plan is misconfigured: its term is shorter than one payout period.');
    }

    try {
        $pdo->beginTransaction();

        // fetch wallet
        $wstmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
        $wstmt->execute([$user_id]);
        $wallet = $wstmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);
            $wstmt->execute([$user_id]);
            $wallet = $wstmt->fetch(PDO::FETCH_ASSOC);
        }

        if ((float) $wallet['balance'] < $amount) {
            $pdo->rollBack();
            jsonResponse('error', 'Insufficient wallet balance.');
        }

        $maturity_date    = $projection['maturity_date'];
        $next_payout_date = $projection['first_payout_date'];
        $reference        = generateReference('MVC-INV');
        $now              = date('Y-m-d H:i:s');

        // Deduct wallet
        $pdo->prepare("UPDATE wallets SET balance = balance - ?, total_investments = total_investments + ? WHERE user_id = ?")
            ->execute([$amount, $amount, $user_id]);

        // Create investment. plan_name / cadence / roi_percent / duration_days are
        // snapshots: editing the plan later must not rewrite this position's terms.
        $pdo->prepare("INSERT INTO investments
                        (user_id, plan_id, plan_name, cadence, amount, roi_percent, duration_days,
                         payouts_total, payouts_made, next_payout_date, maturity_date,
                         roi_earned, status, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0.00, 'active', ?)")
            ->execute([$user_id, $plan_id, $plan['title'], $cadence, $amount, $roi_percent, $duration_days,
                       $projection['payouts_total'], $next_payout_date, $maturity_date, $now]);

        $investment_id = (int) $pdo->lastInsertId();

        // Transaction record
        $details = json_encode([
            'investment_id' => $investment_id,
            'plan_id'       => $plan_id,
            'plan_name'     => $plan['title'],
            'cadence'       => $cadence,
        ]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, reference, status, details, created_at)
                       VALUES (?, 'investment', ?, ?, 'completed', ?, ?)")
            ->execute([$user_id, $amount, $reference, $details, $now]);

        $pdo->commit();

        // Emails
        if (function_exists('sendEmail')) {
            sendEmail([
                'to' => $user_email,
                'template' => 'investment_confirmed',
                'variables' => [
                    'user_name'     => $user_name,
                    'plan_name'     => $plan['title'],
                    'amount'        => number_format($amount, 2),
                    'roi_percent'   => $roi_percent,
                    'cadence'       => $cadence,
                    'per_payout'    => number_format($projection['per_payout'], 2),
                    'payouts_total' => $projection['payouts_total'],
                    'duration_days' => $duration_days,
                    'first_payout'  => date('M d, Y', strtotime($next_payout_date)),
                    'maturity_date' => date('M d, Y', strtotime($maturity_date)),
                    'reference'     => $reference,
                ]
            ]);

            if (defined('ADMIN_CONTACT_EMAIL')) {
                sendEmail([
                    'to' => ADMIN_CONTACT_EMAIL,
                    'template' => 'admin_investment_notification',
                    'variables' => [
                        'user_name'  => $user_name,
                        'user_email' => $user_email,
                        'plan_name'  => $plan['title'],
                        'amount'     => number_format($amount, 2),
                        'reference'  => $reference,
                    ]
                ]);
            }
        }

        jsonResponse('success', 'Investment started successfully.', [
            'investment_id' => $investment_id,
            'reference'     => $reference,
            'cadence'       => $cadence,
            'per_payout'    => $projection['per_payout'],
            'payouts_total' => $projection['payouts_total'],
            'first_payout'  => date('M d, Y', strtotime($next_payout_date)),
            'maturity_date' => date('M d, Y', strtotime($maturity_date)),
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('start_investment error: ' . $e->getMessage());
        jsonResponse('error', 'Failed to start investment. Please try again.');
    }
}

// --------------------- ACTION: unlock_investment ---------------------
if ($action === 'unlock_investment') {
    // (unchanged, logic already correct)
    $inv_id = (int) ($input['investment_id'] ?? 0);
    if ($inv_id <= 0) jsonResponse('error', 'Invalid investment id.');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, user_id, amount, roi_percent, duration_days, status, maturity_date, roi_earned FROM investments WHERE id = ? FOR UPDATE");
        $stmt->execute([$inv_id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv) {
            $pdo->rollBack();
            jsonResponse('error', 'Investment not found.');
        }
        if ((int)$inv['user_id'] !== $user_id) {
            $pdo->rollBack();
            jsonResponse('error', 'Permission denied.');
        }
        if ($inv['status'] !== 'active') {
            $pdo->rollBack();
            jsonResponse('error', 'Investment is not active.');
        }
        if (strtotime($inv['maturity_date']) > strtotime(date('Y-m-d'))) {
            $pdo->rollBack();
            jsonResponse('error', 'Investment has not matured yet.');
        }

        // roi_earned is the running total the cron has already credited to the
        // wallet period by period. Releasing a matured position returns the
        // PRINCIPAL only - re-crediting ROI here would pay it out twice.
        $roi_earned   = (float) $inv['roi_earned'];
        $principal    = (float) $inv['amount'];
        $total_payout = $principal;

        $pdo->prepare("UPDATE investments SET status = 'completed' WHERE id = ?")
            ->execute([$inv_id]);
        $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
            ->execute([$principal, $user_id]);

        $reference = generateReference('MVC-INVREL');
        $details = json_encode([
            'investment_id'  => $inv_id,
            'principal'      => $principal,
            'roi_paid_total' => $roi_earned,
            'note'           => 'Principal released at maturity; ROI was credited per period.',
        ]);
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, reference, status, details, created_at)
                       VALUES (?, 'investment', ?, ?, 'completed', ?, ?)")
            ->execute([$user_id, $total_payout, $reference, $details, $now]);

        $pdo->commit();

        if (function_exists('sendEmail')) {
            sendEmail([
                'to' => $user_email,
                'template' => 'investment_matured',
                'variables' => [
                    'user_name' => $user_name,
                    'investment_id' => $inv_id,
                    'payout' => number_format($total_payout, 2),
                    'roi_earned' => number_format($roi_earned, 2),
                    'reference' => $reference,
                    // investment_matured also renders {{plan_name}} and
                    // {{principal}}; both were missing.
                    'plan_name' => $inv['plan_name'] ?? 'your plan',
                    'principal' => number_format($principal, 2),
                ]
            ]);
        }

        jsonResponse('success', 'Principal released to your wallet.', [
            'payout'     => $total_payout,
            'principal'  => $principal,
            'roi_earned' => $roi_earned,
            'reference'  => $reference
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('unlock_investment error: ' . $e->getMessage());
        jsonResponse('error', 'Failed to unlock investment. Please try again.');
    }
}

// default
http_response_code(400);
jsonResponse('error', 'Invalid action.');
