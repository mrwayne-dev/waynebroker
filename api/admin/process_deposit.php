<?php
/**
 * FILE: C:\mrwayne\web_dev\maveren\api\admin\process_deposit.php
 * PURPOSE: Handles the admin action to either complete or cancel a pending deposit,
 * including database updates and sending the relevant email notification.
 */

require_once("../../config/database.php"); 
require_once("../../api/utilities/email_temps.php"); 
require_once("../backend/email.php"); // Contains the sendEmail function
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();
header('Content-Type: application/json; charset=utf-8');

// 1. Admin Auth Check
if(!isset($_SESSION["admin_id"])){
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Unauthorized access"]);
    exit;
}

// 2. Input Validation
$data = json_decode(file_get_contents("php://input"), true);
$transaction_id = $data["id"] ?? null;
$action = $data["action"] ?? null; // 'complete' or 'cancel'
// Was `?? "No specific reason provided."`, which only catches NULL. admin.js
// sends reason.trim(), so an empty prompt sent "" - falsy, but not null, so
// the empty string went straight into a customer-facing email.
$reason = trim((string)($data["reason"] ?? "")) ?: "No specific reason provided.";

if (!$transaction_id || !in_array($action, ['complete', 'cancel'])) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Invalid action or transaction ID."]);
    exit;
}

$pdo = null;

try {
    $pdo = getPDO();

    // Role gate: this endpoint credits member wallets.
    // Only isset($_SESSION['admin_id']) was checked before, so a `support`
    // admin had exactly the same power here as the owner. Read from the DB,
    // fails closed. See mvcRequireAdminRole() in api/utilities/security.php.
    mvcRequireAdminRole($pdo, MVC_ROLE_OPERATOR);
    $pdo->beginTransaction();

    // 3. Lock the transaction row.
    //
    // The old query joined users and wallets purely to read w.balance for a
    // PHP-side add. That is a read-modify-write with no lock: two approvals
    // for the same member each read the same starting balance, and the second
    // write silently erased the first. Nothing stopped the SAME deposit being
    // completed twice either.
    //
    // Lock the transaction row alone - FOR UPDATE across the 3-way join would
    // have locked users and wallets too, for no benefit.
    $stmt = $pdo->prepare("
        SELECT id, user_id, amount, reference, method
        FROM transactions
        WHERE id = :id AND type = 'deposit' AND status = 'pending'
        FOR UPDATE
    ");
    $stmt->execute([':id' => $transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(["status"=>"error","message"=>"Pending deposit not found or already processed."]);
        exit;
    }

    $userId    = (int)$transaction['user_id'];
    $amount    = (float)$transaction['amount'];
    $reference = $transaction['reference'];

    $userStmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = :id");
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Member', 'email' => ''];
    $userName  = $user['full_name'];
    $userEmail = $user['email'];

    $newStatus = $action === 'complete' ? 'completed' : 'failed';

    // 4A. Compare-and-set. The WHERE re-asserts 'pending' inside the lock, so
    // a second concurrent request gets rowCount() 0 and bails rather than
    // crediting the wallet twice.
    $stmt = $pdo->prepare("UPDATE transactions SET status = :status WHERE id = :id AND status = 'pending'");
    $stmt->execute([':status' => $newStatus, ':id' => $transaction_id]);

    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(["status"=>"error","message"=>"That deposit was already processed."]);
        exit;
    }

    if ($action === 'complete') {
        // 4B. Relative, not read-modify-write. MySQL evaluates this against
        // the committed row under the row lock, so concurrent credits compose
        // instead of clobbering each other.
        $stmt = $pdo->prepare("
            UPDATE wallets
            SET balance = balance + :amount,
                total_deposited = total_deposited + :amount
            WHERE user_id = :user_id
        ");
        $stmt->execute([':amount' => $amount, ':user_id' => $userId]);

        $emailTemplate = 'deposit_confirmed';
        $message = "Deposit of \$" . number_format($amount, 2) . " has been completed and credited to the member's wallet.";
    } else {
        $emailTemplate = 'deposit_cancelled';
        $message = "Deposit request for \$" . number_format($amount, 2) . " has been cancelled.";
    }

    // 5. Commit BEFORE sending mail. sendEmail() used to run inside the
    // transaction, so a slow or hanging SMTP handshake held the wallet row
    // lock for its entire duration, blocking every other balance write for
    // that member.
    $pdo->commit();

    $emailPlaceholders = [
        'user_name' => $userName,
        'amount' => number_format($amount, 2),
        'reference' => $reference,
    ];
    if ($action === 'cancel') {
        // sendEmail() escapes this itself - see api/backend/email.php.
        $emailPlaceholders['reason'] = $reason;
    }

    $emailSuccess = $userEmail
        ? sendEmail(['to' => $userEmail, 'template' => $emailTemplate, 'variables' => $emailPlaceholders])
        : false;

    // 6. Final Response
    // Checks if the email failed to send (handles both boolean false and error array)
    $failed = ($emailSuccess === false) || (is_array($emailSuccess) && !$emailSuccess['success']);

    if ($failed) {
        $errorDetails = is_array($emailSuccess) ? ($emailSuccess['error'] ?? 'Unknown') : 'Returned boolean false';
        error_log("CRITICAL: Failed to send $action email to user $userId. Mailer Error: " . $errorDetails);
        $message .= " (Warning: Email notification failed to send.)";
    }

    echo json_encode(["status"=>"success", "message"=>$message]);

} catch(Exception $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Deposit Process Error: " . $e->getMessage());
    echo json_encode(["status"=>"error", "message"=>"Transaction failed: A server error occurred."]); 
}
?>