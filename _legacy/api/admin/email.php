<?php
// ========================================
// ADMIN EMAIL BROADCAST API
// Fetches recipients from DB and sends emails using system's PHPMailer setup.
// ========================================
header('Content-Type: application/json');
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();

// --- DEPENDENCY INCLUSION ---
// 1. Core Config & Database
require_once '../../config/constants.php';  // Provides APP_NAME, APP_URL, CURRENCY
// ROLE_SUPER_ADMIN / ROLE_SUPPORT_ADMIN live in roles.php, NOT constants.php -
// the comment above used to claim otherwise and nothing included them. PHP 8
// makes an undefined constant a fatal Error rather than a warning, and the
// `?? 'super_admin'` fallback written at the use site does not apply to
// constants, so is_admin_logged_in() threw on EVERY request and this endpoint
// answered 500 for every admin, always.
require_once '../../config/roles.php';
require_once '../../config/database.php';   // Provides getPDO() for database connection
require_once '../../config/env.php';        // Ensure constants like SMTP_HOST are defined

// 2. Email Components
require_once '../utilities/email_temps.php'; // Provides getEmailTemplates()
require_once '../backend/email.php';        // Provides the system's sendEmail() function

/**
 * Checks if the current session belongs to an authorized administrator.
 * Now queries the 'admins' table and checks for admin roles.
 * @return bool True if logged in and authorized.
 */
function is_admin_logged_in() {
    // FIX 1: Check for the admin session ID. Assumes your admin login sets this key.
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    try {
        $pdo = getPDO();
        // FIX 2: Query the 'admins' table instead of 'users'
        $stmt = $pdo->prepare("SELECT role FROM admins WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        // FIX 3: Check against the roles defined in the 'admins' table schema: 
        // 'super_admin', 'manager', 'support'. 
        // Use constants for super/support and explicit string for manager.
        return $admin && (
            $admin['role'] === 'manager' ||
            ($admin['role'] === (ROLE_SUPER_ADMIN ?? 'super_admin')) ||
            ($admin['role'] === (ROLE_SUPPORT_ADMIN ?? 'support')) 
        );

    } catch (PDOException $e) {
        error_log("DB Error in is_admin_logged_in (Admins Table Check): " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches user recipients based on the selected group.
 * Uses real PDO queries.
 * @param string $group 'all', 'active', 'investors', 'donors', 'specific'
 * @param int|null $userId Required only for 'specific' group.
 * @return array Array of associative arrays, each with 'email' and 'name'.
 */
function getRecipientsByGroup($group, $userId = null) {
    $pdo = getPDO();
    $sql = '';
    $params = [];
    $baseConditions = "u.role = 'user' AND u.status = 'active'"; 

    // NOTE: 'investors' and 'donors' rely on joining tables not explicitly defined 
    // in the schema (investments/donations). Ensure these tables exist in your DB.
    
    switch ($group) {
        case 'all':
        case 'active':
            $sql = "SELECT id, COALESCE(full_name, name) as name, email FROM users u WHERE {$baseConditions}";
            break;
            
        case 'specific':
            if (empty($userId) || !is_numeric($userId)) return [];
            $sql = "SELECT id, COALESCE(full_name, name) as name, email FROM users u WHERE id = :id AND {$baseConditions}";
            $params['id'] = $userId;
            break;
            
        case 'investors':
            $sql = "SELECT DISTINCT u.id, COALESCE(u.full_name, u.name) as name, u.email 
                    FROM users u
                    JOIN investments i ON u.id = i.user_id
                    WHERE {$baseConditions}";
            break;

        case 'donors':
            $sql = "SELECT DISTINCT u.id, COALESCE(u.full_name, u.name) as name, u.email 
                    FROM users u
                    JOIN donations d ON u.id = d.user_id
                    WHERE {$baseConditions}";
            break;
            
        default:
            return [];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Remap to a standard format for the email loop
        return array_map(function($user) {
            return [
                'email' => $user['email'],
                // Use full_name if available, otherwise fallback to 'name'
                'name' => $user['name']
            ];
        }, $results);

    } catch (PDOException $e) {
        error_log("DB Query Error in getRecipientsByGroup for group '{$group}': " . $e->getMessage());
        // Return empty array on DB error
        return []; 
    }
}

// --- INITIAL VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed.']);
    exit;
}

if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in as admin.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Get and sanitize inputs
$recipient_group = $input['recipient_group'] ?? '';
$user_id = $input['user_id'] ?? null;
$subject = htmlspecialchars($input['subject'] ?? '');
// Convert newlines (\n) to <br> tags so the message body respects line breaks in the HTML email
$body = nl2br(htmlspecialchars($input['body'] ?? '')); 
$priority = $input['priority'] ?? 'normal'; // Not used in sendEmail(), but captured

if (empty($recipient_group) || empty($subject) || empty($body)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields (Recipient Group, Subject, or Message Body).']);
    exit;
}

if ($recipient_group === 'specific' && (empty($user_id) || !is_numeric($user_id))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid numeric User ID is required for a specific recipient.']);
    exit;
}

// --- CORE LOGIC ---

try {
    if (!function_exists('sendEmail')) {
        throw new Exception("System email function 'sendEmail' not found. Check inclusion path: ../backend/email.php");
    }
    
    // 1. Get recipients from the database
    $recipients = getRecipientsByGroup($recipient_group, $user_id);
    $total_recipients = count($recipients);

    if ($total_recipients === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No active user recipients found for the selected group/ID.']);
        exit;
    }

    $emails_sent_count = 0;
    
    // 2. Prepare and send email to each recipient using the system's sendEmail function
    foreach ($recipients as $recipient) {
        $user_name = $recipient['name'];
        $to_email = $recipient['email'];

        // Build parameters for the system's sendEmail function
        $send_params = [
            'to' => $to_email,
            'template' => 'admin_broadcast', // Use the pre-styled broadcast template
            'subject' => $subject,          // Use the custom subject line directly
            'variables' => [
                'user_name' => $user_name,
                // These are the placeholders defined in the email_temps.php admin_broadcast HTML:
                'subject_line' => $subject, 
                'message_body' => $body, 
            ],
        ];

        // Call the system's PHPMailer-based handler
        $send_result = sendEmail($send_params);
        
        if ($send_result['success']) {
            $emails_sent_count++;
        } else {
            // Log the specific failure for this recipient
            error_log("Failed to send broadcast to {$to_email}: " . ($send_result['error'] ?? 'Unknown PHPMailer Error'));
        }
    }

    // --- FINAL RESPONSE ---
    if ($emails_sent_count > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => "Email broadcast successfully sent to {$emails_sent_count} of {$total_recipients} recipients.",
            'recipients' => $emails_sent_count
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => "All attempts failed. Check the server email logs for PHPMailer errors.",
            'recipients' => 0
        ]);
    }

} catch (Exception $e) {
    error_log('admin/email.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error while sending email.']);
}
?>