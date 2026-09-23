/**
 * ============================================================
 * Smartsupp Live Chat loader
 *
 * EXTERNAL FILE, not an inline <script>, on purpose.
 *
 * The CSP in .htaccess dropped script-src 'unsafe-inline'; the five remaining
 * inline blocks are allow-listed by sha256 hash. Pasting the vendor snippet
 * inline would mean adding a sixth hash, and re-computing it every time the
 * key or the snippet changed - a silent breakage waiting to happen, because a
 * stale hash blocks the script with no error anywhere except the console.
 * An external file under /assets is covered by script-src 'self' and needs no
 * maintenance. The loader.js it injects is covered by the
 * https://*.smartsuppchat.com entry.
 *
 * This replaces the copy that used to live in main.js, which:
 *   - carried a DIFFERENT key (acee1c8f...), and
 *   - omitted the command-queue stub below, so any smartsupp('name', ...)
 *     call made before loader.js arrived threw a ReferenceError.
 *
 * Loaded from:
 *   pages/public/_partials/head.php   - all 10 marketing pages
 *   pages/user/_partials/head.php     - all 5 member dashboard pages
 * ============================================================
 */
(function (d) {
  // Already initialised (double include, or a bfcache restore).
  if (window.smartsupp) return;

  window._smartsupp = window._smartsupp || {};
  window._smartsupp.key = '69ff5cd89049df1e6ee492fa893cbe52bdb8898f';

  // Command queue. Anything called before loader.js lands is buffered on
  // smartsupp._ and replayed by the vendor script once it initialises. This
  // is what makes `smartsupp('chat:show')` safe to call from anywhere.
  // Assigned via window.* rather than the vendor's bare `o = smartsupp = ...`
  // so the file survives being loaded as a module or under strict mode.
  var q = function () { q._.push(arguments); };
  q._ = [];
  window.smartsupp = q;

  var c = d.createElement('script');
  c.type = 'text/javascript';
  c.charset = 'utf-8';
  c.async = true;
  c.src = 'https://www.smartsuppchat.com/loader.js?';

  // The vendor snippet does insertBefore(c, scripts[0]) and assumes a script
  // tag already exists. That holds today, but this file is deferred and a
  // future page could load it as the only script - so fall back to <head>.
  var first = d.getElementsByTagName('script')[0];
  if (first && first.parentNode) {
    first.parentNode.insertBefore(c, first);
  } else {
    (d.head || d.documentElement).appendChild(c);
  }
})(document);
