<?php
ini_set('display_errors', 0);
error_reporting(0);
// ===============================================
// FILE: /api/backend/dashboard.php
// PURPOSE: Provides user dashboard data - wallet stats,
// live investment position summary, and recent transactions.
// Supports SPA dashboard requests (via fetch or AJAX).
// ===============================================
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware: use_strict_mode, and a cookie_secure that
// survives a TLS-terminating proxy (the inline options this replaced
// tested $_SERVER['HTTPS'] === 'on', which is unset behind one).
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

// ---------------------------
// Include dependencies
// ---------------------------
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../utilities/helpers.php';   // formatTransactionType()

// ---------------------------
// Auth check
// ---------------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// ---------------------------
// Initialize DB connection
// ---------------------------
try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// ---------------------------
// Parse input (optional action param)
// ---------------------------
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? 'get_data';

// ===========================================================
// ACTION: GET WALLET (simple standalone for external modules)
// ===========================================================
if ($action === 'get_wallet') {
    try {
        $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            // Auto-create wallet if not found
            $pdo->prepare("
                INSERT INTO wallets (user_id, balance, total_deposited, total_withdrawn, total_investments, total_earnings, pending_withdrawals)
                VALUES (?, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00)
            ")->execute([$user_id]);
            $wallet = ['balance' => 0.00];
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'wallet' => ['balance' => (float)$wallet['balance']]
            ]
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error fetching wallet.']);
        exit;
    }
}

// ===========================================================
// ACTION: GET DATA (main dashboard payload)
// ===========================================================
if ($action !== 'get_data') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit;
}

// ===========================================================
// FETCH WALLET DATA
// ===========================================================
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet) {
    // Create a default wallet if none exists
    $pdo->prepare("
        INSERT INTO wallets (user_id, balance, total_deposited, total_withdrawn, total_investments, total_earnings, pending_withdrawals)
        VALUES (?, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00)
    ")->execute([$user_id]);
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
}
// ===========================================================
// LIVE INVESTMENT POSITION SUMMARY
// Derived on read from `investments`, so the dashboard cannot drift
// out of sync with the positions the cron is actually paying.
// ===========================================================
$invStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status='active' THEN amount END), 0) AS active_capital,
        COALESCE(SUM(roi_earned), 0)                                AS roi_earned,
        COUNT(CASE WHEN status='active' THEN 1 END)                 AS active_count,
        MIN(CASE WHEN status='active' THEN next_payout_date END)    AS next_payout_date
    FROM investments
    WHERE user_id = ?
");
$invStmt->execute([$user_id]);
$invSummary = $invStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$activeCapital = (float) ($invSummary['active_capital'] ?? 0);
$roiEarned     = (float) ($invSummary['roi_earned'] ?? 0);
$activeCount   = (int)   ($invSummary['active_count'] ?? 0);
$nextPayoutDay = $invSummary['next_payout_date'] ?? null;

// Value of the next payout across every position falling due that day.
$nextPayoutAmount = 0.00;
if ($nextPayoutDay) {
    $npStmt = $pdo->prepare("SELECT COALESCE(SUM(amount * roi_percent / 100), 0)
                             FROM investments
                             WHERE user_id = ? AND status = 'active' AND next_payout_date = ?");
    $npStmt->execute([$user_id, $nextPayoutDay]);
    $nextPayoutAmount = round((float) $npStmt->fetchColumn(), 2);
}

// Keep the denormalised wallet counter honest.
if (abs($activeCapital - (float) $wallet['total_investments']) > 0.01) {
    $pdo->prepare("UPDATE wallets SET total_investments = ? WHERE user_id = ?")
        ->execute([$activeCapital, $user_id]);
    $wallet['total_investments'] = $activeCapital;
}

// ===========================================================
// COUNT PENDING WITHDRAWALS
// ===========================================================
$countStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM transactions WHERE user_id = ? AND type = 'withdraw' AND status = 'pending'");
$countStmt->execute([$user_id]);
$pendingWithdrawalCount = (int)$countStmt->fetchColumn();

// ===========================================================
// FETCH RECENT TRANSACTIONS
// ===========================================================
$stmt = $pdo->prepare("
    SELECT type, method, amount, status, reference, created_at
    FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_activity = [];
foreach ($transactions as $txn) {
    $recent_activity[] = [
        'type' => formatTransactionType($txn['type']),
        'method' => ucfirst(str_replace('_', ' ', $txn['method'] ?? '')),
        'amount' => (float)$txn['amount'],        'status' => ucfirst($txn['status']),
        'reference' => $txn['reference'],
        'date' => date('M d, Y', strtotime($txn['created_at'])),
    ];
}

// ===========================================================
// ANNOUNCEMENTS
// ===========================================================
// Admins write these in the admin panel. Until now nothing on the member
// side ever read the table back, so an admin could publish an announcement
// and no member would ever see it - the feature only existed end-to-end on
// the admin half.
//
// Drafts are excluded here rather than in the client: a draft is unfinished
// copy an admin has not chosen to show anyone, so it must not travel to the
// browser at all.
$annStmt = $pdo->query(
    "SELECT id, title, body, category, created_at
       FROM announcements
      WHERE status = 'published'
      ORDER BY created_at DESC
      LIMIT 5"
);
$announcements = [];
foreach ($annStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $announcements[] = [
        'id'       => (int) $a['id'],
        'title'    => $a['title'],
        'body'     => $a['body'],
        'category' => $a['category'] ?: 'general',
        'date'     => date('M d, Y', strtotime($a['created_at'])),
    ];
}

// ===========================================================
// RESPONSE FORMAT
// ===========================================================
echo json_encode([
    'status' => 'success',
    'data' => [
        'wallet' => [
            'balance' => (float)$wallet['balance'],
            'total_deposited' => (float)$wallet['total_deposited'],
            'total_withdrawn' => (float)$wallet['total_withdrawn'],
            'investments' => (float)$wallet['total_investments'],
            'total_earnings' => (float)$wallet['total_earnings'],
            'pending_withdrawals' => $pendingWithdrawalCount, // now counts requests
        ],
        'investments' => [
            'active_capital'      => round($activeCapital, 2),
            'active_count'        => $activeCount,
            'roi_earned'          => round($roiEarned, 2),
            'portfolio_value'     => round($activeCapital + (float)$wallet['balance'], 2),
            'next_payout_date'    => $nextPayoutDay ? date('M d, Y', strtotime($nextPayoutDay)) : null,
            'next_payout_amount'  => $nextPayoutAmount,
        ],
        'recent_activity' => $recent_activity,
        'announcements'   => $announcements,
    ],
]);
exit;
?>
