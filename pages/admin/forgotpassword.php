<?php
// The hand-rolled <head> that used to live here loaded mvc-design.css ALONE.
// No data-theme attribute, so :root[data-theme="light"] could never match and
// this page was locked to dark with no toggle; no phosphor.css, so every toast
// icon rendered as tofu; no font.css, so Switzer never loaded. The public head
// partial does all four and is now shared.
$page_title       = 'Admin Password Reset | Maveren Capital';
$page_description = 'Reset your Maveren Capital administrator password.';
$page_path        = '/admin.forgotpassword';
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
        <h1>Reset admin password</h1>
        <p>Enter your admin email to receive a one-time code.</p>
      </div>

      <!-- Step 1 - request OTP -->
      <form id="forgot-step1" class="form-stack" autocomplete="off">
        <div class="form-field">
          <label class="form-field__label" for="forgot-email">Admin email</label>
          <input id="forgot-email" type="email" class="form-field__input" placeholder="admin@maverencapital.com" required>
        </div>
        <button type="submit" class="btn btn--primary" style="width: 100%;">Send code</button>
      </form>

      <!-- Step 2 - verify OTP -->
      <form id="forgot-step2" class="form-stack hidden" autocomplete="off" style="margin-top: var(--space-5);">
        <div class="form-field">
          <label class="form-field__label" for="otp">Enter the 6-digit code</label>
          <input id="otp" type="text" class="form-field__input" placeholder="••••••" maxlength="6" required>
          <p class="form-field__hint">Check your inbox (and spam folder).</p>
        </div>
        <button type="submit" class="btn btn--primary" style="width: 100%;">Verify code</button>
      </form>

      <!-- Step 3 - set new password -->
      <form id="forgot-step3" class="form-stack hidden" autocomplete="off" style="margin-top: var(--space-5);">
        <div class="form-field">
          <label class="form-field__label" for="new_password">New password</label>
          <input id="new_password" type="password" class="form-field__input" placeholder="••••••••" required>
          <p class="form-field__hint">Minimum 8 characters. Include a number or symbol.</p>
        </div>
        <button type="submit" class="btn btn--primary" style="width: 100%;">Reset password</button>
      </form>

      <div class="form-footer">
        Remembered it? <a href="/admin.login">Sign in</a>
      </div>
    </div>
  </div>
</main>

<div id="toast-container"></div>
<div id="loader" class="hidden"><div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div></div>

<script src="<?= mvc_asset('/assets/js/api.js') ?>" defer></script>
</body>
</html>
