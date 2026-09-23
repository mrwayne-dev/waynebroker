<?php
/**
 * ============================================================
 * FILE: /api/utilities/i18n.php
 * Server-side page translation.
 *
 * WHY SERVER-SIDE. The obvious approach - Google's Website Translator
 * widget - is retired. It still serves a loader, still builds its
 * <select>, and leaves it permanently EMPTY: no request to
 * translate-pa.googleapis.com/v1/supportedLanguages is ever made.
 * Verified against Google's own documented snippet on a bare page with
 * no CSP, so it is the widget, not our integration.
 *
 * Doing it here instead has three consequences worth stating:
 *   - No third-party script runs in the browser, so the strict CSP with
 *     its four inline-script hashes stays enforced everywhere. The
 *     widget would have cost us that.
 *   - Translations are cached, so a page is translated once per locale
 *     and then served from disk.
 *   - It works with JavaScript disabled.
 *
 * HOW IT WORKS. mvcI18nStart() opens an output buffer. At shutdown the
 * buffered HTML is parsed, its translatable TEXT NODES and a short list
 * of user-visible ATTRIBUTES are collected, translated as phrases, and
 * substituted back. Phrases are cached individually, so the nav, footer
 * and shared components are already warm by the second page.
 *
 * DRIVERS. Configured with I18N_DRIVER in .env:
 *   mymemory  (default) no key, works today, ~5k words/day anonymous
 *   google    Cloud Translation v2   - set I18N_API_KEY
 *   deepl     DeepL API              - set I18N_API_KEY
 * google and deepl batch many phrases per request and are what you want
 * under real traffic; mymemory takes one phrase per request and is
 * parallelised with curl_multi to compensate.
 * ============================================================
 */

declare(strict_types=1);

const I18N_COOKIE     = 'mvc_lang';
const I18N_SOURCE     = 'en';
const I18N_CACHE_DIR  = __DIR__ . '/../../cache/i18n';
/** Phrases translated synchronously in one request. Anything beyond this is
 *  left in the source language and picked up on a later view, so a cold cache
 *  degrades into a slower fill rather than a slow page. */
const I18N_BUDGET     = 120;
const I18N_PARALLEL   = 12;
const I18N_TIMEOUT    = 12;
/** Translated pages kept per locale before the oldest are pruned. */
const I18N_PAGE_CACHE_MAX = 60;

/** Never translate the contents of these. */
const I18N_SKIP_TAGS = ['script', 'style', 'code', 'pre', 'textarea', 'svg', 'noscript'];
/** Attributes that are read by a human and so must be translated too. */
const I18N_ATTRS = ['placeholder', 'title', 'alt', 'aria-label', 'value'];

/** RTL scripts - <html dir> has to follow the language or the page is unreadable. */
const I18N_RTL = ['ar', 'he', 'fa', 'ur', 'ps', 'yi', 'dv', 'ckb', 'ug', 'sd'];

/** The locale for this request, validated. */
function mvcI18nLocale(): string
{
    $raw = (string) ($_COOKIE[I18N_COOKIE] ?? I18N_SOURCE);
    // Whitelist by shape, not by list: BCP-47-ish, at most 12 chars. A cookie
    // value reaches a filesystem path below, so it must never carry a slash or
    // a dot.
    return preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z]{2,8})?$/', $raw) ? $raw : I18N_SOURCE;
}

function mvcI18nIsRtl(string $locale): bool
{
    return in_array(strtolower(explode('-', $locale)[0]), I18N_RTL, true);
}

/**
 * Begin translating this response. No-op when the reader is on English.
 * Call as the first statement of a page's <head> partial.
 */
function mvcI18nStart(): void
{
    $locale = mvcI18nLocale();
    if ($locale === I18N_SOURCE) {
        return;
    }
    ob_start(static function (string $html) use ($locale): string {
        try {
            // Parsing and rewriting the DOM costs ~1s on a page this size,
            // against 8ms for an untranslated render. So the finished page is
            // cached whole, keyed on a hash of the SOURCE html: any content
            // change yields a new key, which makes invalidation automatic and
            // means there is no stale-cache failure mode to manage.
            $key    = sha1(mvcI18nCacheKeySource($html));
            $cached = mvcI18nPageCacheGet($locale, $key);
            if ($cached !== null) {
                return $cached;
            }
            $out = mvcI18nTranslateHtml($html, $locale);
            // Cache only a COMPLETE render. A cold page needs a few views for
            // the phrase cache to fill; caching the partial result of the
            // first one would make those fragments permanently English.
            if ($out !== $html && mvcI18nCompleteFlag()) {
                mvcI18nPageCachePut($locale, $key, $out);
            }
            return $out;
        } catch (Throwable $e) {
            // A translation failure must never blank the page. Serve English.
            error_log('i18n: ' . $e->getMessage());
            return $html;
        }
    });
}

/* ------------------------------------------------------------------ */
/* Circuit breaker                                                     */
/* ------------------------------------------------------------------ */

/**
 * When the driver is refusing work - quota spent, key rejected, provider
 * down - stop calling it.
 *
 * Without this, an exhausted quota turns every translated request into 120
 * doomed HTTP calls: measured at 5+ seconds per page view, all of it wasted,
 * and the page still renders in English at the end. That is a denial of
 * service we inflict on ourselves. Once tripped, requests fall straight back
 * to cache-only until the window passes.
 */
function mvcI18nBreakerFile(): string
{
    return I18N_CACHE_DIR . '/.breaker';
}

function mvcI18nBreakerOpen(): bool
{
    $f = mvcI18nBreakerFile();
    if (!is_file($f)) return false;
    $until = (int) file_get_contents($f);
    if ($until > time()) return true;
    @unlink($f);
    return false;
}

function mvcI18nBreakerTrip(int $minutes = 30): void
{
    if (!is_dir(I18N_CACHE_DIR)) {
        @mkdir(I18N_CACHE_DIR, 0775, true);
    }
    @file_put_contents(mvcI18nBreakerFile(), (string) (time() + $minutes * 60));
}

/* ------------------------------------------------------------------ */
/* Phrase cache                                                        */
/* ------------------------------------------------------------------ */

function mvcI18nCacheFile(string $locale): string
{
    return I18N_CACHE_DIR . '/' . preg_replace('/[^a-zA-Z0-9-]/', '', $locale) . '.json';
}

function mvcI18nLoadCache(string $locale): array
{
    $f = mvcI18nCacheFile($locale);
    if (!is_file($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function mvcI18nSaveCache(string $locale, array $cache): void
{
    if (!is_dir(I18N_CACHE_DIR) && !@mkdir(I18N_CACHE_DIR, 0775, true) && !is_dir(I18N_CACHE_DIR)) {
        return;
    }
    $f = mvcI18nCacheFile($locale);
    // Write-then-rename: two concurrent requests both filling a cold cache
    // would otherwise interleave and leave a half-written JSON file that every
    // later request fails to parse.
    $tmp = $f . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($tmp, $f);
    }
}

/* ------------------------------------------------------------------ */
/* Whole-page cache                                                    */
/* ------------------------------------------------------------------ */

/**
 * The bytes the page-cache key is computed from.
 *
 * The raw HTML carries a per-session CSRF token, so hashing it directly gives
 * every visitor their own cache entry and the cache never hits. Blank the
 * volatile parts first: what is left changes only when the CONTENT changes,
 * which is exactly the invalidation signal we want.
 */
function mvcI18nCacheKeySource(string $html): string
{
    return (string) preg_replace(
        [
            '/(<meta[^>]+name=["\']csrf-token["\'][^>]+content=)["\'][^"\']*["\']/i',
            '/(name=["\']csrf_token["\'][^>]*value=)["\'][^"\']*["\']/i',
        ],
        '$1""',
        $html
    );
}

function mvcI18nPageDir(string $locale): string
{
    return I18N_CACHE_DIR . '/pages/' . preg_replace('/[^a-zA-Z0-9-]/', '', $locale);
}

function mvcI18nPageCacheGet(string $locale, string $key): ?string
{
    $f = mvcI18nPageDir($locale) . '/' . $key . '.html';
    if (!is_file($f)) return null;
    $c = file_get_contents($f);
    return is_string($c) && $c !== '' ? $c : null;
}

function mvcI18nPageCachePut(string $locale, string $key, string $html): void
{
    $dir = mvcI18nPageDir($locale);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return;

    $f   = $dir . '/' . $key . '.html';
    $tmp = $f . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $html) !== false) {
        @rename($tmp, $f);
    }

    // Bound the directory. Every content edit mints a new key, so without a
    // cap this grows once per deploy per page per locale, forever.
    $files = glob($dir . '/*.html') ?: [];
    if (count($files) > I18N_PAGE_CACHE_MAX) {
        usort($files, static fn($a, $b) => filemtime($a) <=> filemtime($b));
        foreach (array_slice($files, 0, count($files) - I18N_PAGE_CACHE_MAX) as $old) {
            @unlink($old);
        }
    }
}

/* ------------------------------------------------------------------ */
/* HTML walk                                                           */
/* ------------------------------------------------------------------ */

/** Is this string worth sending to a translator? */
function mvcI18nTranslatable(string $s): bool
{
    $t = trim($s);
    if ($t === '' || mb_strlen($t) < 2)      return false;
    if (!preg_match('/\p{L}{2,}/u', $t))     return false;  // needs real letters
    if (preg_match('/^[\p{Nd}\p{P}\p{S}\s]+$/u', $t)) return false;  // 1,234.00 / $ / -
    return true;
}

function mvcI18nTranslateHtml(string $html, string $locale): string
{
    if (stripos($html, '<html') === false) {
        return $html;   // fragment or JSON response - not a page
    }

    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    // The meta charset makes DOMDocument treat the bytes as UTF-8; without it
    // it assumes ISO-8859-1 and mangles every non-ASCII character on output.
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xpath = new DOMXPath($doc);

    $skip = implode(' or ', array_map(
        static fn(string $t): string => "ancestor::$t",
        I18N_SKIP_TAGS
    ));
    // translate="no" and .notranslate are the standard opt-outs; honour both,
    // on the node or any ancestor.
    $nodes = $xpath->query(
        "//text()[not($skip)]" .
        "[not(ancestor::*[@translate='no'])]" .
        "[not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' notranslate ')])]"
    );

    /** @var array<string,true> $phrases */
    $phrases = [];
    $targets = [];   // [node, kind, attrName]

    foreach ($nodes as $n) {
        if (mvcI18nTranslatable($n->nodeValue)) {
            $phrases[trim($n->nodeValue)] = true;
            $targets[] = [$n, 'text', null];
        }
    }
    foreach (I18N_ATTRS as $attr) {
        foreach ($xpath->query("//*[@$attr]") as $el) {
            // value= is only human-facing on buttons; on an <input type=hidden>
            // or a <select><option> it is data the server parses back.
            if ($attr === 'value' && !in_array(strtolower($el->nodeName), ['button'], true)) {
                continue;
            }
            $v = $el->getAttribute($attr);
            if (mvcI18nTranslatable($v)) {
                $phrases[trim($v)] = true;
                $targets[] = [$el, 'attr', $attr];
            }
        }
    }

    if (!$phrases) return $html;

    $map = mvcI18nResolve(array_keys($phrases), $locale);

    foreach ($targets as [$node, $kind, $attr]) {
        if ($kind === 'text') {
            $raw = $node->nodeValue;
            $key = trim($raw);
            if (!isset($map[$key])) continue;
            // Put the original leading/trailing whitespace back: it is what
            // keeps "Sign in" from butting against the element beside it.
            preg_match('/^(\s*)/u', $raw, $lead);
            preg_match('/(\s*)$/u', $raw, $tail);
            // Assign nodeValue directly. $node is a DOMText, not an element -
            // appendChild() on it does nothing, so the previous form emptied
            // the node and put nothing back, blanking every string on the page.
            $node->nodeValue = $lead[1] . $map[$key] . $tail[1];
        } else {
            $key = trim($node->getAttribute($attr));
            if (isset($map[$key])) $node->setAttribute($attr, $map[$key]);
        }
    }

    // Tell the browser (and assistive tech) what language it is now looking at.
    $htmlEl = $doc->getElementsByTagName('html')->item(0);
    if ($htmlEl instanceof DOMElement) {
        $htmlEl->setAttribute('lang', $locale);
        $htmlEl->setAttribute('dir', mvcI18nIsRtl($locale) ? 'rtl' : 'ltr');
    }

    $out = $doc->saveHTML();
    if (!is_string($out) || $out === '') return $html;

    $out = preg_replace('/^<\?xml encoding="UTF-8"\?>\s*/', '', $out) ?? $out;

    // saveHTML() escapes every non-ASCII character as a named or numeric
    // entity, so a Spanish page doubles in size and an Arabic one is almost
    // entirely entities. Decode ONLY the ones above U+007F - &amp;, &lt;,
    // &gt; and &quot; are left alone, because decoding those would turn
    // escaped text back into live markup.
    return preg_replace_callback(
        '/&(?:#(\d+)|#[xX]([0-9a-fA-F]+)|([A-Za-z][A-Za-z0-9]{1,31}));/',
        static function (array $m): string {
            if ($m[1] !== '') {
                $cp = (int) $m[1];
            } elseif (($m[2] ?? '') !== '') {
                $cp = (int) hexdec($m[2]);
            } else {
                $named = html_entity_decode('&' . $m[3] . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($named === '&' . $m[3] . ';') return $m[0];      // unknown, leave it
                $cp = mb_ord($named, 'UTF-8');
                if ($cp === false) return $m[0];
            }
            return $cp > 0x7F ? mb_chr($cp, 'UTF-8') : $m[0];
        },
        $out
    ) ?? $out;
}

/**
 * Phrase -> translation, cache first, translating only what is missing and
 * only up to the per-request budget.
 */
/**
 * Set false by mvcI18nResolve() when it could not translate every phrase -
 * either the budget ran out or the driver failed on some of them. The page
 * cache reads it, because caching a half-translated render would freeze the
 * untranslated fragments in English permanently: the phrase cache would go on
 * filling, but no request would ever rebuild the page to use it.
 */
function &mvcI18nCompleteFlag(): bool
{
    static $complete = true;
    return $complete;
}

function mvcI18nResolve(array $phrases, string $locale): array
{
    $complete = &mvcI18nCompleteFlag();
    $cache = mvcI18nLoadCache($locale);
    $map   = [];
    $miss  = [];

    foreach ($phrases as $p) {
        $k = sha1($p);
        if (isset($cache[$k])) $map[$p] = $cache[$k];
        else                   $miss[]  = $p;
    }
    if (!$miss) return $map;

    if (count($miss) > I18N_BUDGET) {
        $complete = false;
    }

    // Cache-only mode: serve what we have, translate nothing new.
    if (mvcI18nBreakerOpen()) {
        $complete = false;
        return $map;
    }

    // DOCUMENT ORDER, deliberately - $phrases arrives in the order the walk
    // found it. Sorting longest-first was tried and is wrong: nav labels and
    // buttons are the shortest strings on the page and the most conspicuous,
    // so they lost the budget to body copy and the chrome stayed English
    // through several page views.
    $batch = array_slice($miss, 0, I18N_BUDGET);

    $fresh = mvcI18nDriverTranslate($batch, $locale);
    if (count($fresh) < count($batch)) {
        $complete = false;
    }
    // Nothing at all came back for a non-empty batch: the provider is refusing
    // us, not struggling with a phrase. Stop asking for a while.
    if (!$fresh && $batch) {
        mvcI18nBreakerTrip();
        error_log('i18n: driver returned nothing for ' . count($batch)
                . ' phrases - pausing translation for 30 minutes.');
    }
    foreach ($fresh as $src => $dst) {
        if ($dst !== '' && $dst !== $src) {
            $cache[sha1($src)] = $dst;
            $map[$src] = $dst;
        }
    }
    if ($fresh) mvcI18nSaveCache($locale, $cache);

    return $map;
}

/* ------------------------------------------------------------------ */
/* Drivers                                                             */
/* ------------------------------------------------------------------ */

/**
 * Base cURL options for every driver.
 *
 * CURLOPT_CAINFO is only included when the bundle constant exists. Passing
 * null makes curl_setopt_array() reject the ENTIRE array - including
 * CURLOPT_RETURNTRANSFER - so the handle runs with default options and
 * curl_multi_getcontent() comes back empty with errno 0 and HTTP 0. That
 * silence is what made this look like a network or quota problem when the
 * constant happened not to be defined yet.
 */
function mvcI18nCurlOpts(): array
{
    $o = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => I18N_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'MaverenCapital/1.0 (+https://maverencapital.com)',
    ];
    if (defined('NOWPAY_CA_BUNDLE') && is_string(NOWPAY_CA_BUNDLE) && is_file(NOWPAY_CA_BUNDLE)) {
        $o[CURLOPT_CAINFO] = NOWPAY_CA_BUNDLE;
    }
    return $o;
}

function mvcI18nDriver(): string
{
    $d = defined('I18N_DRIVER') ? I18N_DRIVER : 'mymemory';
    return in_array($d, ['mymemory', 'google', 'deepl'], true) ? $d : 'mymemory';
}

function mvcI18nApiKey(): string
{
    return defined('I18N_API_KEY') ? (string) I18N_API_KEY : '';
}

function mvcI18nDriverTranslate(array $phrases, string $locale): array
{
    if (!$phrases) return [];

    $driver = mvcI18nDriver();

    // google and deepl are useless without a key: they would return nothing,
    // the circuit breaker would trip on the empty batch, and the site would
    // quietly serve English for half an hour with only a log line to explain
    // it. Setting I18N_DRIVER=google before the key is in place is an easy
    // mistake to make at deploy time, so fall back rather than fail.
    if (in_array($driver, ['google', 'deepl'], true) && mvcI18nApiKey() === '') {
        static $warned = false;
        if (!$warned) {
            error_log("i18n: I18N_DRIVER=$driver but I18N_API_KEY is empty - falling back to mymemory.");
            $warned = true;
        }
        $driver = 'mymemory';
    }

    return match ($driver) {
        'google' => mvcI18nGoogle($phrases, $locale),
        'deepl'  => mvcI18nDeepl($phrases, $locale),
        default  => mvcI18nMyMemory($phrases, $locale),
    };
}

/**
 * MyMemory: one phrase per request, so fire them in parallel with curl_multi.
 * Sequentially this would be ~120 round trips on a cold page.
 */
function mvcI18nMyMemory(array $phrases, string $locale): array
{
    $out  = [];
    $pair = I18N_SOURCE . '|' . $locale;

    foreach (array_chunk($phrases, I18N_PARALLEL) as $chunk) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($chunk as $p) {
            $q = ['q' => $p, 'langpair' => $pair];
            // Supplying a contact address raises MyMemory's free allowance
            // from ~5,000 to ~50,000 words/day. Without it a single cold
            // dashboard render can exhaust the anonymous quota for the whole
            // server IP, which is exactly what happened in testing.
            if (defined('I18N_EMAIL') && I18N_EMAIL !== '') {
                $q['de'] = I18N_EMAIL;
            }
            $url = 'https://api.mymemory.translated.net/get?' . http_build_query($q);
            $ch = curl_init($url);
            curl_setopt_array($ch, mvcI18nCurlOpts());
            curl_multi_add_handle($mh, $ch);
            $handles[$p] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $p => $ch) {
            $body = curl_multi_getcontent($ch);
            $j = json_decode((string) $body, true);
            $t = $j['responseData']['translatedText'] ?? '';
            // MyMemory answers 200 with the quota notice in the payload, so the
            // HTTP status alone does not tell you it failed.
            $status = (int) ($j['responseStatus'] ?? 0);
            if (is_string($t) && $t !== '' && stripos($t, 'MYMEMORY WARNING') === false && $status === 200) {
                $out[$p] = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif ($status === 429 || stripos((string) $t, 'MYMEMORY WARNING') !== false) {
                // Log once per batch, not once per phrase. Silence here reads
                // as "translation is broken" when it is really "the free
                // quota is spent" - set I18N_EMAIL, or move to the google or
                // deepl driver.
                static $warned = false;
                if (!$warned) {
                    error_log('i18n: MyMemory quota exhausted for this IP. Set I18N_EMAIL in .env, or switch I18N_DRIVER to google/deepl.');
                    $warned = true;
                }
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $out;
}

/** Google Cloud Translation v2 - up to 128 strings per request. */
function mvcI18nGoogle(array $phrases, string $locale): array
{
    $key = mvcI18nApiKey();
    if ($key === '') return [];
    $out = [];
    foreach (array_chunk($phrases, 100) as $chunk) {
        $post = ['q' => $chunk, 'source' => I18N_SOURCE, 'target' => $locale, 'format' => 'text'];
        $ch = curl_init('https://translation.googleapis.com/language/translate/v2?key=' . urlencode($key));
        curl_setopt_array($ch, mvcI18nCurlOpts() + [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $j = json_decode((string) $body, true);
        foreach (($j['data']['translations'] ?? []) as $i => $t) {
            if (isset($chunk[$i], $t['translatedText'])) {
                $out[$chunk[$i]] = html_entity_decode((string) $t['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
    }
    return $out;
}

/** DeepL - up to 50 texts per request. */
function mvcI18nDeepl(array $phrases, string $locale): array
{
    $key = mvcI18nApiKey();
    if ($key === '') return [];
    $host = str_ends_with($key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
    $out = [];
    foreach (array_chunk($phrases, 50) as $chunk) {
        $fields = ['source_lang' => strtoupper(I18N_SOURCE), 'target_lang' => strtoupper($locale)];
        $q = http_build_query($fields);
        foreach ($chunk as $t) $q .= '&text=' . rawurlencode($t);
        $ch = curl_init($host . '/v2/translate');
        curl_setopt_array($ch, mvcI18nCurlOpts() + [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => $q,
            CURLOPT_HTTPHEADER => ['Authorization: DeepL-Auth-Key ' . $key],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $j = json_decode((string) $body, true);
        foreach (($j['translations'] ?? []) as $i => $t) {
            if (isset($chunk[$i], $t['text'])) $out[$chunk[$i]] = (string) $t['text'];
        }
    }
    return $out;
}
