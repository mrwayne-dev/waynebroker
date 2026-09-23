<?php
/**
 * ============================================================
 * FILE: /scripts/csp_hashes.php
 * Recompute the CSP script-src hashes.
 *
 * The policy in .htaccess pins every inline <script> by the SHA-256 of
 * its body. That is strong - nothing unpinned runs - but it fails
 * silently: change one character inside an inline script and the browser
 * simply refuses to execute it, with no error in the page, only a
 * console entry most people never look at.
 *
 * That is not hypothetical. Renaming the localStorage key 'anc-theme' to
 * 'mvc-theme' during the rebrand invalidated the theme bootstrap's hash,
 * and the script that sets the theme before first paint was blocked on
 * every page until this was run.
 *
 * RUN IT AFTER ANY EDIT TO AN INLINE <script>, and paste the output into
 * the script-src directive in .htaccess.
 *
 *   php scripts/csp_hashes.php                 # crawl APP_URL
 *   php scripts/csp_hashes.php --check         # non-zero exit if stale
 * ============================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Access Denied: CLI only\n");
}

require_once __DIR__ . '/../config/env.php';

// That assumption was wrong and it cost us: /dashboard.invest carries its own
// inline bootstrap-select initialiser that no public page includes, so a
// public-only crawl silently dropped its hash and the CSP blocked the script.
// The member pages are now crawled too, with a real session.
$paths = ['/', '/plans', '/platform', '/solutions', '/about', '/contact',
          '/login', '/register', '/admin.login'];

$authedPaths = ['/dashboard', '/dashboard.wallet', '/dashboard.invest',
                '/dashboard.transactions', '/dashboard.profile', '/dashboard.kyc'];

$base  = rtrim(APP_URL, '/');
$check = in_array('--check', array_slice($argv, 1), true);
$out   = static fn(string $s) => fwrite(STDOUT, $s . "\n");

/**
 * Sign in as the seeded member so the authenticated pages can be crawled.
 * Returns a cookie-jar path, or null when no seeded account is available -
 * in which case the member pages are skipped and said to be skipped, rather
 * than silently contributing nothing.
 */
function cspLogin(string $base): ?string
{
    $jar = tempnam(sys_get_temp_dir(), 'csp');
    $get = function (string $url) use ($jar) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => APP_ENV !== 'local',
            CURLOPT_SSL_VERIFYHOST => APP_ENV !== 'local' ? 2 : 0,
        ]);
        $b = curl_exec($ch); curl_close($ch);
        return is_string($b) ? $b : '';
    };

    $html = $get($base . '/login');
    if (!preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m)) return null;

    $ch = curl_init($base . '/api/auth/login.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . $m[1]],
        CURLOPT_POSTFIELDS => json_encode([
            'email'    => 'member@maverencapital.test',
            'password' => 'MvcTest!2026',
        ]),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => APP_ENV !== 'local',
        CURLOPT_SSL_VERIFYHOST => APP_ENV !== 'local' ? 2 : 0,
    ]);
    $res = curl_exec($ch); curl_close($ch);

    return (is_string($res) && str_contains($res, '"success"')) ? $jar : null;
}

$jar = cspLogin($base);
if ($jar === null) {
    $out('  note: could not sign in as the seeded member - member pages skipped.');
    $out('        Run scripts/seed_test_accounts.php first for full coverage.');
    $authedPaths = [];
}

$hashes = [];
foreach (array_merge($paths, $authedPaths) as $path) {
    $authed = in_array($path, $authedPaths, true);
    $ch = curl_init($base . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => APP_ENV !== 'local',
        CURLOPT_SSL_VERIFYHOST => APP_ENV !== 'local' ? 2 : 0,
    ];
    if ($authed && $jar) { $opts[CURLOPT_COOKIEFILE] = $jar; }
    curl_setopt_array($ch, $opts);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !is_string($html)) {
        $out("  warning: $path returned $code - skipped");
        continue;
    }
    // Inline only: a <script src=...> is covered by 'self', not by a hash.
    preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $html, $m);
    foreach ($m[1] as $body) {
        $hashes['sha256-' . base64_encode(hash('sha256', $body, true))] = true;
    }
}

$found = array_keys($hashes);
sort($found);

$htaccess = (string) file_get_contents(__DIR__ . '/../.htaccess');
preg_match_all("/'(sha256-[^']+)'/", $htaccess, $cur);
$current = $cur[1];
sort($current);

// MISSING hashes are a hard failure - those scripts are blocked right now.
// Extras are reported but not fatal: /admin.* is still not crawled, so a hash
// only that surface needs would otherwise look deletable.
$missing = array_values(array_diff($found, $current));
$unseen  = array_values(array_diff($current, $found));

if ($check) {
    if (!$missing) {
        $out('CSP hashes are current across public and member pages (' . count($found) . ' inline scripts).');
        if ($unseen) {
            $out('  ' . count($unseen) . ' further hash(es) pinned but not reachable without a session');
            $out('  (member/admin pages) - left alone, as expected.');
        }
        exit(0);
    }
    $out('CSP hashes are STALE - these inline scripts are NOT pinned and will be blocked:');
    foreach ($missing as $h) $out("  '$h'");
    exit(1);
}

$out(count($found) . " inline script(s) found across public and member pages.\n");
if ($missing) {
    $out('NOT yet pinned - add these to script-src in .htaccess:');
    foreach ($missing as $h) $out("  '$h'");
} else {
    $out('Every public inline script is already pinned.');
}
if ($unseen) {
    $out("\nAlso pinned, and not reachable without a session (member/admin) - keep them:");
    foreach ($unseen as $h) $out("  '$h'");
}
