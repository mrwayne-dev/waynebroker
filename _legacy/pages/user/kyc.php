<?php
// pages/user/kyc.php
//
// Identity verification. The page renders the member's CURRENT state
// server-side rather than fetching it - a member arriving here after a blocked
// withdrawal must see why immediately, not after a round trip.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$user_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$user_id   = (int) $_SESSION['user_id'];

$kyc_status = 'none';
$latest     = null;
$prefill    = ['full_name' => '', 'country' => ''];

try {
    $pdo = getPDO();

    $st = $pdo->prepare("SELECT kyc_status, full_name, name, country FROM users WHERE id = ?");
    $st->execute([$user_id]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        $kyc_status = $u['kyc_status'] ?: 'none';
        $prefill['full_name'] = $u['full_name'] ?: ($u['name'] ?: '');
        $prefill['country']   = $u['country'] ?: '';
    }

    $st = $pdo->prepare(
        "SELECT id_type, status, reject_reason, created_at, reviewed_at
           FROM kyc_submissions WHERE user_id = ?
          ORDER BY created_at DESC, id DESC LIMIT 1"
    );
    $st->execute([$user_id]);
    $latest = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('kyc.php page: ' . $e->getMessage());
}

// Approved and pending members must not be able to queue a second review.
$can_submit = in_array($kyc_status, ['none', 'rejected'], true);

$badge = [
    'none'     => ['Not started', 'mvc-kyc-badge--none'],
    'pending'  => ['Under review', 'mvc-kyc-badge--pending'],
    'approved' => ['Verified',     'mvc-kyc-badge--approved'],
    'rejected' => ['Action needed','mvc-kyc-badge--rejected'],
][$kyc_status] ?? ['Not started', 'mvc-kyc-badge--none'];
?>
<?php
  $page_title = "Identity Verification | Maveren Capital";
  include __DIR__ . "/_partials/head.php";
?>

<body class="counter-scroll mvc-dash">
    <div id="wrapper">
        <div id="page" class="">
            <div class="layout-wrap loader-off">
                <div id="preload" class="preload-container">
                    <div class="preloading"><span></span></div>
                </div>

                <?php $active = "kyc"; include __DIR__ . "/_partials/sidebar.php"; ?>
                <?php include __DIR__ . "/_partials/dock.php"; ?>

                <div class="section-content-right">
                    <?php $page_heading = "Identity Verification"; include __DIR__ . "/_partials/topbar.php"; ?>

                    <div class="main-content">
                        <div class="main-content-inner">
                            <div class="main-content-wrap">
                                <div class="tf-container">

                                    <div class="mvc-verify">

                                      <?php // ---------------------------------------------
                                            // STATUS
                                            // Compact strip, not a panel. The old version was
                                            // a full card whose heading, badge and one line of
                                            // text left most of it empty.
                                            // --------------------------------------------- ?>
                                      <div class="mvc-verify-status mvc-verify-status--<?= htmlspecialchars($kyc_status) ?>">
                                        <i class="ph <?= [
                                              'approved' => 'ph-seal-check',
                                              'pending'  => 'ph-hourglass-medium',
                                              'rejected' => 'ph-warning-circle',
                                          ][$kyc_status] ?? 'ph-shield-check' ?> mvc-verify-status__icon" aria-hidden="true"></i>
                                        <div class="mvc-verify-status__body">
                                          <?php if ($kyc_status === 'approved'): ?>
                                            <div class="mvc-verify-status__title">Your identity is verified</div>
                                            <div class="mvc-verify-status__text">Withdrawals are open on your account. Nothing further is needed.</div>
                                          <?php elseif ($kyc_status === 'pending'): ?>
                                            <div class="mvc-verify-status__title">Under review</div>
                                            <div class="mvc-verify-status__text">
                                              Usually decided within one business day, and we will email you either way.
                                              Deposits and investments stay open; withdrawals unlock on approval.
                                            </div>
                                          <?php elseif ($kyc_status === 'rejected'): ?>
                                            <div class="mvc-verify-status__title">We could not verify your last submission</div>
                                            <div class="mvc-verify-status__text">
                                              <?= htmlspecialchars($latest['reject_reason'] ?? 'Please resubmit clearer documents.') ?>
                                              You can send new documents below.
                                            </div>
                                          <?php else: ?>
                                            <div class="mvc-verify-status__title">Verify your identity to unlock withdrawals</div>
                                            <div class="mvc-verify-status__text">
                                              Takes about two minutes. Deposits and investments are open either way.
                                            </div>
                                          <?php endif; ?>
                                        </div>
                                        <span class="mvc-kyc-badge <?= $badge[1] ?>"><?= htmlspecialchars($badge[0]) ?></span>
                                      </div>

                                      <?php if ($can_submit): ?>
                                      <section class="mvc-panel">
                                        <?php // A member filling an identity form wants to know how
                                              // much is left. The old page numbered two column
                                              // headings and called it a step indicator. ?>
                                        <div class="mvc-steps" aria-label="Verification steps">
                                          <div class="mvc-steps__item is-current">
                                            <span class="mvc-steps__n">1</span>
                                            <span class="mvc-steps__label">Your details</span>
                                          </div>
                                          <span class="mvc-steps__line"></span>
                                          <div class="mvc-steps__item is-current">
                                            <span class="mvc-steps__n">2</span>
                                            <span class="mvc-steps__label">Documents</span>
                                          </div>
                                          <span class="mvc-steps__line"></span>
                                          <div class="mvc-steps__item">
                                            <span class="mvc-steps__n">3</span>
                                            <span class="mvc-steps__label">Review</span>
                                          </div>
                                        </div>

                                        <form id="kyc-form" enctype="multipart/form-data">

                                          <div class="mvc-fieldset">
                                            <span class="mvc-fieldset__legend">Your details</span>

                                            <div class="mvc-field">
                                              <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="kyc-full-name">Full name, exactly as printed on the document</label>
                                              </div>
                                              <div class="mvc-field__row">
                                                <input type="text" id="kyc-full-name" name="full_name" maxlength="150" required
                                                       class="mvc-field__input" value="<?= htmlspecialchars($prefill['full_name']) ?>">
                                              </div>
                                            </div>

                                            <div class="mvc-grid-2">
                                              <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                  <label class="mvc-field__label" for="kyc-dob">Date of birth</label>
                                                  <span class="mvc-field__hint">18 or over</span>
                                                </div>
                                                <div class="mvc-field__row">
                                                  <input type="date" id="kyc-dob" name="date_of_birth" required class="mvc-field__input">
                                                </div>
                                              </div>

                                              <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                  <label class="mvc-field__label" for="kyc-country">Country of issue</label>
                                                </div>
                                                <div class="mvc-field__row">
                                                  <input type="text" id="kyc-country" name="country" maxlength="80" required
                                                         class="mvc-field__input" autocomplete="country-name"
                                                         value="<?= htmlspecialchars($prefill['country']) ?>">
                                                </div>
                                              </div>
                                            </div>

                                            <div class="mvc-grid-2">
                                              <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                  <label class="mvc-field__label" for="kyc-id-type">Document type</label>
                                                </div>
                                                <div class="mvc-field__row">
                                                  <select id="kyc-id-type" name="id_type" required class="mvc-field__input">
                                                    <option value="">Choose a document</option>
                                                    <option value="passport">Passport</option>
                                                    <option value="national_id">National ID card</option>
                                                    <option value="drivers_license">Driver's licence</option>
                                                  </select>
                                                </div>
                                              </div>

                                              <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                  <label class="mvc-field__label" for="kyc-id-number">Document number</label>
                                                </div>
                                                <div class="mvc-field__row">
                                                  <input type="text" id="kyc-id-number" name="id_number" maxlength="80" required
                                                         class="mvc-field__input mvc-field__input--mono" autocomplete="off">
                                                </div>
                                              </div>
                                            </div>
                                          </div>

                                          <div class="mvc-fieldset">
                                            <span class="mvc-fieldset__legend">Your documents</span>

                                            <div class="mvc-uploads">
                                              <div class="mvc-upload" data-kyc-drop="doc_front">
                                                <input type="file" name="doc_front" id="kyc-doc-front" hidden
                                                       accept="image/png,image/jpeg,image/webp,application/pdf">
                                                <img class="mvc-upload__preview" alt="" hidden>
                                                <div class="mvc-upload__empty">
                                                  <i class="ph ph-identification-card mvc-upload__icon" aria-hidden="true"></i>
                                                  <div class="mvc-upload__name">Front of document</div>
                                                  <div class="mvc-upload__hint">PNG, JPG, WEBP or PDF</div>
                                                </div>
                                              </div>

                                              <?php // A passport is one page; the other two have a reverse.
                                                    // Shown by kyc.js when the type is chosen. ?>
                                              <div class="mvc-upload" data-kyc-drop="doc_back" id="kyc-back-wrap" hidden>
                                                <input type="file" name="doc_back" id="kyc-doc-back" hidden
                                                       accept="image/png,image/jpeg,image/webp,application/pdf">
                                                <img class="mvc-upload__preview" alt="" hidden>
                                                <div class="mvc-upload__empty">
                                                  <i class="ph ph-identification-card mvc-upload__icon" aria-hidden="true"></i>
                                                  <div class="mvc-upload__name">Back of document</div>
                                                  <div class="mvc-upload__hint">PNG, JPG, WEBP or PDF</div>
                                                </div>
                                              </div>

                                              <div class="mvc-upload" data-kyc-drop="selfie">
                                                <input type="file" name="selfie" id="kyc-selfie" hidden
                                                       accept="image/png,image/jpeg,image/webp">
                                                <img class="mvc-upload__preview" alt="" hidden>
                                                <div class="mvc-upload__empty">
                                                  <i class="ph ph-user-focus mvc-upload__icon" aria-hidden="true"></i>
                                                  <div class="mvc-upload__name">Selfie holding it</div>
                                                  <div class="mvc-upload__hint">PNG, JPG or WEBP</div>
                                                </div>
                                              </div>
                                            </div>

                                            <p class="mvc-verify-note">
                                              <i class="ph ph-lightbulb" aria-hidden="true"></i>
                                              <span>Photograph the document flat and in good light, with all four
                                              corners visible and nothing covering the text. Each file up to 10&nbsp;MB.</span>
                                            </p>
                                          </div>

                                          <p class="mvc-verify-note">
                                            <i class="ph ph-lock-simple" aria-hidden="true"></i>
                                            <span>Your documents are visible only to our compliance team, are never
                                            shown on your profile, and are not reachable by anyone with a link.</span>
                                          </p>

                                          <button type="submit" id="kyc-submit" class="tf-button bg-Primary f14-bold w-full mt-16">
                                            <i class="ph ph-shield-check" aria-hidden="true"></i> Submit for review
                                          </button>
                                        </form>
                                      </section>
                                      <?php endif; ?>

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
    <script src="<?= mvc_asset('../../assets/js/kyc.js') ?>" defer></script>
</body>
</html>
