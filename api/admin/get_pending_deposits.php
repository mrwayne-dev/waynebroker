<?php
// FILE: /api/admin/get_pending_deposits.php

require_once("../../config/database.php");
require_once(__DIR__ . "/../utilities/helpers.php");   // formatPaymentMethod()
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.
mvcSessionStart();

// 1. Admin Auth Check
if(!isset($_SESSION["admin_id"])){
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Unauthorized access"]);
    exit;
}

try {
    $pdo = getPDO();

    // t.method and t.details are new. Without them an admin was approving a
    // transfer with no idea which coin or network it was sent on, and no way
    // to tell whether the member had even claimed to have paid.
    // Optional per-user scope, for the queue opened from a wallet row in
    // Wallet Management. Without a user_id the response is the global queue the
    // dashboard shows. t.user_id is now selected too - it was not, so even
    // client-side filtering was impossible.
    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    $sql = "
        SELECT
            t.id,
            t.user_id,
            t.reference,
            t.amount,
            t.created_at,
            t.method,
            t.details,
            u.full_name,
            u.email
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.type = 'deposit' AND t.status = 'pending'";

    $params = [];
    if ($userId > 0) {
        $sql .= " AND t.user_id = :uid";
        $params[':uid'] = $userId;
    }
    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedDeposits = [];
    foreach($deposits as $dep) {
        // Decoded here rather than in admin.js: the client should never have
        // to parse a JSON column, and this keeps the shape in one place.
        $details = json_decode((string)($dep['details'] ?? ''), true);
        if (!is_array($details)) $details = [];

        // Written by initiate_deposit for the deposit_address route only, so
        // a secure_exchange row simply has no snapshot.
        $snap = (isset($details['deposit_address']) && is_array($details['deposit_address']))
              ? $details['deposit_address']
              : null;

        $formattedDeposits[] = [
            'id' => (int)$dep['id'],
            'user_id' => (int)$dep['user_id'],
            'reference' => htmlspecialchars($dep['reference']),
            'amount' => (float)$dep['amount'],
            'date' => date('M d, Y H:i', strtotime($dep['created_at'])),
            // Combine name and email for the display column
            'user' => htmlspecialchars($dep['full_name']) . ' (' . htmlspecialchars($dep['email']) . ')',
            'method' => (string)($dep['method'] ?? ''),
            'method_label' => formatPaymentMethod($dep['method'] ?? ''),
            // NULL on a secure_exchange row; the table renders an em dash.
            'asset' => isset($snap['asset']) ? htmlspecialchars((string)$snap['asset']) : null,
            'network' => isset($snap['network']) ? htmlspecialchars((string)$snap['network']) : null,
            'address_label' => isset($snap['label']) ? htmlspecialchars((string)$snap['label']) : null,
            // NOT escaped: the admin copies this to check a block explorer.
            'address' => $snap['address'] ?? null,
            'memo_tag' => $snap['memo_tag'] ?? null,
            'memo_label' => $snap['memo_label'] ?? null,
            'tx_hash' => $details['tx_hash'] ?? null,
            'marked_paid' => !empty($details['user_marked_paid']),
            'marked_paid_at' => isset($details['marked_paid_at'])
                ? date('M d, Y H:i', strtotime((string)$details['marked_paid_at']))
                : null,
        ];
    }

    echo json_encode(["status"=>"success", "data"=>$formattedDeposits]);

} catch(Exception $e) {
    error_log("Deposit Fetch Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status"=>"error", "message"=>"Server error: Could not fetch deposits."]);
}
?>