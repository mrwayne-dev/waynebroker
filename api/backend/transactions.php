<?php
// ============================================================
// FILE: /api/backend/transactions.php
// PURPOSE: Fetch all user transactions with pagination, search,
// filtering (status/type), and export support.
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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../utilities/helpers.php';   // formatTransactionType()

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
  exit;
}

$user_id = (int) $_SESSION['user_id'];
$pdo = getPDO();

// Parse input (POST or GET)
$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;

$page = max(1, (int)($input['page'] ?? 1));
$limit = max(10, (int)($input['limit'] ?? 10));
$offset = ($page - 1) * $limit;

$search = trim($input['search'] ?? '');
$statusFilter = strtolower(trim($input['status'] ?? 'all'));
$typeFilter = strtolower(trim($input['type'] ?? 'all'));
$export = isset($input['export']) && $input['export'] === 'true';

// Build SQL base
$sql = "FROM transactions WHERE user_id = :uid";
$params = ['uid' => $user_id];

// Apply filters
if ($statusFilter !== 'all' && $statusFilter !== '') {
  $sql .= " AND status = :status";
  $params['status'] = $statusFilter;
}
if ($typeFilter !== 'all' && $typeFilter !== '') {
  $sql .= " AND type = :type";
  $params['type'] = $typeFilter;
}
if ($search !== '') {
  $sql .= " AND (reference LIKE :search OR method LIKE :search OR type LIKE :search)";
  $params['search'] = "%$search%";
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) $sql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Get transactions (paginated)
$dataSql = "SELECT id, reference, type, amount, status, created_at 
            $sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV export handler
if ($export) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="transactions_export.csv"');
  $output = fopen('php://output', 'w');
  fputcsv($output, ['Transaction ID', 'Date', 'Type', 'Amount (USD)', 'Status']);
  foreach ($rows as $r) {
    fputcsv($output, [
      $r['reference'],
      date('M d, Y h:i A', strtotime($r['created_at'])),
      formatTransactionType($r['type']),
      number_format((float)$r['amount'], 2),
      strtoupper($r['status'])
    ]);
  }
  fclose($output);
  exit;
}

// Format response
$formatted = array_map(fn($r) => [
  'id' => (int)$r['id'],
  'reference' => $r['reference'],
  'date' => date('M d, Y h:i A', strtotime($r['created_at'])),
  'type' => formatTransactionType($r['type']),
  'amount' => number_format((float)$r['amount'], 2),
  'status' => ucfirst($r['status'])
], $rows);

// Return JSON
echo json_encode([
  'status' => 'success',
  'data' => [
    'transactions' => $formatted,
    'pagination' => [
      'total' => $total,
      'page' => $page,
      'limit' => $limit,
      'pages' => max(1, ceil($total / $limit))
    ]
  ]
]);
exit;
