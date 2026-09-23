<?php
/**
 * ============================================================
 * FILE: /api/public/set_language.php
 * Set the reader's language cookie, then send them back.
 *
 * A plain POST form target rather than a JSON endpoint, so the language
 * switcher works with JavaScript disabled - which is the whole reason
 * translation moved server-side. The modal posts this form; without JS
 * the same form is a normal submit.
 * ============================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../utilities/security.php';
require_once __DIR__ . '/../utilities/i18n.php';

mvcSessionStart();
mvcCsrfEnforce();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$lang = (string) ($_POST['lang'] ?? I18N_SOURCE);
// Same shape check i18n.php applies on read. This value ends up in a cookie
// that becomes a filesystem path, so it must never carry a separator.
if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z]{2,8})?$/', $lang)) {
    $lang = I18N_SOURCE;
}

$secure = (($_SERVER['HTTPS'] ?? '') === 'on')
       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if ($lang === I18N_SOURCE) {
    setcookie(I18N_COOKIE, '', [
        'expires' => time() - 3600, 'path' => '/', 'secure' => $secure,
        'httponly' => false, 'samesite' => 'Lax',
    ]);
} else {
    setcookie(I18N_COOKIE, $lang, [
        'expires'  => time() + 31536000,   // a year; a language choice is durable
        'path'     => '/',
        'secure'   => $secure,
        // Readable by JS so the switcher can show the current language without
        // a round trip. It is a display preference, not a credential.
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

/**
 * Return to the page they were on. Only ever a same-site PATH - never a
 * caller-supplied absolute URL, which would make this an open redirect.
 */
$to = (string) ($_POST['return'] ?? '/');
if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//')) {
    $to = '/';
}

header('Location: ' . $to, true, 303);
exit;
