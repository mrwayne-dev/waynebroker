<?php
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware: use_strict_mode, and a cookie_secure that
// survives a TLS-terminating proxy (the inline options this replaced
// tested $_SERVER['HTTPS'] === 'on', which is unset behind one).
mvcSessionStart();
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin.login');
    exit;
}
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
?>
<?php
  $page_title = "Deposit Addresses | Maveren Capital Admin";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
<div id="wrapper">
    <div id="page" class="">
        <div class="layout-wrap loader-off">
            <!-- Preloader -->
            <div id="preload" class="preload-container">
                <div class="preloading"><span></span></div>
            </div>

            <!-- Sidebar -->
            <?php $active = "deposit_addresses"; include __DIR__ . "/_partials/sidebar.php"; ?>
            <?php include __DIR__ . "/_partials/dock.php"; ?>
            <!-- /Sidebar -->

            <div class="section-content-right">
                <?php $page_heading = "Deposit Addresses"; include __DIR__ . "/_partials/topbar.php"; ?>

                <div class="main-content">
                    <div class="main-content-inner">
                        <div class="main-content-wrap">
                            <div class="tf-container">

                                <div class="wg-box mb-32">
                                    <div class="d-flex justify-between items-center gap-3 flex-wrap">
                                        <div>
                                            <h5 class="label-01 mb-4">Published deposit addresses</h5>
                                            <p class="f14-regular text-Gray mb-0">
                                                One address per asset and network. Only active rows are shown to members.
                                            </p>
                                        </div>
                                        <button id="add-address-btn" type="button" class="tf-button bg-Primary text-White f12-bold">
                                            <i class="ph ph-plus"></i> Add address
                                        </button>
                                    </div>
                                </div>

                                <div class="mvc-scroll-table">
                                    <table class="mvc-table">
                                        <thead>
                                            <tr>
                                                <th>Asset</th>
                                                <th>Network</th>
                                                <th>Label</th>
                                                <th>Address</th>
                                                <th>Minimum</th>
                                                <th>Status</th>
                                                <th>Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="deposit-address-rows">
                                            <tr><td class="mvc-empty" colspan="8">Loading addresses...</td></tr>
                                        </tbody>
                                    </table>
                                </div>


                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     ADD / EDIT ADDRESS
                     Authored with .mvc-field rather than the legacy .form-group
                     dialect, since this markup is new.
                     ============================================================ -->
                <?php // Not --wide: 720px was borrowed from the six-column pending-deposits
                      // table and left this two-column form with 330px of air per column.
                      // --compact drops the 24px display-weight field values to data-entry
                      // size so the whole form fits 540px without scrolling. ?>
                <div class="modal mvc-modal--compact" id="deposit-address-modal" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="modal-overlay" data-modal-close></div>
                    <div class="modal-content" tabindex="-1" aria-labelledby="deposit-address-modal-title">
                        <header class="modal-header">
                            <div>
                                <h2 id="deposit-address-modal-title">Add deposit address</h2>
                                <p class="modal-header__sub">Members copy this exactly as entered.</p>
                            </div>
                            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                        </header>
                        <div class="modal-body">
                            <form id="deposit-address-form" autocomplete="off">
                                <input type="hidden" id="address-id" value="">

                                <?php // The four controls an admin always touches stay visible;
                                      // the five they rarely touch live in the disclosure below.
                                      // All 11 at once overflowed the dialog on every screen. ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="address-asset">Asset</label>
                                            </div>
                                            <div class="mvc-field__row">
                                                <input class="mvc-field__input mvc-field__input--static mvc-field__input--short" type="text" id="address-asset"
                                                       placeholder="USDT" maxlength="12" spellcheck="false"
                                                       pattern="[A-Za-z0-9]{2,12}" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="address-network">Network</label>
                                            </div>
                                            <div class="mvc-field__row">
                                                <select class="mvc-field__input" id="address-network" required></select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mvc-field">
                                    <div class="mvc-field__top">
                                        <label class="mvc-field__label" for="address-label">Display label</label>
                                        <span class="mvc-field__hint">What members see</span>
                                    </div>
                                    <div class="mvc-field__row">
                                        <input class="mvc-field__input mvc-field__input--static" type="text" id="address-label"
                                               placeholder="USDT - TRC20 (Tron)" maxlength="80" required>
                                    </div>
                                </div>

                                <?php // maxlength matches the VARCHAR(255) column. Without it the
                                      // textarea accepted unlimited input and the server rejected
                                      // it only after a round trip. ?>
                                <div class="mvc-field mvc-field--textarea">
                                    <div class="mvc-field__top">
                                        <label class="mvc-field__label" for="address-value">Deposit address</label>
                                        <span class="mvc-field__hint">No spaces or line breaks</span>
                                    </div>
                                    <div class="mvc-field__row">
                                        <textarea class="mvc-field__input mvc-field__input--mono" id="address-value" rows="2"
                                                  maxlength="255" spellcheck="false" required
                                                  placeholder="TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE"></textarea>
                                    </div>
                                </div>

                                <details class="mvc-disclosure" id="address-optional">
                                    <summary>
                                        Optional settings
                                        <span class="mvc-disclosure__count" id="address-optional-count"></span>
                                    </summary>
                                    <div class="mvc-disclosure__body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mvc-field">
                                                    <div class="mvc-field__top">
                                                        <label class="mvc-field__label" for="address-memo-label">Memo / tag label</label>
                                                    </div>
                                                    <div class="mvc-field__row">
                                                        <select class="mvc-field__input" id="address-memo-label">
                                                            <option value="">Not required</option>
                                                            <option value="Memo">Memo</option>
                                                            <option value="Destination Tag">Destination Tag</option>
                                                            <option value="Payment ID">Payment ID</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mvc-field">
                                                    <div class="mvc-field__top">
                                                        <label class="mvc-field__label" for="address-memo">Memo / tag value</label>
                                                    </div>
                                                    <div class="mvc-field__row">
                                                        <input class="mvc-field__input mvc-field__input--mono" type="text" id="address-memo"
                                                               maxlength="120" spellcheck="false" placeholder="Leave blank if unused">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php // Placeholders, not values. A bold "0.00" reads as
                                              // "someone set this to zero"; an empty field reads as
                                              // "no minimum", which is what zero actually means.
                                              // Both the JS and the server already coerce empty to 0. ?>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mvc-field">
                                                    <div class="mvc-field__top">
                                                        <label class="mvc-field__label" for="address-min">Minimum deposit</label>
                                                    </div>
                                                    <div class="mvc-field__row">
                                                        <input class="mvc-field__input mvc-field__input--compact" type="number" id="address-min"
                                                               min="0" step="0.01" placeholder="0.00" inputmode="decimal">
                                                        <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mvc-field">
                                                    <div class="mvc-field__top">
                                                        <label class="mvc-field__label" for="address-confirmations">Confirmations</label>
                                                        <span class="mvc-field__hint">0 hides it</span>
                                                    </div>
                                                    <div class="mvc-field__row">
                                                        <input class="mvc-field__input mvc-field__input--compact" type="number" id="address-confirmations"
                                                               min="0" max="255" step="1" placeholder="0">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mvc-field mvc-field--textarea">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="address-instructions">Instructions</label>
                                            </div>
                                            <div class="mvc-field__row">
                                                <textarea class="mvc-field__input" id="address-instructions" rows="2" maxlength="500"
                                                          placeholder="Send only USDT on the Tron network. Other assets sent here are lost."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </details>

                                <?php // Visibility stays out of the disclosure: it decides whether
                                      // members see the row at all. min/max match the SMALLINT
                                      // column - 99999 threw PDO 22003, which the endpoint's
                                      // duplicate-key handler does not catch, so it surfaced as a
                                      // generic 500. ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <span class="mvc-field__label" id="address-active-label">Visibility</span>
                                            </div>
                                            <div class="mvc-segment" id="address-active" role="group" aria-labelledby="address-active-label">
                                                <button type="button" class="mvc-segment__btn is-active" data-active="1" aria-pressed="true">Active</button>
                                                <button type="button" class="mvc-segment__btn" data-active="0" aria-pressed="false">Hidden</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="address-sort">Sort order</label>
                                                <span class="mvc-field__hint">Lower first</span>
                                            </div>
                                            <div class="mvc-field__row">
                                                <input class="mvc-field__input mvc-field__input--compact" type="number" id="address-sort"
                                                       min="0" max="999" step="1" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <?php // Outside .modal-body so it cannot scroll out of view. ?>
                        <div class="modal-footer-actions">
                            <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                            <button type="submit" form="deposit-address-form" class="modal-confirm-btn" id="save-address-btn">Save address</button>
                        </div>
                    </div>
                </div>

                <!-- DELETE CONFIRMATION -->
                <div class="modal mvc-modal--danger" id="delete-address-modal" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="modal-overlay" data-modal-close></div>
                    <div class="modal-content" tabindex="-1" aria-labelledby="delete-address-title">
                        <header class="modal-header">
                            <h2 id="delete-address-title">Delete address</h2>
                            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                        </header>
                        <div class="modal-body">
                            <p>Remove <strong id="delete-address-label"></strong> from the published list?</p>
                            <p class="note">Deposits already created keep the address they were issued, so nothing a member has been told will change.</p>
                        </div>

                        <?php // Same pinned footer as its sibling dialog. It used to sit
                              // inside .modal-body, so the two modals on this page put
                              // their buttons in different places. ?>
                        <div class="modal-footer-actions">
                            <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                            <button type="button" class="modal-confirm-btn" id="confirm-delete-address">Delete address</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Loader & Toast -->
<div id="loader" class="hidden">
    <div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
</div>
<div id="toast-container"></div>

<!-- Scripts -->
<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/deposit_addresses.js') ?>" defer></script>
</body>
</html>
