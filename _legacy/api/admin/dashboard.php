<?php
// FILE: /api/admin/dashboard.php
// PURPOSE: Provides global system statistics, metrics, and recent activity for the Admin Dashboard.

// ---------------------------
// Session & Headers
// ---------------------------
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware: use_strict_mode, and a cookie_secure that
// survives a TLS-terminating proxy (the inline options this replaced
// tested $_SERVER['HTTPS'] === 'on', which is unset behind one).
mvcSessionStart();
header('Content-Type: application/json; charset=utf-8');
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

// ---------------------------
// Include dependencies
// ---------------------------
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utilities/helpers.php';   // formatTransactionType()

// ---------------------------
// **ADMIN** Auth Check
// ---------------------------
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Admin Unauthorized. Please log in.']);
    exit;
}

// ---------------------------
// Initialize DB connection
// ---------------------------
try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    error_log("Admin Dashboard DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// ---------------------------
// 1. Fetch Global Metrics for Cards & Alerts
// ---------------------------
$totalRevenue = 0.00;
$activeInvestmentsCount = 0;
$totalUsers = 0;
$pendingDeposits = 0;
$pendingWithdrawals = 0;
$totalInvestedAmount = 0.00; 

try {
    // Metric 1: Total Revenue (all deposits into wallets)
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(total_deposited), 0) FROM wallets")->fetchColumn();

    // NOTE: a "total donations" metric used to be queried here against
    // wallets.total_donations, a column left over from the inherited product
    // that this schema does not have. The query threw, the catch below
    // swallowed it, and EVERY metric after it (active investments, total
    // users, both pending counts, total invested) silently stayed 0. Nothing
    // consumed the value: its #total-donations element does not exist in any
    // admin page. Removed rather than repaired.

    // Metric 2: Active Investments Count
    $activeInvestmentsCount = $pdo->query("SELECT COUNT(id) FROM investments WHERE status = 'active'")->fetchColumn();

    // Metric 3: Total Active Users
    $totalUsers = $pdo->query("SELECT COUNT(id) FROM users WHERE status = 'active'")->fetchColumn();

    // Pending Alerts: Deposits & Withdrawals (Count)
    $pendingDeposits = $pdo->query("SELECT COUNT(id) FROM transactions WHERE type = 'deposit' AND status = 'pending'")->fetchColumn();
    $pendingWithdrawals = $pdo->query("SELECT COUNT(id) FROM transactions WHERE type = 'withdraw' AND status = 'pending'")->fetchColumn();
    
    // Total Invested amount (used for chart comparison)
    $totalInvestedAmount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE status = 'active'")->fetchColumn();
    
} catch (Exception $e) {
    error_log("Admin Metric Query Error: " . $e->getMessage());
}

// ---------------------------
// 2. Fetch Recent Transactions for the Table
// ---------------------------
$recentTransactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.type, t.amount, t.status, u.full_name, t.created_at
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($transactions as $txn) {
        $recentTransactions[] = [
            'date' => date('M d, Y', strtotime($txn['created_at'])),
            'user' => htmlspecialchars($txn['full_name'] ?? 'System User'),
            // FIX: Format type to 'Capitalized First Letter'
            'type' => htmlspecialchars(formatTransactionType($txn['type'] ?? '')), 
            'amount' => (float)$txn['amount'],
            'status' => htmlspecialchars($txn['status'] ?? 'N/A'),
        ];
    }
} catch (Exception $e) {
    error_log("Admin Recent TXN Query Error: " . $e->getMessage());
}

// ---------------------------
// 3. Calculate Chart Ratios (Distribution of monetary values)
// ---------------------------
// The "donations" slice went with the removed metric above; the chart legend
// in pages/admin/dashboard.php only ever had Revenue / Invested / Users.
$chartSources = [
    'revenue_raw' => (float)$totalRevenue,
    'investments_raw' => (float)$totalInvestedAmount,
];

$chartMonetaryTotal = $chartSources['revenue_raw'] + $chartSources['investments_raw'];

$chartPercentages = [
    'revenue' => 0.0,
    'investments' => 0.0,
    'users' => 0.0,
];

if ($chartMonetaryTotal > 0) {
    $chartPercentages['revenue'] = round(($chartSources['revenue_raw'] / $chartMonetaryTotal) * 100, 1);
    $chartPercentages['investments'] = round(($chartSources['investments_raw'] / $chartMonetaryTotal) * 100, 1);

    // Assign remaining percentage to users/placeholder slice for a full 100% chart visualization
    $monetarySum = $chartPercentages['revenue'] + $chartPercentages['investments'];
    $chartPercentages['users'] = round(max(0, 100 - $monetarySum), 1);
} else {
    // If no data, split for visualization or assign all to users
    $chartPercentages['users'] = 100.0;
}


// ---------------------------
// RESPONSE FORMAT
// ---------------------------
echo json_encode([
    'status' => 'success',
    'data' => [
        'metrics' => [
            'total_revenue' => (float)$totalRevenue,
            'active_investments' => (int)$activeInvestmentsCount,
            'total_users' => (int)$totalUsers,
            // Capital currently under management. The #total-aum card has always
            // existed in the admin markup but nothing ever populated it, so it
            // was pinned at $0.00.
            'total_aum' => (float)$totalInvestedAmount,
        ],
        'pending_alerts' => [
            'deposits' => (int)$pendingDeposits,
            'withdrawals' => (int)$pendingWithdrawals,
        ],
        'recent_activity' => $recentTransactions,
        'chart_data' => $chartPercentages,
    ],
]);
exit;
?>