<?php
// The hand-rolled <head> that used to live here loaded mvc-design.css ALONE.
// No data-theme attribute, so :root[data-theme="light"] could never match and
// this page was locked to dark with no toggle; no phosphor.css, so every toast
// icon rendered as tofu; no font.css, so Switzer never loaded. The public head
// partial does all four and is now shared.
$page_title       = 'Admin Sign in | Maveren Capital';
$page_description = 'Maveren Capital Admin. Manage the platform, users, and operations securely.';
$page_path        = '/admin.login';
$page_robots      = 'noindex, nofollow';
include __DIR__ . '/../public/_partials/head.php';
?>

<body class="mvc-redesign">

<?php // No navbar on these pages, so the toggle needs its own anchor.
      // theme.js binds any [data-theme-toggle]. ?>
<button type="button" class="theme-toggle auth-theme-toggle" data-theme-toggle aria-label="Toggle colour theme">
  <i class="ph ph-sun" aria-hidden="true"></i>
</button>

<main class="auth-page">
  <div class="container">
    <div class="form-card">
      <div class="form-card__header">
        <a href="/" class="auth-brand" aria-label="Maveren Capital home">
          <?php // Two-image swap on the mark. A single ink mark was invisible
                // against the dark .form-card; the name is text so it follows
                // --color-ink-primary and is readable in both themes. ?>
          <img class="auth-logo auth-logo--light" src="/assets/images/logo/mvc-mark-orange.png" width="128" height="128" alt="">
          <img class="auth-logo auth-logo--dark" src="/assets/images/logo/mvc-mark-ink.png" width="128" height="128" alt="">
          <span class="auth-wordmark">Maveren Capital</span>
        </a>
        <p class="eyebrow">
          <span class="eyebrow__icon"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg></span>
          Admin Console
        </p>
        <h1>Sign in to the console</h1>
        <p>Authorised administrators only.</p>
      </div>

      <form id="login-form" class="form-stack" autocomplete="off">
        <div class="form-field">
          <label class="form-field__label" for="email">Admin email</label>
          <input id="email" name="email" type="email" class="form-field__input" placeholder="admin@maverencapital.com" required>
        </div>

        <div class="form-field form-field--with-action">
          <label class="form-field__label" for="password">Password</label>
          <input id="password" name="password" type="password" class="form-field__input" placeholder="••••••••" required>
          <button type="button" class="form-field__action" aria-label="Show / hide password" aria-pressed="false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; font-size: var(--text-sm);">
          <label style="display: inline-flex; align-items: center; gap: var(--space-2); color: var(--color-ink-muted); cursor: pointer;">
            <input type="checkbox" id="terms" checked> Trusted device
          </label>
          <a href="/admin.forgotpassword" class="form-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn--primary" style="width: 100%;">Sign in</button>
      </form>

      <div class="form-footer">
        Need an admin account? <a href="/admin.register">Register</a>
        <span style="margin: 0 var(--space-2); opacity: 0.4;">·</span>
        <a href="/">Back to site</a>
      </div>
    </div>
  </div>
</main>

<div id="toast-container"></div>
<div id="loader" class="hidden"><div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div></div>

<script src="<?= mvc_asset('/assets/js/api.js') ?>" defer></script>
</body>
</html>
