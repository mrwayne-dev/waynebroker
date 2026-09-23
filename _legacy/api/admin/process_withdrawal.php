<?php
/**
 * ============================================================
 * Maveren Capital - PROCESS WITHDRAWAL ACTION (ADMIN)
 * ============================================================
 * POST: id, action (complete|cancel), [reason]
 * Actions:
 *  - complete = approve and send money (deduction already made)
 *  - cancel = return money to user wallet + notify user
 * Emails: withdrawal_approved, withdrawal_declined
 * ============================================================
 */

require_once("../../config/database.php");
require_once("../../api/utilities/email_temps.php"); 
require_once("../backend/email.php"); // Contains the sendEmail function
require_once("../../api/utilities/helpers.php");
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();
header('Content-Type: application/json');

// Admin Auth Check - this endpoint approves/cancels withdrawals and credits wallets.
if (!isset($_SESSION["admin_id"])) {
    logSecurityEvent('unauthorized_admin_access', ['endpoint' => 'process_withdrawal', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Allow both JSON and form POST
$input = json_decode(file_get_contents("php://input"), true);

$id     = intval($input['id'] ?? ($_POST['id'] ?? 0));
$action = $input['action'] ?? ($_POST['action'] ?? '');
$reason = trim($input['reason'] ?? ($_POST['reason'] ?? ''));

if (!$id || !in_array($action, ['complete', 'cancel'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo = getPDO();

    // Role gate: this endpoint approves withdrawals and moves member funds out.
    // Only isset($_SESSION['admin_id']) was checked before, so a `support`
    // admin had exactly the same power here as the owner. Read from the DB,
    // fails closed. See mvcRequireAdminRole() in api/utilities/security.php.
    mvcRequireAdminRole($pdo, MVC_ROLE_OPERATOR);
    $pdo->beginTransaction();

    // Fetch withdrawal
    // Deliberately NOT filtered on status='pending'.
    //
    // It used to be, which meant an already-completed withdrawal came back as
    // no row at all, threw "not found or already processed", and reached the
    // admin as the catch block's generic "Could not process the withdrawal.
    // Please try again." That is the worst available answer on this screen: the
    // action had in fact succeeded and the member had already been emailed, so
    // an admin who believed it failed might reissue the payment by hand or go
    // chasing it in the payment provider. Harmless to the ledger, expensive in
    // the real world.
    //
    // Fetching regardless of status lets the two cases be told apart below.
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name, u.email 
        FROM transactions t
        JOIN users u ON u.id = t.user_id
        WHERE t.id = ? AND t.type = 'withdraw'
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $txn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$txn) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'No withdrawal found with that ID.']);
        exit;
    }

    // Already dealt with. Name the state and say no further action is needed,
    // so nobody reads this as "it failed, do it again".
    if ($txn['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        $already = $txn['status'] === 'completed'
            ? 'already been completed and the member has been notified'
            : 'already been cancelled and the funds returned to the member';
        echo json_encode([
            'status'  => 'error',
            'message' => "This withdrawal has {$already}. No further action is needed.",
            'data'    => ['transaction_status' => $txn['status']],
        ]);
        exit;
    }

    $userId  = $txn['user_id'];
    $amount  = floatval($txn['amount']);
    $userEmail = $txn['email'];
    $userName  = $txn['full_name'];
    $reference = $txn['reference'];

    // --------------------------
    // COMPLETE WITHDRAWAL
    // --------------------------
    if ($action === 'complete') {

        // Compare-and-set, as in process_deposit.php. Without `AND status='pending'`
        // a double-submit re-ran the whole branch and sent a second approval mail.
        $update = $pdo->prepare("UPDATE transactions SET status='completed' WHERE id=? AND status='pending'");
        $update->execute([$id]);
        if ($update->rowCount() !== 1) {
            // Reachable only as a genuine race: another admin moved this row
            // out of pending between the SELECT above and this UPDATE. The
            // sequential case is answered earlier with a specific message.
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Another administrator just processed this withdrawal.']);
            exit;
        }

        // The funds left the balance at request time and were parked in
        // wallets.pending_withdrawals. Completing has to clear that parking slot;
        // it never did, so the column only ever grew and was useless as a
        // per-user figure.
        //
        // total_withdrawn moves in the SAME statement. Nothing in the codebase
        // had ever written that column - only the INSERTs that seed a wallet row
        // in api/backend/dashboard.php mention it, and there was no UPDATE
        // anywhere - so a member who had withdrawn was still shown "Total
        // withdrawn $0.00" on the wallet page, which reads it straight off this
        // row. Deposits already do the mirror of this in process_deposit.php,
        // which is what marks this as an omission rather than a decision.
        //
        // Safe against a double-complete without any guard of its own: the
        // compare-and-set above rolls the transaction back unless it moved
        // exactly one row from pending, so this line cannot run twice for one
        // withdrawal.
        $pdo->prepare(
            "UPDATE wallets
                SET pending_withdrawals = GREATEST(pending_withdrawals - ?, 0),
                    total_withdrawn     = total_withdrawn + ?
              WHERE user_id = ?"
        )->execute([$amount, $amount, $userId]);

        // Email Notification
        sendEmail([
            'to' => $userEmail,
            'template' => 'withdrawal_approved',
            'variables' => [
                'user_name' => $userName,
                'amount'    => number_format($amount, 2),
                'method'    => formatPaymentMethod($txn['method']),   // was the raw slug: members read "wallet_address"
                'reference' => $reference
            ]
        ]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Withdrawal completed']);
        exit;
    }

// --------------------------
// CANCEL WITHDRAWAL
// --------------------------
if ($action === 'cancel') {

    // 1. Flip the status first, under a compare-and-set, so a double-submit
    //    cannot refund the same withdrawal twice.
    $update = $pdo->prepare("UPDATE transactions SET status='failed' WHERE id=? AND status='pending'");
    $update->execute([$id]);
    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Another administrator just processed this withdrawal.']);
        exit;
    }

    // 2. Return funds to wallet + release the parked amount.
    $wallet = $pdo->prepare("
        UPDATE wallets 
        SET balance = balance + ?, 
            pending_withdrawals = GREATEST(pending_withdrawals - ?, 0)
        WHERE user_id=?
    ");
    $wallet->execute([$amount, $amount, $userId]);

sendEmail([
    'to' => $userEmail,
    'template' => 'withdrawal_declined',
    'variables' => [
        'user_name' => $userName,
        'amount'    => number_format($amount, 2),
        'reference' => $reference,
        'reason'    => $reason  
    ]
]);


    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Withdrawal cancelled and funds returned']);
    exit;
}


} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('process_withdrawal.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not process the withdrawal. Please try again.']);
}
