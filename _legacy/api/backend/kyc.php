<?php
// ===============================================
// FILE: /api/backend/kyc.php
// PURPOSE: Member-side identity verification.
//
// ACTIONS
//   status  (GET or POST)  current state + the latest submission's summary
//   submit  (POST, multipart) create a submission and flip the member to pending
//
// Uploads follow the same contract as api/backend/upload_avatar.php - true MIME
// sniffing via finfo, never the client-supplied name or type - but write to
// uploads/kyc/{user_id}/, which is not web-reachable. These are identity
// documents; an avatar can be served by URL, a passport cannot.
// ===============================================

require_once __DIR__ . '/../utilities/security.php';
mvcSessionStart();

// CSRF: safe methods return immediately, anything else must present the session
// token. assets/js/api.js sends it on every POST, including multipart.
mvcCsrfEnforce();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/email.php';        // sendEmail() + mvcEmailSent()

function kycRespond(string $status, string $message, array $data = []): void {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? 'status';

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    error_log('kyc.php: DB connect failed: ' . $e->getMessage());
    kycRespond('error', 'Server error. Please try again.');
}

// ---------------------------------------------------------------
// Shared: the member's current state + latest submission
// ---------------------------------------------------------------
function kycCurrent(PDO $pdo, int $user_id): array {
    $st = $pdo->prepare("SELECT kyc_status FROM users WHERE id = ?");
    $st->execute([$user_id]);
    $status = (string) ($st->fetchColumn() ?: 'none');

    $st = $pdo->prepare(
        "SELECT id, id_type, id_number, full_name, date_of_birth, country,
                status, reject_reason, created_at, reviewed_at
           FROM kyc_submissions
          WHERE user_id = ?
          ORDER BY created_at DESC, id DESC
          LIMIT 1"
    );
    $st->execute([$user_id]);
    $latest = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($latest) {
        // Never ship the stored paths to the client. Knowing the filename is of
        // no use to the member and of considerable use to anyone else.
        $latest['id_number'] = kycMaskIdNumber((string) $latest['id_number']);
    }

    return [
        'kyc_status' => $status,
        // A rejected member must be able to submit again; an approved or
        // pending one must not queue a second review.
        'can_submit' => in_array($status, ['none', 'rejected'], true),
        'latest'     => $latest,
    ];
}

// Show only the last 4 characters, so the member can confirm which document
// they sent without the full number sitting in a JSON response.
function kycMaskIdNumber(string $n): string {
    $n = trim($n);
    return strlen($n) <= 4 ? str_repeat('*', strlen($n)) : str_repeat('*', strlen($n) - 4) . substr($n, -4);
}

if ($action === 'status') {
    kycRespond('success', 'OK', kycCurrent($pdo, $user_id));
}

if ($action !== 'submit') {
    kycRespond('error', 'Unknown action.');
}

// ---------------------------------------------------------------
// submit
// ---------------------------------------------------------------

// Throttled: each submission writes three files to disk, so an unthrottled
// endpoint is a way to fill the volume as much as it is a way to spam review.
mvcEnforceRateLimit($pdo, 'kyc_submit', (string) $user_id);
mvcRecordAttempt($pdo, 'kyc_submit', mvcClientIp());
mvcRecordAttempt($pdo, 'kyc_submit', (string) $user_id);

$state = kycCurrent($pdo, $user_id);
if (!$state['can_submit']) {
    kycRespond('error', $state['kyc_status'] === 'approved'
        ? 'Your identity is already verified.'
        : 'You already have a submission under review.');
}

// An upload larger than post_max_size arrives with $_POST AND $_FILES both
// empty - PHP discards the body before this script runs - so the generic
// "no file" message is what an oversized upload would otherwise show.
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    kycRespond('error', 'Those files are larger than the server accepts. Please keep each one under 10 MB.');
}

$idType = strtolower(trim((string) ($_POST['id_type'] ?? '')));
$ALLOWED_TYPES = ['passport', 'national_id', 'drivers_license'];
if (!in_array($idType, $ALLOWED_TYPES, true)) {
    kycRespond('error', 'Please choose a valid document type.');
}

$idNumber = trim((string) ($_POST['id_number'] ?? ''));
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$dob      = trim((string) ($_POST['date_of_birth'] ?? ''));
$country  = trim((string) ($_POST['country'] ?? ''));

if ($idNumber === '' || strlen($idNumber) > 80)  kycRespond('error', 'Please enter the document number.');
if ($fullName === '' || strlen($fullName) > 150) kycRespond('error', 'Please enter your full name as printed on the document.');
if ($country  === '' || strlen($country)  > 80)  kycRespond('error', 'Please select the country that issued the document.');

// Validate the date rather than trusting <input type="date">: the field is
// trivially bypassed, and an unparseable value would reach a NOT NULL DATE.
$dobDate = DateTime::createFromFormat('Y-m-d', $dob);
if (!$dobDate || $dobDate->format('Y-m-d') !== $dob) {
    kycRespond('error', 'Please enter a valid date of birth.');
}
$age = (new DateTime('today'))->diff($dobDate)->y;
if ($age < 18)  kycRespond('error', 'You must be at least 18 years old to verify your account.');
if ($age > 120) kycRespond('error', 'Please enter a valid date of birth.');

// A passport is a single page. The other two have a reverse that carries the
// address and issue data, so it is required for them and only for them.
$needsBack = in_array($idType, ['national_id', 'drivers_license'], true);

const KYC_MAX_BYTES = 10 * 1024 * 1024;
// PDF is accepted for the document scans (many issuers hand out a PDF) but not
// for the selfie, which must be a photograph.
const KYC_DOC_MIME    = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
const KYC_SELFIE_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

/**
 * Validate one uploaded file and return [tmp_path, extension].
 * Returns null when the field is absent and optional.
 */
function kycValidateUpload(string $field, array $allowedMime, bool $required, string $label): ?array {
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) kycRespond('error', "Please upload $label.");
        return null;
    }
    $f = $_FILES[$field];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            kycRespond('error', ucfirst($label) . ' must be 10 MB or smaller.');
        }
        kycRespond('error', "Could not read $label. Please try again.");
    }
    if ($f['size'] > KYC_MAX_BYTES) {
        kycRespond('error', ucfirst($label) . ' must be 10 MB or smaller.');
    }
    // is_uploaded_file before anything touches the path: without it a crafted
    // tmp_name could point finfo at an arbitrary file on disk.
    if (!is_uploaded_file($f['tmp_name'])) {
        kycRespond('error', "Could not read $label. Please try again.");
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        $human = in_array('pdf', $allowedMime, true) ? 'PNG, JPG, WEBP or PDF' : 'PNG, JPG or WEBP';
        kycRespond('error', ucfirst($label) . " must be a $human file.");
    }
    return [$f['tmp_name'], $allowedMime[$mime]];
}

$front  = kycValidateUpload('doc_front', KYC_DOC_MIME,    true,       'the front of your document');
$back   = kycValidateUpload('doc_back',  KYC_DOC_MIME,    $needsBack, 'the back of your document');
$selfie = kycValidateUpload('selfie',    KYC_SELFIE_MIME, true,       'a selfie holding your document');

$dir = __DIR__ . '/../../uploads/kyc/' . $user_id;
if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
    error_log("kyc.php: could not create $dir");
    kycRespond('error', 'Server error while saving your documents.');
}

/**
 * Move one validated upload into place under an unguessable name.
 *
 * The filename carries random bytes rather than a predictable
 * {user}_{field}.{ext}: the directory is denied by .htaccess, but a
 * misconfigured host that ignores it should still not serve a passport to
 * anyone who can guess a URL.
 */
function kycStore(array $file, string $dir, int $user_id, string $field): string {
    [$tmp, $ext] = $file;
    $name = sprintf('%s_%s_%s.%s', $field, date('Ymd'), bin2hex(random_bytes(8)), $ext);
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        error_log("kyc.php: move_uploaded_file failed for $field (user $user_id)");
        kycRespond('error', 'Server error while saving your documents.');
    }
    @chmod($dir . '/' . $name, 0640);
    return 'uploads/kyc/' . $user_id . '/' . $name;
}

$frontPath  = kycStore($front,  $dir, $user_id, 'front');
$backPath   = $back ? kycStore($back, $dir, $user_id, 'back') : null;
$selfiePath = kycStore($selfie, $dir, $user_id, 'selfie');

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        "INSERT INTO kyc_submissions
            (user_id, id_type, id_number, full_name, date_of_birth, country,
             doc_front, doc_back, selfie, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    )->execute([$user_id, $idType, $idNumber, $fullName, $dob, $country,
                $frontPath, $backPath, $selfiePath]);

    // Written in the same transaction as the row above, so the denormalised
    // flag and the submission history cannot disagree.
    $pdo->prepare("UPDATE users SET kyc_status = 'pending' WHERE id = ?")->execute([$user_id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // The files are already on disk. Remove them rather than leaving orphans
    // that no row references and nothing will ever clean up.
    foreach ([$frontPath, $backPath, $selfiePath] as $p) {
        if ($p) @unlink(__DIR__ . '/../../' . $p);
    }
    error_log('kyc.php: submit failed: ' . $e->getMessage());
    kycRespond('error', 'Server error while saving your submission.');
}

mvcSecurityLog('kyc_submitted', ['user_id' => $user_id, 'id_type' => $idType, 'ip' => mvcClientIp()]);

// --- Notify the member and the admin ---
//
// Neither of these existed. Every other member action tells somebody: a
// deposit sends two emails, an investment sends two, a withdrawal sends two.
// A KYC submission sent none - so the member had no record that their
// documents arrived, and more importantly NOTHING told an admin that a
// submission was waiting. Verification gates withdrawals, so a submission
// nobody knew about is a member who cannot withdraw and cannot find out why.
// The review side was already wired: api/admin/kyc.php mails kyc_approved or
// kyc_rejected on a decision. Only the submission event was silent.
//
// Sent AFTER the commit, and failure is not surfaced to the member: the
// documents are safely stored and under review either way, so a mail problem
// must not make a successful submission look like it failed. sendEmail logs
// its own failures to logs/email.log.
$submittedAt = date('Y-m-d H:i');
$idTypeLabel = ucwords(str_replace('_', ' ', $idType));

// Read the address from the database rather than $_SESSION: the session copy
// is written at login and a member who changes their email mid-session would
// otherwise have this go to the old one.
$userEmail = (string) ($pdo->query(
    'SELECT email FROM users WHERE id = ' . (int) $user_id
)->fetchColumn() ?: '');

sendEmail([
    'to'       => $userEmail,
    'template' => 'kyc_submitted',
    'variables' => [
        'user_name'    => $fullName,
        'id_type'      => $idTypeLabel,
        'submitted_at' => $submittedAt,
    ],
]);

sendEmail([
    'to'       => ADMIN_CONTACT_EMAIL,
    'template' => 'admin_kyc_notification',
    'variables' => [
        'user_name'    => $fullName,
        'user_email'   => $userEmail,
        'id_type'      => $idTypeLabel,
        'country'      => $country,
        'submitted_at' => $submittedAt,
    ],
]);

kycRespond('success', 'Your documents were submitted and are now under review.', kycCurrent($pdo, $user_id));
