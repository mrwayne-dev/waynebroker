<?php
ini_set('display_errors', 0);
error_reporting(0);
// ===============================================
// FILE: /api/public/contact.php
// PURPOSE: Handle a public contact-form submission.
//
// This is the first endpoint under api/public/ - everything else in api/
// requires a session. The marketing contact form is by definition
// unauthenticated, so the abuse controls here (per-address cooldown,
// honeypot, hard length caps) do the work a session would otherwise do.
//
// The form posts multipart/form-data because it carries an optional
// attachment, so this reads $_POST/$_FILES rather than a JSON body like
// the rest of the API.
// ===============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';       // must precede constants.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../utilities/helpers.php';   // logSecurityEvent()
require_once __DIR__ . '/../backend/email.php';       // sendEmail()
require_once __DIR__ . '/../../api/utilities/security.php';   // rate limiting + sessions

// Must follow the require above - hardened + proxy-aware session cookie.
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

header('Content-Type: application/json; charset=utf-8');

function contactResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    contactResponse('error', 'Invalid request method.', [], 405);
}

// ---------------------------
// Honeypot
//
// A field no human sees and no human fills. Bots that submit every input
// on the page trip it. Answer with the ordinary success shape so a scripted
// submitter learns nothing from the difference.
// ---------------------------
if (trim((string)($_POST['company_website'] ?? '')) !== '') {
    logSecurityEvent('contact_honeypot', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    contactResponse('success', 'Thanks - your message is on its way.');
}

// ---------------------------
// Validate
// ---------------------------
$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$type    = trim((string)($_POST['type'] ?? ''));
$service = trim((string)($_POST['service'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    contactResponse('error', 'Please fill in your name, email, subject and message.');
}

if (mb_strlen($name) > 150)     contactResponse('error', 'That name is too long.');
if (mb_strlen($email) > 190)    contactResponse('error', 'That email address is too long.');
if (mb_strlen($subject) > 200)  contactResponse('error', 'Please keep the subject under 200 characters.');
if (mb_strlen($message) > 5000) contactResponse('error', 'Please keep the message under 5000 characters.');
if (mb_strlen($message) < 10)   contactResponse('error', 'Please tell us a little more - at least 10 characters.');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    contactResponse('error', 'Please enter a valid email address.');
}

// Whitelists, so a crafted POST cannot write arbitrary strings into columns
// that the admin UI later renders as labels.
$allowedTypes    = ['general', 'services', 'support', 'feedback', 'partnership'];
$allowedServices = ['prospect', 'member', 'press', 'partner', 'compliance', 'other'];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'general';
}
if ($service !== '' && !in_array($service, $allowedServices, true)) {
    $service = '';
}

try {
    $pdo = getPDO();

    // Per-IP bucket on top of the existing per-address cooldown below: the
    // cooldown alone is bypassed by rotating the From address.
    mvcEnforceRateLimit($pdo, 'contact');
    mvcRecordAttempt($pdo, 'contact', mvcClientIp());

    // ---------------------------
    // Cooldown, per email address
    //
    // 60 seconds between messages from one address. Modelled on the resend
    // cooldown in api/auth/verify_email.php. Backed by idx_contact_email.
    // ---------------------------
    $stmt = $pdo->prepare("
        SELECT created_at FROM contact_messages
        WHERE email = ? ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$email]);
    $last = $stmt->fetchColumn();

    if ($last && (time() - strtotime($last)) < 60) {
        contactResponse('error', 'You just sent a message. Please wait a moment before sending another.', [], 429);
    }

    // ---------------------------
    // Optional attachment
    //
    // Validated on the true MIME type, not the filename extension, following
    // api/backend/upload_avatar.php. The stored name is random rather than
    // user-supplied: this directory is web-reachable, so a caller must not get
    // to choose the path.
    // ---------------------------
    $attachmentPath = null;
    $attachmentAbs  = null;

    if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                contactResponse('error', 'That attachment is larger than 5 MB.');
            }
            contactResponse('error', 'The attachment could not be uploaded. Please try again.');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            contactResponse('error', 'That attachment is larger than 5 MB.');
        }

        $allowedMime = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if (!isset($allowedMime[$mime])) {
            contactResponse('error', 'Attachments must be a PDF, JPG, PNG or Word document.');
        }

        $ext       = $allowedMime[$mime];
        $uploadDir = __DIR__ . '/../../uploads/contact/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $filename       = bin2hex(random_bytes(16)) . '.' . $ext;
        $attachmentAbs  = $uploadDir . $filename;
        $attachmentPath = '/uploads/contact/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $attachmentAbs)) {
            contactResponse('error', 'The attachment could not be saved. Please try again.');
        }
    }

    // ---------------------------
    // Store
    // ---------------------------
    $stmt = $pdo->prepare("
        INSERT INTO contact_messages
            (name, email, type, service, subject, message, attachment_path, ip, user_agent, created_at)
        VALUES (:name, :email, :type, :service, :subject, :message, :attachment, :ip, :ua, NOW())
    ");
    $stmt->execute([
        ':name'       => $name,
        ':email'      => $email,
        ':type'       => $type,
        ':service'    => $service !== '' ? $service : null,
        ':subject'    => $subject,
        ':message'    => $message,
        ':attachment' => $attachmentPath,
        ':ip'         => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ':ua'         => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    $messageId = (int) $pdo->lastInsertId();

    // ---------------------------
    // Notify
    //
    // Values are passed RAW. sendEmail() escapes every variable itself
    // (api/backend/email.php), so pre-escaping here would deliver &amp;amp;
    // to the recipient - a bug that already exists at several other call sites.
    //
    // message_body is on sendEmail's raw-HTML allowlist, so it is the one
    // value that must be escaped here before nl2br().
    // ---------------------------
    $typeLabels = [
        'general'     => 'General enquiry',
        'services'    => 'Services',
        'support'     => 'Support',
        'feedback'    => 'Feedback',
        'partnership' => 'Partnership',
    ];
    $typeLabel = $typeLabels[$type] ?? 'General enquiry';

    // Acknowledgement to the sender.
    sendEmail([
        'to'       => $email,
        'template' => 'contact_received',
        'variables' => [
            'user_name'    => $name,
            'subject'      => $subject,
            'message_type' => $typeLabel,
            'reference'    => 'MSG-' . str_pad((string)$messageId, 6, '0', STR_PAD_LEFT),
        ],
    ]);

    // Copy to the team, with the message body and a Reply-To the sender.
    sendEmail([
        'to'       => ADMIN_CONTACT_EMAIL,
        'template' => 'admin_contact_notification',
        'reply_to' => $email,
        'variables' => [
            'sender_name'  => $name,
            'sender_email' => $email,
            'message_type' => $typeLabel,
            'subject'      => $subject,
            'reference'    => 'MSG-' . str_pad((string)$messageId, 6, '0', STR_PAD_LEFT),
            'attachment'   => $attachmentPath ? (APP_URL . ltrim($attachmentPath, '/')) : 'None',
            // Allowlisted as raw HTML by sendEmail, so it must be escaped here.
            'message_body' => nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')),
        ],
    ]);

    contactResponse('success', 'Thanks - your message is on its way. We usually reply within two working hours.', [
        'reference' => 'MSG-' . str_pad((string)$messageId, 6, '0', STR_PAD_LEFT),
    ]);

} catch (Throwable $e) {
    error_log('contact.php: ' . $e->getMessage());
    // Don't orphan an uploaded file if the insert or mail threw.
    if (!empty($attachmentAbs) && is_file($attachmentAbs)) {
        @unlink($attachmentAbs);
    }
    contactResponse('error', 'Something went wrong sending your message. Please try again shortly.', [], 500);
}
