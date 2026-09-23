<?php
// D:\mrwayne\web_dev\maveren\api\admin\transactions.php
// ============================================================
// PURPOSE: Fetch all platform transactions (Admin View) with
// pagination, search, filtering, and metric calculation.
// ============================================================
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();
header('Content-Type: application/json');

// Ensure only authenticated admins can access this script
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../../config/database.php';
require_once __DIR__ . '/../utilities/helpers.php';   // formatTransactionType()
// Assuming executeQuery helper is defined elsewhere (e.g., in a utils file or copied here)
// For simplicity, we define it here, assuming database.php provides getPDO().
$pdo = getPDO();

/**
 * Executes a prepared statement and returns results or handles errors.
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error in admin/transactions.php: " . $e->getMessage());
        return false;
    }
}


// --- Request Logic ---

$input = $_GET; // Transactions page primarily uses GET for state/pagination

$page = max(1, (int)($input['page'] ?? 1));
$perPage = max(10, (int)($input['per_page'] ?? 10));
$search = trim($input['search'] ?? '');
$filter = strtolower(trim($input['filter'] ?? 'all'));
$export = isset($input['export']) && $input['export'] === 'true';

// 1. Build Base Query and Parameters (for COUNT and DATA)
$sql = "FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE 1";
$params = [];

// Apply filters (type or status)
if ($filter !== 'all' && $filter !== '') {
    if (in_array($filter, ['pending', 'completed', 'failed'])) {
        $sql .= " AND t.status = :filter";
    } else {
        // deposit, withdrawal, donation, investment
        $sql .= " AND t.type = :filter";
    }
    $params[':filter'] = $filter;
}

// Apply search
if (!empty($search)) {
    $searchWild = "%$search%";
    // Search by Transaction ID (t.id), Reference, or User Name/Email
    $sql .= " AND (t.id = :idExact OR t.reference LIKE :s OR u.full_name LIKE :s OR u.email LIKE :s)";
    $params[':s'] = $searchWild;
    $params[':idExact'] = is_numeric($search) ? (int)$search : 0;
}

// 2. Fetch Metrics (Separate Queries for performance/simplicity)
$metrics = fetchTransactionMetrics($pdo);

// 3. Count Total Records
$countStmt = executeQuery($pdo, "SELECT COUNT(t.id) " . $sql, $params);
if (!$countStmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error during count.']);
    exit;
}
$total = (int)$countStmt->fetchColumn();

$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$limitSql = " LIMIT " . $perPage . " OFFSET " . $offset;


// 4. Fetch Transactions (Paginated)
$dataSql = "SELECT 
                t.id, 
                t.reference, 
                t.type, 
                t.amount, 
                t.status, 
                t.created_at,
                u.id AS user_id,
                COALESCE(u.full_name, u.name) AS user_name 
            " . $sql . " 
            ORDER BY t.created_at DESC" . $limitSql;

$stmt = executeQuery($pdo, $dataSql, $params);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];


// 5. CSV Export Handler
if ($export) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="admin_transactions_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Reference', 'Date', 'User Name', 'User Email', 'Type', 'Amount (USD)', 'Status']);
    
    foreach ($rows as $r) {
        // Fetch user email for export (requires extra query, kept outside of main list query for simplicity)
        $emailStmt = executeQuery($pdo, "SELECT email FROM users WHERE id = :id", [':id' => $r['user_id']]);
        $email = $emailStmt ? $emailStmt->fetchColumn() : 'N/A';

        fputcsv($output, [
            $r['id'],
            $r['reference'],
            date('Y-m-d H:i', strtotime($r['created_at'])),
            $r['user_name'],
            $email,
            formatTransactionType($r['type']),
            number_format((float)$r['amount'], 2),
            strtoupper($r['status'])
        ]);
    }
    fclose($output);
    exit;
}

// 6. Format JSON Response
$formatted = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'reference' => $r['reference'],
    'date' => date('M d, Y H:i', strtotime($r['created_at'])),
    'type' => formatTransactionType($r['type']),
    'amount' => (string)(float)$r['amount'], 
    'status' => ucfirst($r['status']),
    'user_name' => $r['user_name'],
    'user_id' => $r['user_id']
], $rows);

echo json_encode([
    'status' => 'success',
    'data' => [
        'metrics' => $metrics,
        'transactions' => $formatted,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $perPage
    ]
]);
exit;


/**
 * Retrieves core metrics for the transaction dashboard cards.
 */
function fetchTransactionMetrics($pdo) {
    $metrics = [
        'total_transactions' => 0,
        'total_volume' => 0.00,
        'pending_count' => 0,
        'today_count' => 0
    ];

    try {
        $metrics['total_transactions'] = $pdo->query("SELECT COUNT(id) FROM transactions")->fetchColumn();
        
        // Total Volume (Completed transactions, assumed from context)
        $metrics['total_volume'] = $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'completed'")->fetchColumn() ?? 0.00;
        
        // Pending Count
        $metrics['pending_count'] = $pdo->query("SELECT COUNT(id) FROM transactions WHERE status = 'pending'")->fetchColumn();
        
        // Today Count
        $today = date('Y-m-d');
        $stmt_today = $pdo->prepare("SELECT COUNT(id) FROM transactions WHERE DATE(created_at) = :today");
        $stmt_today->bindParam(':today', $today);
        $stmt_today->execute();
        $metrics['today_count'] = $stmt_today->fetchColumn();

    } catch (PDOException $e) {
        error_log("Metric Fetch Error: " . $e->getMessage());
    }

    return $metrics;
}

?>