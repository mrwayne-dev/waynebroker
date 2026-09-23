<?php
// The hand-rolled <head> that used to live here loaded mvc-design.css ALONE.
// No data-theme attribute, so :root[data-theme="light"] could never match and
// this page was locked to dark with no toggle; no phosphor.css, so every toast
// icon rendered as tofu; no font.css, so Switzer never loaded. The public head
// partial does all four and is now shared.
$page_title       = 'Admin Register | Maveren Capital';
$page_description = 'Create an Maveren Capital administrator account.';
$page_path        = '/admin.register';
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
        <h1>Create an admin account</h1>
        <p>Restricted. Requires authorisation.</p>
      </div>

      <form id="register-form" class="form-stack" autocomplete="off">
        <div class="form-field">
          <label class="form-field__label" for="username">Username</label>
          <input id="username" name="username" type="text" class="form-field__input" placeholder="jane.admin" required>
        </div>

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
          <p class="form-field__hint">Minimum 8 characters. Include a number or symbol.</p>
        </div>

        <div class="form-field">
          <label class="form-field__label" for="invite_code">Admin invite code</label>
          <input id="invite_code" name="invite_code" type="text" class="form-field__input" placeholder="Enter your invite code" autocomplete="off" required>
          <p class="form-field__hint">Required. Provided by a platform administrator.</p>
        </div>

        <label style="display: inline-flex; align-items: flex-start; gap: var(--space-2); font-size: var(--text-sm); color: var(--color-ink-muted); cursor: pointer; line-height: 1.5;">
          <input type="checkbox" id="terms" checked style="margin-top: 3px;">
          <span>I confirm I am authorised to administer the Maveren Capital platform.</span>
        </label>

        <button type="submit" class="btn btn--primary" style="width: 100%;">Create admin account</button>
      </form>

      <div class="form-footer">
        Already have an admin account? <a href="/admin.login">Sign in</a>
      </div>
    </div>
  </div>
</main>

<div id="toast-container"></div>
<div id="loader" class="hidden"><div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div></div>

<script src="<?= mvc_asset('/assets/js/api.js') ?>" defer></script>
</body>
</html>
