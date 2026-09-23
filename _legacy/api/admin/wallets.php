<?php
// D:\mrwayne\web_dev\maveren\api\admin\wallets.php
// ============================================================
// PURPOSE: Manage user wallets (Admin View): fetch data, metrics, and update balances.
// ============================================================
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();
header('Content-Type: application/json');

// Ensure only authenticated admins can access this script
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../../config/database.php';
// Assuming getPDO() and executeQuery() helpers are accessible or defined above

$pdo = getPDO();

// Role gate: this endpoint edits wallet balances directly.
// Only isset($_SESSION['admin_id']) was checked before, so a `support`
// admin had exactly the same power here as the owner. Read from the DB,
// fails closed. See mvcRequireAdminRole() in api/utilities/security.php.
mvcRequireAdminRole($pdo, MVC_ROLE_OWNER);

/**
 * Executes a prepared statement and returns results or handles errors.
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error in admin/wallets.php: " . $e->getMessage());
        return false;
    }
}


// --- Request Logic ---

$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;
$action = strtolower($input['action'] ?? '');


// --- Handle POST Actions (e.g., Update Balance) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_balance') {
    $wallet_id = (int)($input['wallet_id'] ?? 0);
    $new_balance = (float)($input['new_balance'] ?? null);

    if ($wallet_id <= 0 || $new_balance === null || $new_balance < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid wallet ID or balance amount.']);
        exit;
    }

    try {
        // Use a transaction to ensure atomicity
        $pdo->beginTransaction();

        $updateSql = "UPDATE wallets SET balance = :balance WHERE id = :wid";
        $stmt = executeQuery($pdo, $updateSql, [
            ':balance' => number_format($new_balance, 2, '.', ''), // Use number_format for consistency when saving DECIMAL
            ':wid' => $wallet_id
        ]);

        if ($stmt && $stmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => "Wallet #{$wallet_id} balance successfully updated to $" . number_format($new_balance, 2) . "."]);
        } else {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Failed to update wallet balance. Wallet might not exist.']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Wallet Update Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error during balance update.']);
    }
    exit;
}

// --- Handle GET Requests (Data Retrieval, Metrics, List) ---

$page = max(1, (int)($input['page'] ?? 1));
$perPage = max(10, (int)($input['per_page'] ?? 10));
$search = trim($input['search'] ?? '');
$filter = strtolower(trim($input['filter'] ?? 'all'));

// 1. Fetch Metrics
$metrics = fetchWalletMetrics($pdo);

// 2. Build Base Query for Wallet List
//
// The LEFT JOIN gives a per-user pending count and amount, which nothing else
// supplies: fetchWalletMetrics() returns GLOBAL scalars, and w.pending_withdrawals
// is a stored dollar figure that drifts (process_withdrawal.php used to complete
// a withdrawal without decrementing it, so it only ever grew). This is derived
// from transactions on every read, so it cannot drift.
$sql = "FROM wallets w 
        JOIN users u ON w.user_id = u.id 
        LEFT JOIN (
            SELECT user_id,
                   SUM(type = 'deposit')  AS pending_deposits_count,
                   SUM(type = 'withdraw') AS pending_withdrawals_count,
                   SUM(CASE WHEN type = 'deposit'  THEN amount ELSE 0 END) AS pending_deposits_amount,
                   SUM(CASE WHEN type = 'withdraw' THEN amount ELSE 0 END) AS pending_withdrawals_amount
            FROM transactions
            WHERE status = 'pending' AND type IN ('deposit', 'withdraw')
            GROUP BY user_id
        ) p ON p.user_id = w.user_id
        WHERE 1";
$params = [];

// Apply filters
if ($filter === 'active') {
    // Only show wallets for active users
    $sql .= " AND u.status = 'active'"; 
} elseif ($filter === 'zero') {
    $sql .= " AND w.balance = 0.00";
} elseif ($filter === 'pending') {
    // Wallets with something waiting on an admin.
    $sql .= " AND (COALESCE(p.pending_deposits_count, 0) + COALESCE(p.pending_withdrawals_count, 0)) > 0";
}

// Apply search
if (!empty($search)) {
    $searchWild = "%$search%";
    // Search by Wallet ID (w.id), User Name, or User Email
    $sql .= " AND (w.id = :widExact OR u.full_name LIKE :s OR u.email LIKE :s)";
    $params[':s'] = $searchWild;
    $params[':widExact'] = is_numeric($search) ? (int)$search : 0;
}

// 3. Count Total Records
$countStmt = executeQuery($pdo, "SELECT COUNT(w.id) " . $sql, $params);
$total = $countStmt ? (int)$countStmt->fetchColumn() : 0;

$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$limitSql = " LIMIT " . $perPage . " OFFSET " . $offset;


// 4. Fetch Wallets (Paginated)
$dataSql = "SELECT 
                w.id AS wallet_id, 
                w.user_id, 
                w.balance, 
                w.total_deposited, 
                w.pending_withdrawals, 
                COALESCE(p.pending_deposits_count, 0)     AS pending_deposits_count,
                COALESCE(p.pending_withdrawals_count, 0)  AS pending_withdrawals_count,
                COALESCE(p.pending_deposits_amount, 0)    AS pending_deposits_amount,
                COALESCE(p.pending_withdrawals_amount, 0) AS pending_withdrawals_amount,
                COALESCE(u.full_name, u.name) AS user_name,
                u.email AS user_email
            " . $sql . " 
            ORDER BY (COALESCE(p.pending_deposits_count, 0) + COALESCE(p.pending_withdrawals_count, 0)) DESC,
                     w.balance DESC" . $limitSql;

$stmt = executeQuery($pdo, $dataSql, $params);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];


// 5. Format JSON Response: Return raw numbers for frontend JS formatting
$formatted = array_map(fn($r) => [
    'wallet_id' => (int)$r['wallet_id'],
    'user_id' => (int)$r['user_id'],
    'balance' => (string)(float)$r['balance'], 
    'deposited' => (string)(float)$r['total_deposited'],
    'pending_withdrawals_sum' => (string)(float)$r['pending_withdrawals'], // Sum stored in wallets table
    // Derived per read from transactions - see the note on the LEFT JOIN.
    'pending_deposits_count'     => (int)$r['pending_deposits_count'],
    'pending_withdrawals_count'  => (int)$r['pending_withdrawals_count'],
    'pending_deposits_amount'    => (string)(float)$r['pending_deposits_amount'],
    'pending_withdrawals_amount' => (string)(float)$r['pending_withdrawals_amount'],
    'user_name' => $r['user_name'],
    'user_email' => $r['user_email']
], $rows);

echo json_encode([
    'status' => 'success',
    'data' => [
        'metrics' => $metrics,
        'wallets' => $formatted,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $perPage
    ]
]);
exit;


/**
 * Retrieves core metrics for the wallet dashboard cards.
 */
function fetchWalletMetrics($pdo) {
    $metrics = [
        'total_wallets' => 0,
        'total_balance' => 0.00,
        'pending_deposits_count' => 0,
        'pending_withdrawals_count' => 0
    ];

    try {
        $metrics['total_wallets'] = $pdo->query("SELECT COUNT(id) FROM wallets")->fetchColumn() ?? 0;
        $metrics['total_balance'] = $pdo->query("SELECT SUM(balance) FROM wallets")->fetchColumn() ?? 0.00;
        
        // Count Pending Deposits (Transaction Type: deposit, Status: pending)
        $stmt_dep = $pdo->prepare("SELECT COUNT(id) FROM transactions WHERE type = 'deposit' AND status = 'pending'");
        $stmt_dep->execute();
        $metrics['pending_deposits_count'] = $stmt_dep->fetchColumn() ?? 0;

        // Count Pending Withdrawals (Transaction Type: withdrawal, Status: pending)
        $stmt_wdr = $pdo->prepare("SELECT COUNT(id) FROM transactions WHERE type = 'withdraw' AND status = 'pending'");
        $stmt_wdr->execute();
        $metrics['pending_withdrawals_count'] = $stmt_wdr->fetchColumn() ?? 0;

    } catch (PDOException $e) {
        error_log("Wallet Metric Fetch Error: " . $e->getMessage());
    }

    return $metrics;
}

?>