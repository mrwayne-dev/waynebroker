<?php
// ============================================================
// FILE: /api/admin/settings.php
// PURPOSE: Read and update platform settings from the admin panel
// ACTIONS: get, update
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
require_once __DIR__ . '/../utilities/settings.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST ?: $_GET;
$action = trim($input['action'] ?? '');

function settingsOut($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// The largest figure any of these may be set to. Not a business rule so much
// as a typo guard: a stray zero turning 50 into 50000 would refuse every
// withdrawal on the platform, and the member-facing message would tell them
// their balance was too small rather than that the site was misconfigured.
const MVC_SETTING_CEILING = 1000000.0;

// Every money setting this screen owns. 0 always means "no limit", which is
// how they all ship - see the two settings migrations.
const MVC_MONEY_SETTINGS = [
    'withdrawal_min_amount' => 'Minimum withdrawal',
    'withdrawal_max_amount' => 'Maximum withdrawal',
    'deposit_min_amount'    => 'Minimum deposit',
    'deposit_max_amount'    => 'Maximum deposit',
];

// --------------------- ACTION: get ---------------------
// Readable by any admin so the page renders for everyone; only the write
// below is restricted.
if ($action === 'get') {
    try {
        mvcRequireAdminRole($pdo, MVC_ROLE_ALL);
        $out = ['ceiling' => MVC_SETTING_CEILING];
        foreach (array_keys(MVC_MONEY_SETTINGS) as $key) {
            $out[$key] = mvcSettingGetFloat($pdo, $key, 0.0);
        }
        settingsOut('success', 'Settings loaded.', $out);
    } catch (Exception $e) {
        error_log('admin settings get: ' . $e->getMessage());
        settingsOut('error', 'Failed to load settings.');
    }
}

// --------------------- ACTION: update ---------------------
if ($action === 'update') {
    // Role gate: this changes the rules money moves under, so it sits with
    // the owner rather than with anyone who can action a single transaction.
    mvcRequireAdminRole($pdo, MVC_ROLE_OWNER);

    // Parse and range-check everything BEFORE writing anything, so a bad
    // value in one field cannot leave the others half-applied.
    $values = [];
    foreach (MVC_MONEY_SETTINGS as $key => $label) {
        // A field the client did not send keeps its stored value. This is
        // what lets the page save one section without clearing the other.
        if (!array_key_exists($key, $input)) {
            $values[$key] = mvcSettingGetFloat($pdo, $key, 0.0);
            continue;
        }

        $raw = $input[$key];
        // Presence is checked before casting. (float) '' is 0.0, so a blank
        // field would silently read as "no limit" rather than as a mistake.
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            settingsOut('error', $label . ': enter an amount as a number.');
        }
        $num = (float) $raw;
        if ($num < 0) {
            settingsOut('error', $label . ' cannot be negative.');
        }
        if ($num > MVC_SETTING_CEILING) {
            settingsOut('error', $label . ': that looks like a typo. Enter an amount up to $' . number_format(MVC_SETTING_CEILING, 2) . '.');
        }
        $values[$key] = $num;
    }

    // Cross-field check. A minimum above a maximum would refuse every
    // transaction of that kind while each field looked individually valid,
    // and the member-facing message would name a range nothing can satisfy.
    foreach ([['withdrawal', 'withdrawal_min_amount', 'withdrawal_max_amount'],
              ['deposit',    'deposit_min_amount',    'deposit_max_amount']] as [$what, $minKey, $maxKey]) {
        if ($values[$maxKey] > 0 && $values[$minKey] > $values[$maxKey]) {
            settingsOut('error', sprintf(
                'The minimum %s ($%s) cannot be above the maximum ($%s). Nothing would be allowed through.',
                $what,
                number_format($values[$minKey], 2),
                number_format($values[$maxKey], 2)
            ));
        }
    }

    // Stored to 2dp so the stored value, the message the member is shown,
    // and the comparisons in api/backend/wallet.php all agree.
    foreach ($values as $key => $num) {
        if (!mvcSettingSet($pdo, $key, number_format($num, 2, '.', ''))) {
            settingsOut('error', 'Could not save the settings. Please try again.');
        }
    }

    $saved = ['ceiling' => MVC_SETTING_CEILING];
    foreach (array_keys(MVC_MONEY_SETTINGS) as $key) {
        $saved[$key] = round($values[$key], 2);
    }
    settingsOut('success', 'Settings saved.', $saved);
}

http_response_code(400);
settingsOut('error', 'Invalid action.');
