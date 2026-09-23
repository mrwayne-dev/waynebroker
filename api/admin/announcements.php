<?php
// ============================================================
// FILE: /api/admin/announcements.php
// PURPOSE: Admin CRUD for member-facing announcements
// ACTIONS: get_list, add, edit, delete, toggle
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

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getPDO();

    // Role gate: this endpoint publishes messages to all members.
    // Only isset($_SESSION['admin_id']) was checked before, so a `support`
    // admin had exactly the same power here as the owner. Read from the DB,
    // fails closed. See mvcRequireAdminRole() in api/utilities/security.php.
    mvcRequireAdminRole($pdo, MVC_ROLE_OPERATOR);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST ?: $_GET;
$action = trim($input['action'] ?? '');

function jsonOut($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// Ensure table exists (idempotent)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        status ENUM('published', 'draft') NOT NULL DEFAULT 'published',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log('announcements table init: ' . $e->getMessage());
}

// --------------------- ACTION: get_list ---------------------
if ($action === 'get_list') {
    try {
        $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
        jsonOut('success', 'Announcements loaded.', ['announcements' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        error_log('admin announcements get_list: ' . $e->getMessage());
        jsonOut('error', 'Failed to load announcements.');
    }
}

// --------------------- ACTION: add ---------------------
if ($action === 'add') {
    $title    = trim((string)($input['title'] ?? ''));
    $body     = trim((string)($input['body'] ?? ''));
    $category = trim((string)($input['category'] ?? 'general'));
    $status   = $input['status'] ?? 'published';

    if ($title === '') jsonOut('error', 'Title is required.');
    if ($body === '')  jsonOut('error', 'Body is required.');
    if (!in_array($status, ['published', 'draft'], true)) jsonOut('error', 'Invalid status.');

    try {
        $pdo->prepare("INSERT INTO announcements (title, body, category, status) VALUES (?, ?, ?, ?)")
            ->execute([$title, $body, $category, $status]);
        jsonOut('success', 'Announcement created.', ['id' => (int) $pdo->lastInsertId()]);
    } catch (Exception $e) {
        error_log('admin announcements add: ' . $e->getMessage());
        jsonOut('error', 'Failed to create announcement.');
    }
}

// --------------------- ACTION: edit ---------------------
if ($action === 'edit') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) jsonOut('error', 'Invalid announcement id.');

    $title    = trim((string)($input['title'] ?? ''));
    $body     = trim((string)($input['body'] ?? ''));
    $category = trim((string)($input['category'] ?? 'general'));
    $status   = $input['status'] ?? 'published';

    if ($title === '') jsonOut('error', 'Title is required.');
    if ($body === '')  jsonOut('error', 'Body is required.');
    if (!in_array($status, ['published', 'draft'], true)) jsonOut('error', 'Invalid status.');

    try {
        // Existence is checked separately from the write. rowCount() is the
        // number of rows CHANGED, so re-saving an announcement without editing
        // any field returns 0 and the old code reported that as a failure -
        // the admin saw an error for a save that was perfectly valid, and had
        // no way to tell it apart from a genuinely missing record.
        $exists = $pdo->prepare("SELECT 1 FROM announcements WHERE id = ?");
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) jsonOut('error', 'Announcement not found.');

        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, body = ?, category = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $body, $category, $status, $id]);
        jsonOut('success', 'Announcement updated.', ['id' => $id]);
    } catch (Exception $e) {
        error_log('admin announcements edit: ' . $e->getMessage());
        jsonOut('error', 'Failed to update announcement.');
    }
}

// --------------------- ACTION: delete ---------------------
if ($action === 'delete') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) jsonOut('error', 'Invalid announcement id.');

    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) jsonOut('error', 'Announcement not found.');
        jsonOut('success', 'Announcement deleted.', ['id' => $id]);
    } catch (Exception $e) {
        error_log('admin announcements delete: ' . $e->getMessage());
        jsonOut('error', 'Failed to delete announcement.');
    }
}

http_response_code(400);
jsonOut('error', 'Invalid action.');
