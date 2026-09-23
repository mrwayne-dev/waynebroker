<?php
$page_title = 'Reset your password | Maveren Capital';
$page_description = 'Reset your Maveren Capital account password securely. Enter your email to receive a one-time reset code.';
$page_path = '/forgotpassword';
$nav_variant = 'solid';
include __DIR__ . '/_partials/head.php';
?>
<body class="mvc-redesign">

<?php include __DIR__ . '/_partials/navbar.php'; ?>

<main class="auth-page">
  <div class="container">
    <div class="form-card">
      <div class="form-card__header">
        <p class="eyebrow">
          <span class="eyebrow__icon"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg></span>
          Recovery
        </p>
        <h1>Reset your password</h1>
        <p>Enter your registered email and we'll send a one-time code.</p>
      </div>

      <!-- Step 1 - request OTP -->
      <form id="forgot-step1" class="form-stack" autocomplete="off">
        <div class="form-field">
          <label class="form-field__label" for="forgot-email">Email address</label>
          <input id="forgot-email" type="email" class="form-field__input" placeholder="you@example.com" required>
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
        Remembered it? <a href="/login">Sign in</a>
      </div>
    </div>
  </div>
</main>

<div id="toast-container"></div>
<div id="loader" class="hidden"><div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div></div>

<?php include __DIR__ . '/_partials/footer.php'; ?>

<script src="<?= mvc_asset('/assets/js/main.js') ?>" defer></script>
<script src="<?= mvc_asset('/assets/js/api.js') ?>" defer></script>
</body>
</html>
