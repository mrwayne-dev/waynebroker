<?php
require_once __DIR__ . '/../../../config/assets.php'; // asset cache-busting
require_once __DIR__ . '/../../../api/utilities/security.php'; // mvcCsrfToken()


// ============================================================
// ADMIN HEAD partial - $page_title, $page_description
// ============================================================
$page_title = $page_title ?? 'Maveren Capital Admin';
$page_description = $page_description ?? 'Maveren Capital administration.';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="author" content="Maveren Capital">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php // CSRF token for assets/js/api.js. mvcCsrfToken() starts a session if
          // one is not already open, so this works on anonymous pages (the
          // public contact form) as well as authenticated ones. ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(mvcCsrfToken(), ENT_QUOTES) ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#16232A">
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Theme: applied before paint so there is no flash of the wrong palette.
         Must stay INLINE and stay ahead of the stylesheets. -->
    <script>
      (function () {
        try {
          var t = localStorage.getItem('mvc-theme');
          document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
        } catch (e) { /* private mode - keep the dark default */ }
      })();
    </script>

    <!-- Critical CSS -->
    <?php // Plain stylesheets, not rel=preload + onload=this.rel='stylesheet'.
          // Two reasons. The onload attribute is an inline event handler, which
          // is precisely what CSP script-src 'unsafe-inline' had to keep
          // allowing. And the trick made these two load ASYNCHRONOUSLY while
          // every stylesheet below stayed blocking - so on a slow connection
          // bootstrap.css and dashboard.css could apply AFTER
          // mvc-dashboard.css and silently override the re-skin that is
          // supposed to win the cascade. Blocking is correct for critical CSS. ?>
    <link rel="stylesheet" href="<?= mvc_asset('/assets/css/bootstrap.css') ?>">
    <link rel="stylesheet" href="<?= mvc_asset('/assets/css/dashboard.css') ?>">

    <!-- Fonts (self-hosted: Switzer brand face).
         font.css sits immediately after the preloads on purpose: it holds the
         @font-face rules, and until it parses the browser has no reason to use
         the bytes it just fetched. Behind animation/bootstrap-select it landed
         late enough for the "preloaded but not used" warning to fire. -->
    <link rel="preload" href="/assets/fonts/Switzer-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Switzer-500.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Switzer-600.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= mvc_asset('/assets/fonts/font.css') ?>">

    <!-- Phosphor icons (self-hosted) -->
    <link rel="stylesheet" href="<?= mvc_asset('/assets/fonts/phosphor.css') ?>">

    <!-- Non-critical CSS -->
    <link rel="stylesheet" href="<?= mvc_asset('/assets/css/animation.min.css') ?>">
    <link rel="stylesheet" href="<?= mvc_asset('/assets/css/animation.css') ?>">
    <link rel="stylesheet" href="<?= mvc_asset('/assets/css/bootstrap-select.min.css') ?>">

    <!-- MVC design re-skin (loads last to win the cascade) -->
    <link rel="stylesheet" href="<?= mvc_asset('/assets/css/mvc-dashboard.css') ?>">
    <script src="<?= mvc_asset('/assets/js/theme.js') ?>" defer></script>
    <script src="<?= mvc_asset('/assets/js/dock.js') ?>" defer></script>
    <?php // mvcConfirm(): the themed replacement for window.confirm(). Loaded here
         // rather than per-page because four different scripts call it, and a
         // dialog that is missing simply because a page forgot the include is
         // a silently skipped confirmation on an action that moves money. ?>
    <script src="<?= mvc_asset('/assets/js/confirm.js') ?>" defer></script>

    <noscript>
        <link rel="stylesheet" href="<?= mvc_asset('/assets/css/bootstrap.css') ?>">
        <link rel="stylesheet" href="<?= mvc_asset('/assets/css/dashboard.css') ?>">
        <link rel="stylesheet" href="<?= mvc_asset('/assets/css/mvc-dashboard.css') ?>">
    </noscript>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/favicon/favicon-32x32.png" sizes="32x32">
    <link rel="shortcut icon" href="/assets/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-title" content="Maveren Capital">
    <link rel="manifest" href="/assets/favicon/site.webmanifest">
</head>
