<?php
require_once("../../config/database.php");
require_once("../../api/utilities/helpers.php");
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.
mvcSessionStart();
header('Content-Type: application/json');

// Admin Auth Check - exposes all users' pending withdrawals (PII + amounts).
if (!isset($_SESSION["admin_id"])) {
    logSecurityEvent('unauthorized_admin_access', ['endpoint' => 'get_pending_withdrawals', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

try {
    $pdo = getPDO();

    // t.method and t.reference are new. The pending-withdrawals table showed
    // User / Amount / Date only, so an admin released funds without seeing
    // whether the payout was going to a bank account or a wallet address.
    // Optional per-user scope - see the note in get_pending_deposits.php.
    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    $sql = "
        SELECT t.id, t.user_id, t.amount, t.method, t.reference,
               u.full_name AS user,
               DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') AS date
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.type = 'withdraw'
          AND t.status = 'pending'";

    $params = [];
    if ($userId > 0) {
        $sql .= " AND t.user_id = :uid";
        $params[':uid'] = $userId;
    }
    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id'           => (int) $row['id'],
            'user_id'      => (int) $row['user_id'],
            'user'         => (string) $row['user'],
            'amount'       => (float) $row['amount'],
            'date'         => (string) $row['date'],
            'reference'    => (string) ($row['reference'] ?? ''),
            'method'       => (string) ($row['method'] ?? ''),
            'method_label' => formatPaymentMethod($row['method'] ?? ''),
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $rows,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not fetch withdrawals'
    ]);
}
