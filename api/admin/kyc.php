<?php
/**
 * ============================================================
 * Maveren Capital - KYC REVIEW (ADMIN)
 * ============================================================
 * POST action=list     [status=pending|approved|rejected|all] [page] [per_page]
 * POST action=detail   id
 * POST action=review   id, decision=approve|reject, [reason]
 * POST action=counts
 *
 * Approving or rejecting writes BOTH kyc_submissions.status and
 * users.kyc_status inside one transaction. They are two views of the
 * same fact - the history and the current state - and the withdrawal
 * guard in api/backend/wallet.php reads only the second, so letting
 * them drift would either strand a verified member or clear an
 * unverified one.
 *
 * Emails: kyc_approved, kyc_rejected
 * ============================================================
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utilities/email_temps.php';
require_once __DIR__ . '/../backend/email.php';
require_once __DIR__ . '/../utilities/security.php';
require_once __DIR__ . '/../../config/roles.php';

mvcSessionStart();
mvcCsrfEnforce();
header('Content-Type: application/json');

function kycAdminRespond(string $status, string $message, array $data = []): void {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    kycAdminRespond('error', 'Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kycAdminRespond('error', 'Invalid request method');
}

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    error_log('admin/kyc.php: DB connect failed: ' . $e->getMessage());
    kycAdminRespond('error', 'Server error.');
}

$adminRole = mvcRequireAdminRole($pdo, [ROLE_SUPPORT_ADMIN, ROLE_SUPER_ADMIN]);
$adminId   = (int) $_SESSION['admin_id'];

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = (string) ($input['action'] ?? $_POST['action'] ?? '');

// ------------------------------------------------------------------
// counts - drives the sidebar badge
// ------------------------------------------------------------------
if ($action === 'counts') {
    $row = $pdo->query(
        "SELECT
            SUM(status = 'pending')  AS pending,
            SUM(status = 'approved') AS approved,
            SUM(status = 'rejected') AS rejected
         FROM kyc_submissions"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    kycAdminRespond('success', 'OK', [
        'pending'  => (int) ($row['pending']  ?? 0),
        'approved' => (int) ($row['approved'] ?? 0),
        'rejected' => (int) ($row['rejected'] ?? 0),
    ]);
}

// ------------------------------------------------------------------
// list
// ------------------------------------------------------------------
if ($action === 'list') {
    $status  = (string) ($input['status'] ?? 'pending');
    $page    = max(1, (int) ($input['page'] ?? 1));
    $perPage = min(50, max(5, (int) ($input['per_page'] ?? 15)));
    $offset  = ($page - 1) * $perPage;

    $where = '';
    $args  = [];
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where = 'WHERE k.status = ?';
        $args[] = $status;
    }

    $cs = $pdo->prepare("SELECT COUNT(*) FROM kyc_submissions k $where");
    $cs->execute($args);
    $total = (int) $cs->fetchColumn();

    // LIMIT/OFFSET are interpolated as ints, not bound: emulated prepares
    // quote bound values as strings and MySQL rejects LIMIT '15'.
    $stmt = $pdo->prepare(
        "SELECT k.id, k.user_id, k.id_type, k.full_name, k.country,
                k.status, k.reject_reason, k.created_at, k.reviewed_at,
                u.email, u.name AS account_name,
                a.name AS reviewer_name
           FROM kyc_submissions k
           JOIN users  u ON u.id = k.user_id
           LEFT JOIN admins a ON a.id = k.reviewed_by
           $where
          ORDER BY (k.status = 'pending') DESC, k.created_at ASC
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($args);

    kycAdminRespond('success', 'OK', [
        'rows'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => (int) ceil($total / $perPage),
    ]);
}

// ------------------------------------------------------------------
// detail
// ------------------------------------------------------------------
if ($action === 'detail') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) kycAdminRespond('error', 'Invalid submission.');

    $stmt = $pdo->prepare(
        "SELECT k.*, u.email, u.name AS account_name, u.phone, u.country AS account_country,
                u.created_at AS member_since, a.name AS reviewer_name
           FROM kyc_submissions k
           JOIN users u ON u.id = k.user_id
           LEFT JOIN admins a ON a.id = k.reviewed_by
          WHERE k.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) kycAdminRespond('error', 'Submission not found.');

    // Replace stored paths with the streamer URL. The path itself is of no use
    // to the browser - uploads/kyc is denied - and of no use to the reviewer.
    $has = fn(string $f) => !empty($row[$f]);
    $row['doc_front_url'] = $has('doc_front') ? "/api/admin/kyc_file.php?submission=$id&field=doc_front" : null;
    $row['doc_back_url']  = $has('doc_back')  ? "/api/admin/kyc_file.php?submission=$id&field=doc_back"  : null;
    $row['selfie_url']    = $has('selfie')    ? "/api/admin/kyc_file.php?submission=$id&field=selfie"    : null;
    unset($row['doc_front'], $row['doc_back'], $row['selfie']);

    kycAdminRespond('success', 'OK', ['submission' => $row]);
}

// ------------------------------------------------------------------
// review
// ------------------------------------------------------------------
if ($action === 'review') {
    $id       = (int) ($input['id'] ?? 0);
    $decision = (string) ($input['decision'] ?? '');
    $reason   = trim((string) ($input['reason'] ?? ''));

    if ($id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        kycAdminRespond('error', 'Invalid parameters.');
    }
    // A rejection the member cannot act on is worse than no rejection: they
    // resubmit the same documents and land in the queue again.
    if ($decision === 'reject' && $reason === '') {
        kycAdminRespond('error', 'Please give a reason so the member knows what to fix.');
    }
    if (strlen($reason) > 255) {
        kycAdminRespond('error', 'Reason must be 255 characters or fewer.');
    }

    $stmt = $pdo->prepare(
        "SELECT k.id, k.user_id, k.status, u.email, u.name AS account_name
           FROM kyc_submissions k JOIN users u ON u.id = k.user_id
          WHERE k.id = ?"
    );
    $stmt->execute([$id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub) kycAdminRespond('error', 'Submission not found.');
    if ($sub['status'] !== 'pending') {
        kycAdminRespond('error', 'That submission has already been reviewed.');
    }

    $newStatus = $decision === 'approve' ? 'approved' : 'rejected';

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            "UPDATE kyc_submissions
                SET status = ?, reject_reason = ?, reviewed_by = ?, reviewed_at = NOW()
              WHERE id = ? AND status = 'pending'"
        )->execute([$newStatus, $decision === 'reject' ? $reason : null, $adminId, $id]);

        $pdo->prepare("UPDATE users SET kyc_status = ? WHERE id = ?")
            ->execute([$newStatus, (int) $sub['user_id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('admin/kyc.php: review failed: ' . $e->getMessage());
        kycAdminRespond('error', 'Server error while saving the decision.');
    }

    mvcSecurityLog('kyc_reviewed', [
        'admin_id'   => $adminId,
        'role'       => $adminRole,
        'submission' => $id,
        'subject'    => (int) $sub['user_id'],
        'decision'   => $newStatus,
        'ip'         => mvcClientIp(),
    ]);

    // Email is best-effort and deliberately OUTSIDE the transaction: the
    // decision is already committed, and a bounced notification must not roll
    // back a completed review.
    try {
        sendEmail([
            'to'       => $sub['email'],
            'template' => $decision === 'approve' ? 'kyc_approved' : 'kyc_rejected',
            'variables' => [
                'user_name' => $sub['account_name'] ?: 'there',
                'reason'    => $reason,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('admin/kyc.php: notification failed for submission ' . $id . ': ' . $e->getMessage());
    }

    kycAdminRespond('success', $decision === 'approve'
        ? 'Identity approved. The member can now withdraw.'
        : 'Submission rejected. The member has been told why and can resubmit.');
}

kycAdminRespond('error', 'Unknown action.');
