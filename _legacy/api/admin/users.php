<?php
// D:\mrwayne\web_dev\maveren\api\admin\users.php

require_once __DIR__ . '/../../api/utilities/security.php';
// Ensure only authenticated admins can access this script
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();
// Use the session check from the more robust admin files
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../../config/database.php';
require_once '../backend/email.php'; 

$pdo = getPDO();

// Role gate: this endpoint edits member records.
// Only isset($_SESSION['admin_id']) was checked before, so a `support`
// admin had exactly the same power here as the owner. Read from the DB,
// fails closed. See mvcRequireAdminRole() in api/utilities/security.php.
mvcRequireAdminRole($pdo, MVC_ROLE_OPERATOR);

// --- Helper Functions ---

/**
 * Sends a JSON response and terminates the script.
 */
function sendResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Executes a prepared statement and returns results or handles errors.
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        // Use execute with params array for safer binding
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Log error and return false
        error_log("Database Error in users.php: " . $e->getMessage());
        return false;
    }
}

/**
 * Handles GET requests to fetch metrics and user list.
 */
function handleGetRequest($pdo) {
    
    if (!$pdo) {
        sendResponse(['status' => 'error', 'message' => 'Internal Server Error: Database unavailable.'], 500);
    }
    
    // 1. Fetch Metrics
    $metrics = fetchUserMetrics($pdo);
    
    // 2. Fetch Paginated User List
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 10;
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';

    // Cast inputs to integers immediately for safety
    $page = (int)$page;
    $perPage = (int)$perPage;

    $userList = fetchUserList($pdo, $page, $perPage, $filter, $search);

    if ($userList === false) {
        sendResponse(['status' => 'error', 'message' => 'Database error when fetching users. Check logs for SQL failure.'], 500);
    }
    
    sendResponse(['status' => 'success', 'data' => [
        'metrics' => $metrics,
        'users' => $userList['users'],
        'total_pages' => $userList['total_pages'],
        'current_page' => $page,
        'per_page' => $perPage
    ]]);
}

/**
 * Retrieves core metrics for the user dashboard cards.
 */
function fetchUserMetrics($pdo) {
    $metrics = [
        'total_users' => 0,
        'active_users' => 0,
        'admin_count' => 0,
        'new_today' => 0
    ];

    try {
        $metrics['total_users'] = $pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();
        $metrics['admin_count'] = $pdo->query("SELECT COUNT(id) FROM users WHERE role = 'admin'")->fetchColumn();
        $metrics['active_users'] = $pdo->query("SELECT COUNT(id) FROM users WHERE status = 'active'")->fetchColumn();
        
        $today = date('Y-m-d');
        $stmt_new = $pdo->prepare("SELECT COUNT(id) FROM users WHERE DATE(created_at) = :today");
        $stmt_new->bindParam(':today', $today);
        $stmt_new->execute();
        $metrics['new_today'] = $stmt_new->fetchColumn();

    } catch (PDOException $e) {
        error_log("Metric Fetch Error: " . $e->getMessage());
    }

    return $metrics;
}

/**
 * Fetches user list with pagination, search, and filter logic.
 *
 * NOTE: This function uses a hybrid binding approach: array execute for dynamic WHERE parameters,
 * and separate bindParam for integer LIMIT/OFFSET for security and compatibility.
 */
function fetchUserList($pdo, $page, $perPage, $filter, $search) {
    $perPage = max(1, (int)$perPage);
    $page    = max(1, (int)$page);
    
    // Base query
    $sql = "FROM users WHERE 1";
    $params = [];

    // 🔎 Filter (simple & safe)
    if ($filter === 'active') {
        $sql .= " AND status = 'active'";
    } elseif ($filter === 'suspended') {
        $sql .= " AND status = 'disabled'";
    } elseif ($filter === 'admin') {
        $sql .= " AND role = 'admin'";
    } elseif ($filter === 'user') {
        $sql .= " AND role = 'user'";
    }

    // 🔍 Search (no fragile columns)
    if (!empty($search)) {
        $searchWild = "%$search%";
        $sql .= " AND (email LIKE :s 
                   OR name LIKE :s 
                   OR full_name LIKE :s 
                   OR id = :idExact)";
        $params[':s'] = $searchWild;
        $params[':idExact'] = is_numeric($search) ? (int)$search : 0;
    }

    // 1️⃣ COUNT
    $stmtCount = executeQuery($pdo, "SELECT COUNT(id) " . $sql, $params);
    if (!$stmtCount) return false;
    
    $totalUsers = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, ceil($totalUsers / $perPage));
    $offset = ($page - 1) * $perPage;

    // 2️⃣ DATA (SAFE FORMATTING IN SQL)
    $stmt = executeQuery($pdo,
        "SELECT 
            id,
            COALESCE(full_name, name) AS display_name,
            email,
            role,
            status,
            COALESCE(DATE_FORMAT(created_at,'%Y-%m-%d %H:%i'),'N/A') AS last_login
         $sql
         ORDER BY id DESC
         LIMIT $perPage OFFSET $offset",
         $params);

    if (!$stmt) return false;

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$u) {
        $u['id'] = (int)$u['id']; // ensure numeric
    }

    return ['users' => $users, 'total_pages' => $totalPages];
}


/**
 * Handles POST requests for user actions (edit, delete, send email).
 */
function handlePostRequest($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;

    if (!$userId) {
        sendResponse(['status' => 'error', 'message' => 'Invalid or missing user ID.'], 400);
    }

    switch ($action) {

        /* ---------------------- EDIT USER ---------------------- */
        case 'edit_user':
            $name   = trim($data['name'] ?? '');
            $email  = trim($data['email'] ?? '');
            $role   = $data['role'] ?? '';
            $status = $data['status'] ?? '';

            if (!$name || !$role || !$status) {
                sendResponse(['status' => 'error', 'message' => 'Required fields missing.'], 400);
            }

            if (!in_array($role, ['user', 'admin'])) {
                sendResponse(['status' => 'error', 'message' => 'Invalid role selection.'], 400);
            }

            if (!in_array($status, ['active', 'disabled'])) {
                sendResponse(['status' => 'error', 'message' => 'Invalid status value.'], 400);
            }

            $params = [
                ':name'      => $name,
                ':full_name' => $name,
                ':role'      => $role,
                ':status'    => $status,
                ':id'        => $userId
            ];

            // Email validation if provided
            if ($email) {
                $dupCheck = executeQuery($pdo, 
                    "SELECT id FROM users WHERE email = :email AND id != :id", 
                    [':email' => $email, ':id' => $userId]
                );
                if ($dupCheck && $dupCheck->rowCount() > 0) {
                    sendResponse(['status' => 'error', 'message' => 'Email already taken by another user.'], 409);
                }
                $params[':email'] = $email;
                $setSQL = ", email = :email";
            } else {
                $setSQL = "";
            }

            $sql = "UPDATE users 
                    SET name = :name, full_name = :full_name, role = :role, status = :status $setSQL
                    WHERE id = :id";

            if (executeQuery($pdo, $sql, $params)) {
                sendResponse(['status' => 'success', 'message' => "User updated successfully."]);
            }
            sendResponse(['status' => 'error', 'message' => 'Failed to update user.'], 500);

        
        /* ---------------------- DELETE USER ---------------------- */
        case 'delete_user':
            // Reject deleting admins here
            $roleCheck = executeQuery($pdo, "SELECT role FROM users WHERE id = :id", [':id' => $userId]);
            $user = $roleCheck ? $roleCheck->fetch(PDO::FETCH_ASSOC) : null;

            if ($user && $user['role'] === 'admin') {
                sendResponse(['status' => 'error', 'message' => 'Cannot delete Admin accounts from this page.'], 403);
            }

            if (executeQuery($pdo, "DELETE FROM users WHERE id = :id", [':id' => $userId])) {
                sendResponse(['status' => 'success', 'message' => 'User deleted successfully.']);
            }

            sendResponse(['status' => 'error', 'message' => 'Delete failed.'], 500);


        /* ---------------------- SEND EMAIL ---------------------- */
        case 'send_email':
            $subject = trim($data['subject'] ?? '');
            $body    = trim($data['body'] ?? '');

            if (!$subject || !$body) {
                sendResponse(['status' => 'error', 'message' => 'Email subject and body are required.'], 400);
            }

            $stmt = executeQuery($pdo,
                "SELECT email, COALESCE(full_name,name) AS user_name 
                 FROM users WHERE id = :id", 
                [':id' => $userId]
            );

            $user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$user) {
                sendResponse(['status' => 'error', 'message' => 'User not found.'], 404);
            }

            $sent = sendEmail([
                'to'       => $user['email'],
                'template' => 'admin_broadcast',
                'subject'  => $subject,
                'variables'=> [
                    'user_name' => $user['user_name'],
                    'subject_line' => $subject,
                    'message_body' => nl2br(htmlspecialchars($body)),
                ]
            ]);

            if ($sent['success'] ?? false) {
                sendResponse(['status' => 'success', 'message' => 'Email queued successfully.']);
            }

            sendResponse(['status' => 'error', 'message' => 'Failed to send email.'], 500);


        /* ---------------------- INVALID ACTION ---------------------- */
        default:
            sendResponse(['status' => 'error', 'message' => 'Invalid action.'], 400);
    }
}



// --- Main Request Handler ---

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($pdo);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($pdo);
} else {
    sendResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

?>