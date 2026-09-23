<?php
// ========================================
// HELPER FUNCTIONS - Maveren Capital
// ========================================

/**
 * Logs an admin action (for audit trails)
 */
function logAdminAction($pdo, $admin_id, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$admin_id, $action, $details]);
    } catch (Exception $e) {
        error_log('Admin log error: ' . $e->getMessage());
    }
}

/**
 * Sanitize user input to prevent XSS or injection
 */
function cleanInput($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Attempt to resolve approximate location from IP address.
 * Works safely in localhost (returns 'Localhost') and online.
 */
function getLocationFromIP($ip) {
    try {
        // Handle localhost or private IPs quickly
        if (in_array($ip, ['127.0.0.1', '::1']) || preg_match('/^192\.168\./', $ip)) {
            return 'Localhost / Internal Network';
        }

        // Lightweight public lookup (2s timeout)
        $url = "https://ipapi.co/{$ip}/json/";
        $context = stream_context_create(['http' => ['timeout' => 2]]);
        $response = @file_get_contents($url, false, $context);
        if (!$response) return 'Unknown Location';

        $data = json_decode($response, true);
        if (!is_array($data)) return 'Unknown Location';

        $city = $data['city'] ?? '';
        $region = $data['region'] ?? '';
        $country = $data['country_name'] ?? '';
        $parts = array_filter([$city, $region, $country]);

        return $parts ? implode(', ', $parts) : 'Unknown Location';

    } catch (Exception $e) {
        error_log('GeoIP lookup failed: ' . $e->getMessage());
        return 'Unknown Location';
    }
}
function logLoginEvent($pdo, $userType, $userId, $ip, $browser, $location) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_type, user_id, ip, browser, location, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userType, $userId, $ip, $browser, $location]);
    } catch (Exception $e) {
        error_log('Login log error: ' . $e->getMessage());
    }
}

/**
 * Extracts readable browser + OS information from a user-agent string.
 */
function getUserBrowser($userAgent) {
    $browser = 'Unknown Browser';
    $platform = 'Unknown OS';
    $version = '';

    // --- Detect platform ---
    if (preg_match('/linux/i', $userAgent)) {
        $platform = 'Linux';
    } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $platform = 'Mac OS';
    } elseif (preg_match('/windows|win32/i', $userAgent)) {
        $platform = 'Windows';
    } elseif (preg_match('/iphone/i', $userAgent)) {
        $platform = 'iPhone';
    } elseif (preg_match('/android/i', $userAgent)) {
        $platform = 'Android';
    }

    // --- Detect browser ---
    if (preg_match('/MSIE/i', $userAgent) && !preg_match('/Opera/i', $userAgent)) {
        $browser = 'Internet Explorer';
        $ub = "MSIE";
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $browser = 'Firefox';
        $ub = "Firefox";
    } elseif (preg_match('/OPR|Opera/i', $userAgent)) {
        $browser = 'Opera';
        $ub = "OPR";
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $browser = 'Edge';
        $ub = "Edge";
    } elseif (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edge/i', $userAgent)) {
        $browser = 'Chrome';
        $ub = "Chrome";
    } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Safari';
        $ub = "Safari";
    } else {
        $ub = '';
    }

    // --- Extract version ---
    if ($ub && preg_match("/{$ub}\/([0-9\.]+)/i", $userAgent, $matches)) {
        $version = $matches[1];
    }

    return trim("{$browser} {$version} on {$platform}");
}

// ============================================================
// Brute-force mitigation lives in api/utilities/security.php.
//
// ensureLoginAttemptsTable() / loginThrottleExceeded() /
// recordLoginFailure() used to sit here. They were superseded on
// 2026-08-01 by mvcRateLimited() / mvcRecordAttempt(), and by then
// nothing called them - but ensureLoginAttemptsTable() still issued a
// CREATE TABLE IF NOT EXISTS on every invocation, which is why
// `login_attempts` exists on this machine while appearing in no schema
// file or migration. That orphan is exactly the drift `migrate.php` and
// the schema_migrations ledger now exist to prevent, so keeping dead
// code capable of recreating it would defeat the point.
//
// The replacements are strictly better: scoped buckets (login,
// admin_login, register, reset, otp, deposit, withdraw, invest,
// contact) so member and admin logins no longer share one counter, and
// per-IP AND per-account subjects rather than IP alone.
//
// `login_attempts` itself is left on disk - dropping a table is not
// something a code change should do silently. Once this is deployed:
//   DROP TABLE IF EXISTS `login_attempts`;
// ============================================================

/**
 * Append a structured security event to logs/security.log (A09).
 * Values are newline-stripped to prevent log forgery/injection (audit 11.3).
 */
function logSecurityEvent($event, array $context = []) {
    try {
        $logPath = __DIR__ . '/../../logs/security.log';
        if (!is_dir(dirname($logPath))) @mkdir(dirname($logPath), 0775, true);
        $clean = [];
        foreach ($context as $k => $v) {
            $clean[$k] = is_scalar($v) ? str_replace(["\r", "\n"], ' ', (string) $v) : json_encode($v);
        }
        $line = json_encode(['ts' => date('c'), 'event' => $event] + $clean, JSON_UNESCAPED_SLASHES);
        @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        error_log('logSecurityEvent error: ' . $e->getMessage());
    }
}

/**
 * Human label for a transactions.type slug.
 *
 * The column stores machine slugs (deposit, withdraw, investment, roi_payout,
 * investment_release). Every consumer used to print them with ucfirst() alone,
 * which surfaced "Roi_payout" in the UI and in CSV exports.
 *
 * Unknown slugs fall back to title-cased words so a new type added later still
 * reads sensibly instead of leaking an underscore.
 */
function formatTransactionType($type) {
    $slug = strtolower(trim((string) $type));

    $labels = [
        'deposit'            => 'Deposit',
        'withdraw'           => 'Withdrawal',
        'withdrawal'         => 'Withdrawal',
        'investment'         => 'Investment',
        'roi_payout'         => 'ROI Payout',
        'roi'                => 'ROI Payout',
        'payout'             => 'Payout',
        'investment_release' => 'Principal Released',
    ];

    if (isset($labels[$slug])) {
        return $labels[$slug];
    }

    if ($slug === '') {
        return 'N/A';
    }

    return ucwords(str_replace('_', ' ', $slug));
}

/**
 * Human label for a transactions.method slug.
 *
 * Sibling of formatTransactionType(). Callers were split between
 * ucfirst(str_replace('_',' ',$m)) and a bare ucfirst($m) - the latter leaving
 * "Wallet_address" in member and admin emails. One helper, one spelling.
 *
 * wire_transfer and cash_mailing are retired but stay mapped: historical rows
 * still carry them and must not suddenly render as raw slugs.
 */
function formatPaymentMethod($method) {
    $slug = strtolower(trim((string) $method));

    $labels = [
        'secure_exchange' => 'Crypto Checkout',
        // Manual transfer to an address we publish. Not the same thing as
        // 'wallet_address', which is the member's own payout address.
        'deposit_address' => 'Deposit Address',
        'local_bank'      => 'Local Bank',
        'wallet_address'  => 'Wallet Address',
        'wallet'          => 'Wallet',
        'system'          => 'System',
        'wire_transfer'   => 'Wire Transfer',
        'cash_mailing'    => 'Cash Mailing',
    ];

    if (isset($labels[$slug])) {
        return $labels[$slug];
    }

    if ($slug === '') {
        return 'N/A';
    }

    return ucwords(str_replace('_', ' ', $slug));
}
?>
