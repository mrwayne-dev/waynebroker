<?php
// ============================================================
// FILE: /config/assets.php
// PURPOSE: Automatic per-file asset cache-busting.
//
// mvc_asset('/assets/js/x.js') -> '/assets/js/x.js?v=<filemtime>'
//
// The version is the file's modification time, so editing a CSS/JS file
// changes its URL and browsers re-fetch it - no manual version bumping,
// and unchanged files keep their long cache. Works for both absolute
// ("/assets/..") and relative ("../../assets/..") URL forms.
// ============================================================

if (!function_exists('mvc_asset')) {
    function mvc_asset(string $url): string {
        $pos = strpos($url, 'assets/');
        if ($pos === false) {
            return $url; // not a local asset (e.g. a CDN URL) - leave untouched
        }
        $fsPath = dirname(__DIR__) . '/' . substr($url, $pos); // <project root>/assets/...
        $mtime  = @filemtime($fsPath);
        return $mtime ? ($url . '?v=' . $mtime) : $url;
    }
}
