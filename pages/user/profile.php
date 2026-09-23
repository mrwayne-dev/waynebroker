<?php
// pages/user/profile.php

require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware (use_strict_mode, and cookie_secure that survives
// a TLS-terminating proxy - the inline options this replaced tested
// $_SERVER['HTTPS'] === 'on', which is unset there).
mvcSessionStart();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$user_id   = $_SESSION['user_id'] ?? null;
$avatar    = htmlspecialchars($_SESSION['profile_picture'] ?? '/assets/images/avatar/default.png');
?>
<?php
  $page_title = "Profile | Maveren Capital";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
<div id="wrapper">
  <div id="page">
    <div class="layout-wrap loader-off">
      <div id="preload" class="preload-container">
        <div class="preloading"><span></span></div>
      </div>

      <?php $active = "profile"; include __DIR__ . "/_partials/sidebar.php"; ?>
      <?php include __DIR__ . "/_partials/dock.php"; ?>

      <div class="section-content-right">
        <?php $page_heading = "Profile"; include __DIR__ . "/_partials/topbar.php"; ?>

        <div class="main-content">
          <div class="main-content-inner">
            <div class="main-content-wrap">
              <div class="tf-container">
                <div class="mvc-stack">

                  <?php // PHOTO ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Profile photo</h2>
                    </div>

                    <div class="mvc-avatar-row">
                      <img id="avatar-preview" class="mvc-avatar" src="<?= $avatar ?>" alt=""
                           data-fallback-src="/assets/images/avatar/default.png">
                      <form id="avatar-form" enctype="multipart/form-data" class="mvc-avatar-actions">
                        <input type="file" id="avatar-input" name="avatar" accept="image/png,image/jpeg,image/webp" hidden>
                        <p class="mvc-notice__body">
                          Square works best. PNG, JPG or WEBP, up to 10&nbsp;MB &mdash; it is
                          cropped and resized on upload.
                        </p>
                        <div class="mvc-actions">
                          <button type="button" id="avatar-choose" class="tf-button bg-Accent f14-bold">
                            <i class="ph ph-image" aria-hidden="true"></i> Choose photo
                          </button>
                          <button type="submit" id="avatar-upload" class="tf-button bg-Primary f14-bold" disabled>
                            Upload
                          </button>
                        </div>
                      </form>
                    </div>
                  </section>

                  <?php // DETAILS ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Your details</h2>
                    </div>

                    <form id="profile-form">
                      <div class="mvc-form-grid">
                        <div class="mvc-field">
                          <div class="mvc-field__top"><label class="mvc-field__label" for="pf-full-name">Full name</label></div>
                          <div class="mvc-field__row"><input type="text" id="pf-full-name" class="mvc-field__input" maxlength="100"></div>
                        </div>
                        <div class="mvc-field">
                          <div class="mvc-field__top">
                            <label class="mvc-field__label" for="pf-email">Email</label>
                            <span class="mvc-field__hint">Contact support to change</span>
                          </div>
                          <div class="mvc-field__row"><input type="email" id="pf-email" class="mvc-field__input" readonly></div>
                        </div>
                        <div class="mvc-field">
                          <div class="mvc-field__top"><label class="mvc-field__label" for="pf-phone">Phone</label></div>
                          <div class="mvc-field__row"><input type="tel" id="pf-phone" class="mvc-field__input" maxlength="40" autocomplete="tel"></div>
                        </div>
                        <div class="mvc-field">
                          <div class="mvc-field__top"><label class="mvc-field__label" for="pf-country">Country</label></div>
                          <div class="mvc-field__row"><input type="text" id="pf-country" class="mvc-field__input" maxlength="80" autocomplete="country-name"></div>
                        </div>
                        <div class="mvc-field">
                          <div class="mvc-field__top"><label class="mvc-field__label" for="pf-location">Location</label></div>
                          <div class="mvc-field__row"><input type="text" id="pf-location" class="mvc-field__input" maxlength="255" autocomplete="address-level2"></div>
                        </div>
                        <div class="mvc-field mvc-field--full">
                          <div class="mvc-field__top"><label class="mvc-field__label" for="pf-address">Address</label></div>
                          <div class="mvc-field__row"><input type="text" id="pf-address" class="mvc-field__input" maxlength="255" autocomplete="street-address"></div>
                        </div>
                      </div>

                      <div class="mvc-actions mvc-actions--end">
                        <button type="submit" id="profile-save-btn" class="tf-button bg-Primary f14-bold">Save changes</button>
                      </div>
                    </form>
                  </section>

                  <?php // PASSWORD ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Password</h2>
                    </div>

                    <form id="password-form">
                      <div class="mvc-form-grid">
                        <div class="mvc-field">
                          <div class="mvc-field__top"><label class="mvc-field__label" for="pf-current-password">Current password</label></div>
                          <div class="mvc-field__row"><input type="password" id="pf-current-password" class="mvc-field__input" autocomplete="current-password"></div>
                        </div>
                        <div class="mvc-field">
                          <div class="mvc-field__top">
                            <label class="mvc-field__label" for="pf-new-password">New password</label>
                            <span class="mvc-field__hint">8 characters minimum</span>
                          </div>
                          <div class="mvc-field__row"><input type="password" id="pf-new-password" class="mvc-field__input" autocomplete="new-password"></div>
                        </div>
                        <div class="mvc-field">
                          <div class="mvc-field__top"><label class="mvc-field__label" for="pf-confirm-password">Confirm new password</label></div>
                          <div class="mvc-field__row"><input type="password" id="pf-confirm-password" class="mvc-field__input" autocomplete="new-password"></div>
                        </div>
                      </div>

                      <p class="mvc-verify-note">
                        <i class="ph ph-shield-check" aria-hidden="true"></i>
                        <span>Changing your password signs out your other sessions.</span>
                      </p>

                      <div class="mvc-actions mvc-actions--end">
                        <button type="submit" id="password-save-btn" class="tf-button bg-Primary f14-bold">Update password</button>
                      </div>
                    </form>
                  </section>

                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="loader" class="hidden">
  <div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
</div>
<div id="toast-container"></div>

<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/dashboard.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/profile.js') ?>" defer></script>
</body>
</html>
