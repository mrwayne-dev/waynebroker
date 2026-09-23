<?php
// ===============================================
// FILE: /api/backend/profile.php
// PURPOSE: User profile controller for Maveren Capital
// Actions: get_profile | update_profile | change_password
// ===============================================
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env.php';       // must precede constants.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../utilities/helpers.php';
require_once __DIR__ . '/email.php';                  // sendEmail()
require_once __DIR__ . '/../../api/utilities/security.php';   // mvcHashPassword()

$pdo = getPDO();
$user_id = (int) $_SESSION['user_id'];

// --- Parse request (JSON body, POST, or GET) ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$body = [];
$raw = @file_get_contents('php://input');
if ($raw) {
    $json = @json_decode($raw, true);
    if (is_array($json)) {
        $body = $json;
        if (!$action && !empty($json['action'])) $action = $json['action'];
    }
}
$action = $action ? trim((string) $action) : null;

function jsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

function field($body, $key) {
    return isset($body[$key]) ? trim((string) $body[$key]) : '';
}

try {
    switch ($action) {

        // -------------------------------------------------------
        // GET PROFILE
        // -------------------------------------------------------
        case 'get_profile': {
            $stmt = $pdo->prepare("SELECT name, first_name, last_name, full_name, email,
                                          phone, country, location, address, profile_picture
                                   FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u) jsonResponse('error', 'User not found.');

            jsonResponse('success', 'Profile loaded.', [
                'full_name'       => $u['full_name'] ?: $u['name'],
                // NULL -> '' for every optional field. profile.js assigns these
                // straight onto .value, so an unset column must arrive as an
                // empty string for the field to render genuinely blank rather
                // than showing a value the member never entered.
                'first_name'      => $u['first_name'] ?? '',
                'last_name'       => $u['last_name'] ?? '',
                'email'           => $u['email'],
                'phone'           => $u['phone'] ?? '',
                'country'         => $u['country'] ?? '',
                'location'        => $u['location'] ?? '',
                'address'         => $u['address'] ?? '',
                'profile_picture' => $u['profile_picture'] ?: '/assets/images/avatar/default.png',
            ]);
            break;
        }

        // -------------------------------------------------------
        // UPDATE PROFILE (name, email, phone, country, address)
        // -------------------------------------------------------
        case 'update_profile': {
            $full_name = field($body, 'full_name');
            $email     = field($body, 'email');
            $phone     = field($body, 'phone');
            $country   = field($body, 'country');
            $location  = field($body, 'location');
            $address   = field($body, 'address');

            if ($full_name === '') jsonResponse('error', 'Full name is required.');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse('error', 'A valid email address is required.');
            }

            // Email uniqueness (exclude self)
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetch()) jsonResponse('error', 'That email is already in use.');

            // Read the current address BEFORE the update so a change can be
            // reported to the address losing access as well as the new one.
            $prev = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $prev->execute([$user_id]);
            $oldEmail = (string) $prev->fetchColumn();

            // `?: null` throughout: a cleared field round-trips to SQL NULL
            // rather than '', so it comes back as '' from get_profile and the
            // input renders blank. Keep this convention for any new column.
            $upd = $pdo->prepare("UPDATE users
                                  SET full_name = ?, email = ?, phone = ?, country = ?,
                                      location = ?, address = ?
                                  WHERE id = ?");
            $upd->execute([
                $full_name, $email,
                $phone ?: null, $country ?: null, $location ?: null, $address ?: null,
                $user_id,
            ]);

            // Keep session display name + email fresh
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;

            // Changing the account email was completely silent before, which is
            // the cleanest account takeover in the system: anyone with a session
            // could move the address and the owner would never know. Both
            // addresses are told, so the old one still gets a warning.
            if (strcasecmp($oldEmail, $email) !== 0) {
                $vars = [
                    'user_name'   => $full_name,
                    'old_email'   => $oldEmail,
                    'new_email'   => $email,
                    'change_time' => date('Y-m-d H:i:s'),
                ];
                foreach (array_unique([$oldEmail, $email]) as $notify) {
                    if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
                        sendEmail([
                            'to' => $notify,
                            'template' => 'account_email_changed',
                            'variables' => $vars,
                        ]);
                    }
                }
            }

            jsonResponse('success', 'Profile updated successfully.', [
                'full_name' => $full_name,
                'email'     => $email,
                'phone'     => $phone,
                'country'   => $country,
                'address'   => $address,
            ]);
            break;
        }

        // -------------------------------------------------------
        // CHANGE PASSWORD
        // -------------------------------------------------------
        case 'change_password': {
            $current = field($body, 'current_password');
            $new     = field($body, 'new_password');
            $confirm = field($body, 'confirm_password');

            if ($current === '' || $new === '' || $confirm === '') {
                jsonResponse('error', 'All password fields are required.');
            }
            if (strlen($new) < 8) jsonResponse('error', 'New password must be at least 8 characters.');
            if ($new !== $confirm) jsonResponse('error', 'New password and confirmation do not match.');

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($current, $row['password'])) {
                jsonResponse('error', 'Your current password is incorrect.');
            }

            $hash = mvcHashPassword($new);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);

            // Silent before, even though the same outcome via the forgot-password
            // route already sent password_reset_success. Inconsistent, and a
            // takeover blind spot.
            $who = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $who->execute([$user_id]);
            $me = $who->fetch(PDO::FETCH_ASSOC) ?: [];

            if (!empty($me['email'])) {
                sendEmail([
                    'to' => $me['email'],
                    'template' => 'account_password_changed',
                    'variables' => [
                        'user_name'   => $me['full_name'] ?? 'there',
                        'user_email'  => $me['email'],
                        'change_time' => date('Y-m-d H:i:s'),
                        'ip'          => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    ],
                ]);
            }

            jsonResponse('success', 'Password updated successfully.');
            break;
        }

        default:
            jsonResponse('error', 'Unknown or missing action.');
    }
} catch (Throwable $e) {
    error_log('profile.php: ' . $e->getMessage());
    jsonResponse('error', 'Server error. Please try again.');
}
