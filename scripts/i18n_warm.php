<?php
/**
 * ============================================================
 * FILE: /scripts/i18n_warm.php
 * Pre-build the translation cache.
 *
 * A cold page costs one driver round trip per phrase, which is seconds.
 * Run this after a deploy and no visitor ever pays that: every page is
 * already in cache/i18n/pages/{locale}/.
 *
 * USAGE
 *   php scripts/i18n_warm.php es fr de            warm those locales
 *   php scripts/i18n_warm.php --status            what is cached now
 *   php scripts/i18n_warm.php --clear             drop the whole cache
 *   php scripts/i18n_warm.php --clear es          drop one locale
 *
 * It warms the PUBLIC pages only. Member and admin pages need a session,
 * and their chrome is largely the same strings, so the phrase cache the
 * public pass builds covers most of them anyway.
 * ============================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Access Denied: CLI only\n");
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../api/utilities/i18n.php';

$paths = ['/', '/plans', '/platform', '/solutions', '/about', '/contact', '/login', '/register'];

$args   = array_slice($argv, 1);
$status = in_array('--status', $args, true);
$clear  = in_array('--clear', $args, true);
$locales = array_values(array_filter($args, static fn($a) => !str_starts_with($a, '--')));

$out = static fn(string $s) => fwrite(STDOUT, $s . "\n");

/* ---------------- status ---------------- */
if ($status) {
    $out('Translation cache: ' . I18N_CACHE_DIR);
    $out('Driver: ' . mvcI18nDriver() . (mvcI18nApiKey() !== '' ? ' (key set)' : ''));
    foreach (glob(I18N_CACHE_DIR . '/*.json') ?: [] as $f) {
        $n = count(json_decode((string) file_get_contents($f), true) ?: []);
        $out(sprintf('  %-8s %5d phrases', basename($f, '.json'), $n));
    }
    foreach (glob(I18N_CACHE_DIR . '/pages/*', GLOB_ONLYDIR) ?: [] as $d) {
        $out(sprintf('  %-8s %5d cached pages', basename($d), count(glob($d . '/*.html') ?: [])));
    }
    exit(0);
}

/* ---------------- clear ---------------- */
if ($clear) {
    $targets = $locales ?: ['*'];
    $n = 0;
    foreach ($targets as $t) {
        foreach (glob(I18N_CACHE_DIR . "/$t.json") ?: [] as $f) { @unlink($f); $n++; }
        foreach (glob(I18N_CACHE_DIR . "/pages/$t/*.html") ?: [] as $f) { @unlink($f); $n++; }
    }
    $out("Removed $n cache file(s).");
    $out('Note: files written by the web server are owned by its user. If some');
    $out('survived, run this as that user (on cPanel they are the same account).');
    exit(0);
}

if (!$locales) {
    exit("Give one or more locales, e.g.  php scripts/i18n_warm.php es fr de\n"
       . "Or --status / --clear.\n");
}

/* ---------------- warm ---------------- */
// Fetch each page from the site itself so the warm path is EXACTLY the render
// path - same partials, same buffer, same cache keys. Rebuilding the HTML here
// would warm a page no visitor ever sees.
$base = rtrim(APP_URL, '/');
$out("Warming " . count($paths) . " pages x " . count($locales) . " locale(s) against $base");
$out('Driver: ' . mvcI18nDriver());

$failures = 0;
foreach ($locales as $loc) {
    if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z]{2,8})?$/', $loc)) {
        $out("  skipping invalid locale '$loc'");
        continue;
    }
    $out("\n[$loc]");
    foreach ($paths as $path) {
        $t0 = microtime(true);
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE         => I18N_COOKIE . '=' . $loc,
            CURLOPT_TIMEOUT        => 180,   // a cold page is slow, by design
            CURLOPT_SSL_VERIFYPEER => APP_ENV !== 'local',  // .test uses a local CA
            CURLOPT_SSL_VERIFYHOST => APP_ENV !== 'local' ? 2 : 0,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $secs = round(microtime(true) - $t0, 1);
        if ($code === 200 && is_string($body) && $body !== '') {
            $out(sprintf('  %-12s ok    %5.1fs', $path, $secs));
        } else {
            $failures++;
            $out(sprintf('  %-12s FAIL  http=%s %s', $path, $code, $err));
        }
        // A second pass picks up anything the per-request budget deferred, and
        // only a complete render is written to the page cache.
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE         => I18N_COOKIE . '=' . $loc,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_SSL_VERIFYPEER => APP_ENV !== 'local',
            CURLOPT_SSL_VERIFYHOST => APP_ENV !== 'local' ? 2 : 0,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

$out($failures ? "\nDone with $failures failure(s)." : "\nDone.");
