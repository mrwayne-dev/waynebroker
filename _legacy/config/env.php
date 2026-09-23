<?php
// ============================================================
// ENVIRONMENT CONFIGURATION - Maveren Capital
//
// This file no longer stores any secrets. It's a thin loader
// that reads .env at the project root and exposes those values
// as PHP constants (the rest of the codebase still uses
// DB_USER, DB_PASS, SMTP_*, NOWPAY_*, etc.).
//
// All credentials live in .env, which must be gitignored.
// ============================================================

// --- Locate and parse .env ----------------------------------------------
$envPath = __DIR__ . '/../.env';

if (!is_file($envPath) || !is_readable($envPath)) {
    http_response_code(500);
    error_log('config/env.php: missing or unreadable .env at ' . $envPath);
    exit('Server misconfigured: environment file not found.');
}

$envValues = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $trim = trim($line);
    if ($trim === '' || $trim[0] === '#' || strpos($trim, '=') === false) continue;
    [$key, $value] = array_map('trim', explode('=', $trim, 2));
    if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
        $value = trim($value, "\"'");
    }
    $envValues[$key] = $value;
}

$envv = static function (string $key, $fallback = null) use ($envValues) {
    return $envValues[$key] ?? $fallback;
};

$require = static function (string $key) use ($envValues) {
    if (!array_key_exists($key, $envValues) || $envValues[$key] === '') {
        http_response_code(500);
        error_log("config/env.php: required .env key missing: {$key}");
        exit("Server misconfigured: {$key} not set in environment.");
    }
    return $envValues[$key];
};

// --- Environment flag ---------------------------------------------------
$env = $_SERVER['MVC_ENV'] ?? ($envValues['APP_ENV'] ?? 'production');
define('ENV', $env);
define('APP_ENV', (in_array($env, ['dev', 'development', 'local'], true) ? 'local' : 'production'));

// --- Base URL (single source of truth: .env, prod fallback) -------------
// Drives NOWPayments callback/redirect URLs and email links. Defined here
// (not in constants.php) so the .env value always wins regardless of the
// order in which callers include constants.php vs env.php.
if (!defined('APP_URL')) define('APP_URL', $envv('APP_URL', 'https://maverencapital.com'));

// --- Database (all required) --------------------------------------------
define('DB_HOST', $require('DB_HOST'));
define('DB_NAME', $require('DB_NAME'));
define('DB_USER', $require('DB_USER'));
define('DB_PASS', $envv('DB_PASS', ''));  // password may legitimately be empty in some local setups

// --- SMTP (credentials required, tuning optional) -----------------------
//
// SMTP_USER/SMTP_PASS are required, not optional.
//
// They used to default to ''. An empty password does not disable auth - the
// app still advertises SMTPAuth and offers empty credentials, so the server
// rejects every login and PHPMailer throws on each send. Nothing stopped at
// boot, so the only symptom was mail quietly not arriving: registration still
// answered "we sent a 6-digit verification code" and members waited on an
// email that was never accepted. Dropping the password out of .env is exactly
// how that happened once already.
//
// A relay that genuinely needs no authentication belongs behind an explicit
// flag, not behind a blank credential that looks like a typo.
define('SMTP_HOST',      $require('SMTP_HOST'));
define('SMTP_PORT',      (int) $envv('SMTP_PORT', 465));
define('SMTP_USER',      $require('SMTP_USER'));
define('SMTP_PASS',      $require('SMTP_PASS'));
define('SMTP_FROM',      $require('SMTP_FROM'));
define('SMTP_FROM_NAME', $envv('SMTP_FROM_NAME', 'Maveren Capital'));
define('SMTP_SECURE',    $envv('SMTP_SECURE', 'ssl'));

// --- NOWPayments --------------------------------------------------------
// Required, not optional.
//
// These used to default to '' on a missing key. An empty NOWPAY_IPN_SECRET
// means now_webhook.php computes its HMAC under an empty key - it still fails
// closed, so nothing is forgeable, but every real IPN 403s and deposits sit
// pending forever with no signal anywhere. A truncated .env should stop the
// app at boot instead of quietly breaking payments.
define('NOWPAY_API_KEY',    $require('NOWPAYMENTS_API_KEY'));
define('NOWPAY_IPN_SECRET', $require('NOWPAYMENTS_IPN_SECRET'));
define('NOWPAY_CA_BUNDLE',  __DIR__ . '/certs/cacert.pem');

// Opt-in escape hatch for create_crypto_payment.php's TLS fallback. Absent or
// anything other than the exact string "true" leaves verification ON.
define('NOWPAY_INSECURE_FALLBACK', strtolower(trim($envv('NOWPAY_INSECURE_FALLBACK', ''))) === 'true');

// --- Admin --------------------------------------------------------------
// --- Translation (api/utilities/i18n.php) ---------------------------------
// I18N_DRIVER: mymemory (default, no key) | google | deepl
// I18N_EMAIL:  raises MyMemory's free daily allowance ~10x. Costs nothing.
// I18N_API_KEY: required by the google and deepl drivers only.
define('I18N_DRIVER',  $envv('I18N_DRIVER', 'mymemory'));
define('I18N_EMAIL',   $envv('I18N_EMAIL', ''));
define('I18N_API_KEY', $envv('I18N_API_KEY', ''));

define('ADMIN_CONTACT_EMAIL', $envv('SMTP_TO', $envv('SMTP_FROM', 'support@maverencapital.com')));
define('ADMIN_INVITE_CODE',   $envv('ADMIN_INVITE_CODE', ''));

// --- Error Display and Logging ------------------------------------------
//
// Production used to run error_reporting(0), which does not just hide errors
// from the visitor - it stops PHP RECORDING them at all. Warnings, notices and
// uncaught fatals were silently discarded, so the only things that ever
// reached a log were the handful of explicit error_log() calls in the code.
// After launch that means a failing deposit, a broken query or a fatal in the
// IPN handler leaves no trace anywhere.
//
// The correct split is: report and log everything, display nothing.
error_reporting(E_ALL);
ini_set('log_errors', '1');

if (APP_ENV === 'local') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);

    // One file instead of the host default, which is the bare relative name
    // "error_log" - that scatters a separate log into whichever directory the
    // entry script happened to live in, and drops those files inside the web
    // root. logs/ is denied by .htaccess (RedirectMatch 403 on ^/logs).
    //
    // Silent no-op if the directory is not writable: PHP falls back to the
    // server default rather than failing the request. Verify after deploy with
    //   php -r 'error_log("deploy check");' && tail logs/php-error.log
    $logDir = dirname(__DIR__) . '/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/php-error.log');
    }
    unset($logDir);
}

// --- Cleanup loader locals so callers don't see them --------------------
unset($envPath, $envValues, $envv, $require, $env);
