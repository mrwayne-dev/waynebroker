/* =================================================================
 * FILE: /assets/js/theme.js
 * Maveren Capital - light/dark theme toggle
 *
 * The theme is applied by a tiny inline script in each <head>
 * partial BEFORE paint (see _partials/head.php), so this file only
 * has to wire up the toggle buttons and keep tabs in sync.
 *
 * Contract:
 *   - <html data-theme="dark|light">  is the single source of truth
 *   - localStorage key 'mvc-theme' persists the choice
 *   - dark is the default when nothing is stored
 *   - any element with [data-theme-toggle] flips the theme
 * ================================================================= */
(function () {
  'use strict';

  var STORAGE_KEY = 'mvc-theme';
  var root = document.documentElement;

  function current() {
    return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  }

  // Must match --Bg in mvc-dashboard.css / --color-bg-page in mvc-design.css.
  var THEME_COLOR = { dark: '#16232A', light: '#F4F8F9' };

  // The <meta name="theme-color"> is hardcoded to the dark ink in every head
  // partial, so the mobile browser chrome stayed dark in the light theme.
  function syncThemeColor(theme) {
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', THEME_COLOR[theme] || THEME_COLOR.dark);
  }

  function apply(theme, persist) {
    root.setAttribute('data-theme', theme);
    if (persist) {
      try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) { /* private mode */ }
    }
    syncButtons(theme);
    syncThemeColor(theme);
    document.dispatchEvent(new CustomEvent('mvc:themechange', { detail: { theme: theme } }));
  }

  // Keep every toggle's icon + a11y state in step with the active theme.
  function syncButtons(theme) {
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < buttons.length; i++) {
      var btn = buttons[i];
      btn.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
      btn.setAttribute('aria-label', theme === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
      btn.setAttribute('title', theme === 'light' ? 'Dark mode' : 'Light mode');

      var icon = btn.querySelector('i');
      if (icon) {
        // Phosphor: sun when currently light (click -> dark), moon when dark.
        icon.className = theme === 'light' ? 'ph ph-moon' : 'ph ph-sun';
      }
    }
  }

  function toggle() {
    apply(current() === 'light' ? 'dark' : 'light', true);
  }

  function init() {
    syncButtons(current());
    syncThemeColor(current());

    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
      if (btn) {
        e.preventDefault();
        toggle();
      }
    });

    // Mirror the choice across open tabs.
    window.addEventListener('storage', function (e) {
      if (e.key === STORAGE_KEY && e.newValue) {
        apply(e.newValue === 'light' ? 'light' : 'dark', false);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.mvcTheme = { get: current, set: function (t) { apply(t, true); }, toggle: toggle };
})();
