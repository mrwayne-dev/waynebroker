<?php
// ===============================================
// FILE: /api/admin/kyc_file.php
// PURPOSE: Stream one KYC document to an authenticated admin.
//
// uploads/kyc/ is denied by its own .htaccess, so this is the ONLY route to
// these files. Everything below exists to make sure it stays a narrow one:
// an admin session is required, the file must be referenced by a real
// kyc_submissions row, and the resolved path must sit inside the KYC
// directory. A passport leaks if any one of those is skipped.
//
// GET /api/admin/kyc_file.php?submission=12&field=doc_front
// ===============================================

require_once __DIR__ . '/../utilities/security.php';
mvcSessionStart();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/roles.php';

function kycFileDeny(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    error_log('kyc_file.php: DB connect failed: ' . $e->getMessage());
    kycFileDeny(500, 'Server error.');
}

// Any admin may review; there is no read-only admin tier below support_admin.
mvcRequireAdminRole($pdo, [ROLE_SUPPORT_ADMIN, ROLE_SUPER_ADMIN]);

$submissionId = (int) ($_GET['submission'] ?? 0);
$field        = (string) ($_GET['field'] ?? '');

// Whitelist, not interpolation: $field names a COLUMN.
$ALLOWED = ['doc_front', 'doc_back', 'selfie'];
if ($submissionId <= 0 || !in_array($field, $ALLOWED, true)) {
    kycFileDeny(400, 'Bad request.');
}

$stmt = $pdo->prepare("SELECT `$field` AS path, user_id FROM kyc_submissions WHERE id = ?");
$stmt->execute([$submissionId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['path']) {
    kycFileDeny(404, 'Not found.');
}

$root = realpath(__DIR__ . '/../../uploads/kyc');
$full = realpath(__DIR__ . '/../../' . $row['path']);

// realpath() resolves ../ and symlinks, so this comparison is what actually
// contains the read to the KYC directory. The separator on the prefix stops
// /uploads/kyc-elsewhere from matching /uploads/kyc.
if ($root === false || $full === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
    error_log("kyc_file.php: path escaped the KYC root: {$row['path']}");
    kycFileDeny(404, 'Not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($full) ?: 'application/octet-stream';
// Only ever serve what we accept on the way in. A stored file with an
// unexpected type is a sign something went wrong, not something to render.
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'], true)) {
    kycFileDeny(415, 'Unsupported file.');
}

mvcSecurityLog('kyc_document_viewed', [
    'admin_id'   => (int) ($_SESSION['admin_id'] ?? 0),
    'submission' => $submissionId,
    'field'      => $field,
    'subject'    => (int) $row['user_id'],
    'ip'         => mvcClientIp(),
]);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($full));
// inline so the reviewer sees it in the page; the filename is ours, never the
// member's original, which could carry a misleading extension.
header('Content-Disposition: inline; filename="kyc-' . $submissionId . '-' . $field . '"');
// Identity documents must not be written to a shared or disk cache.
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($full);
