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
  $page_title = "Investment Plans | Maveren Capital Admin";
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
            <?php $active = "plans"; include __DIR__ . "/_partials/sidebar.php"; ?>
            <?php include __DIR__ . "/_partials/dock.php"; ?>
            <!-- /Sidebar -->

            <!-- Main Content -->
            <div class="section-content-right">
                <!-- Header -->
                <?php $page_heading = "Investment Plans"; include __DIR__ . "/_partials/topbar.php"; ?>
                <!-- /Header -->

                <!-- Main Content -->
                <div class="main-content">
                    <div class="main-content-inner">
                        <div class="main-content-wrap">
                            <div class="tf-container">

                                <!-- 1. SUMMARY CARDS -->
                                <div class="row mb-32">
                                    <div class="col-12">
                                        <div class="wallet-cards grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap20">
                                            <!-- Total capital invested -->
                                            <div class="wallet-card wallet-main">
                                                <div class="wallet-card-header">Capital invested</div>
                                                <div class="wallet-card-balance">$<span id="total-active-invest">0.00</span></div>
                                                <div class="wallet-card-footer"><i class="ph ph-trend-up"></i> Locked</div>
                                            </div>
                                            <!-- Total ROI Paid Out -->
                                            <div class="wallet-card wallet-green">
                                                <div class="wallet-card-header">Total ROI Paid</div>
                                                <div class="wallet-card-balance">$<span id="total-roi-paid">0.00</span></div>
                                                <div class="wallet-card-footer"><i class="ph ph-check-circle"></i> Distributed</div>
                                            </div>
                                            <!-- Ongoing Plans -->
                                            <div class="wallet-card wallet-accent">
                                                <div class="wallet-card-header">Ongoing Plans</div>
                                                <div class="wallet-card-balance"><span id="ongoing-plans">0</span></div>
                                                <div class="wallet-card-footer"><i class="ph ph-hourglass"></i> Users</div>
                                            </div>
                                            <!-- Next Maturity -->
                                            <div class="wallet-card wallet-purple">
                                                <div class="wallet-card-header">Next Maturity</div>
                                                <div class="wallet-card-balance"><span id="next-maturity">—</span></div>
                                                <div class="wallet-card-footer"><i class="ph ph-calendar-x"></i> Upcoming</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. INVESTMENT PLANS MANAGER -->
                                <div class="mb-32">
                                    <div class="d-flex justify-between items-center mb-16">
                                        <h5 class="label-01">Plan catalogue</h5>
                                        <button id="add-plan-btn" class="tf-button bg-Primary text-White f12-bold">
                                            <i class="ph ph-plus"></i> Add New Plan
                                        </button>
                                    </div>

                                    <?php // See the note on the users table: the div-grid header
                                          // and the table body were sized independently. ?>
                                    <div class="mvc-scroll-table">
                                        <table class="mvc-table">
                                            <thead>
                                                <tr>
                                                    <th>Plan Name</th>
                                                    <th>Term</th>
                                                    <th>ROI</th>
                                                    <th>Risk Level</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="plans-table-body">
                                                <tr><td class="mvc-empty" colspan="6">Loading plans...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- 4. ACTIVE INVESTMENTS TABLE -->
                                <div class="mb-32">
                                    <h5 class="label-01 mb-16">All active positions</h5>
                                    <?php // 8 columns. The old div-grid CSS only defined widths for
                                          // nth-child(1)-(7), so the Actions column sized itself
                                          // independently in the header and the body. ?>
                                    <div class="mvc-scroll-table">
                                        <table class="mvc-table">
                                            <thead>
                                                <tr>
                                                    <th>Member</th>
                                                    <th>Plan</th>
                                                    <th>Amount</th>
                                                    <th>ROI</th>
                                                    <th>Status</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="active-investments-body">
                                                <tr><td class="mvc-empty" colspan="8">Loading active investments...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="active-pagination"></div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Main Content -->

                <!-- MODALS -->

                <?php // Add/Edit Plan. Rebuilt on .mvc-field; the inline
                      // `style="max-width:600px"` is replaced by .mvc-modal--wide,
                      // which is guarded by min-width:576px so it cannot pin a
                      // desktop width onto the phone bottom sheet. ?>
                <div class="modal mvc-modal--wide mvc-modal--compact" id="plan-modal" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="modal-overlay" data-modal-close></div>
                    <div class="modal-content" tabindex="-1" aria-labelledby="plan-modal-title">
                        <header class="modal-header">
                            <div>
                                <h2 id="plan-modal-title">Add New Plan</h2>
                                <p class="modal-header__sub">Active plans are selectable by members immediately.</p>
                            </div>
                            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                        </header>
                        <div class="modal-body">
                            <form id="plan-form" autocomplete="off">
                                <input type="hidden" id="plan-id" value="">

                                <div class="mvc-field">
                                    <div class="mvc-field__top">
                                        <label class="mvc-field__label" for="plan-name">Plan name</label>
                                        <span class="mvc-field__hint">Required</span>
                                    </div>
                                    <div class="mvc-field__row">
                                        <input type="text" class="mvc-field__input" id="plan-name" maxlength="120" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="plan-min">Min amount</label>
                                                <span class="mvc-field__hint">USD</span>
                                            </div>
                                            <div class="mvc-field__row">
                                                <span class="mvc-field__chip">$</span>
                                                <input type="number" step="0.01" min="0" class="mvc-field__input mvc-field__input--num" id="plan-min" placeholder="250">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="plan-max">Max amount</label>
                                                <span class="mvc-field__hint">USD</span>
                                            </div>
                                            <div class="mvc-field__row">
                                                <span class="mvc-field__chip">$</span>
                                                <input type="number" step="0.01" min="0" class="mvc-field__input mvc-field__input--num" id="plan-max" placeholder="25000">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php // A plan has ONE roi_percent, charged per payout period, and a
                                      // cadence that defines how long that period is. The Min/Max ROI
                                      // pair that used to sit here matched no column and always failed
                                      // the backend validator. ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="plan-cadence">Payout cadence</label>
                                                <span class="mvc-field__hint">Required</span>
                                            </div>
                                            <div class="mvc-field__row">
                                                <select class="mvc-field__input" id="plan-cadence" required>
                                                    <option value="weekly">Weekly</option>
                                                    <option value="monthly" selected>Monthly</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="plan-roi">ROI per payout</label>
                                                <span class="mvc-field__hint">Required</span>
                                            </div>
                                            <div class="mvc-field__row">
                                                <input type="number" step="0.01" min="0.01" class="mvc-field__input mvc-field__input--num" id="plan-roi" placeholder="1.1" required>
                                                <span class="mvc-field__chip">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="plan-duration">Term duration</label>
                                                <span class="mvc-field__hint">Days</span>
                                            </div>
                                            <div class="mvc-field__row">
                                                <input type="number" min="1" max="3650" class="mvc-field__input mvc-field__input--num" id="plan-duration" placeholder="90" required>
                                            </div>
                                            <p class="mvc-field__note">At least one payout period: 7 days weekly, 30 monthly.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="plan-risk">Risk level</label>
                                            </div>
                                            <div class="mvc-field__row">
                                                <select class="mvc-field__input" id="plan-risk">
                                                    <option value="low">Low</option>
                                                    <option value="medium">Medium</option>
                                                    <option value="high">High</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mvc-field">
                                    <div class="mvc-field__top">
                                        <label class="mvc-field__label" for="plan-status">Status</label>
                                    </div>
                                    <div class="mvc-field__row">
                                        <select class="mvc-field__input" id="plan-status">
                                            <option value="active">Active</option>
                                            <option value="hidden">Hidden</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer-actions">
                            <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                            <button type="submit" form="plan-form" class="modal-confirm-btn">Save plan</button>
                        </div>
                    </div>
                </div>

                <?php // Closing a position moves real money, so it goes through a
                      // confirm step rather than firing on one click. Same shape as
                      // the deposit/withdrawal dialogs. ?>
                <div class="modal mvc-modal--danger" id="close-investment-modal" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="modal-overlay" data-modal-close></div>
                    <div class="modal-content" tabindex="-1" aria-labelledby="close-investment-title">
                        <div class="modal-header">
                            <div>
                                <h2 id="close-investment-title">Close this position?</h2>
                                <p class="modal-header__sub" id="close-investment-sub"></p>
                            </div>
                            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                        </div>
                        <div class="modal-body">
                            <ul class="mvc-summary">
                                <li class="mvc-summary__row">
                                    <span class="k"><i class="ph ph-user"></i> Member</span>
                                    <span class="v" id="close-inv-user"></span>
                                </li>
                                <li class="mvc-summary__row">
                                    <span class="k"><i class="ph ph-chart-line-up"></i> Plan</span>
                                    <span class="v" id="close-inv-plan"></span>
                                </li>
                                <li class="mvc-summary__row mvc-summary__row--total">
                                    <span class="k" id="close-inv-label">Returns to wallet</span>
                                    <span class="v" id="close-inv-amount"></span>
                                </li>
                            </ul>
                            <p class="note" id="close-inv-note"></p>
                            <div class="modal-actions">
                                <button type="button" class="button-close-modal tf-button" data-modal-close>Keep it open</button>
                                <button type="button" class="modal-confirm-btn" id="close-investment-btn">Close position</button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php /* Edit position - intent-based.
                         Was three raw fields (amount, roi_percent, status). The cron
                         reads payouts_made, payouts_total, next_payout_date and
                         maturity_date together, and several combinations silently
                         corrupt the schedule: a past next_payout_date fires immediate
                         catch-up payouts, and a maturity_date before the final payout
                         loses the member one. So the admin picks an INTENT and the
                         API derives the columns. See api/admin/plans.php. */ ?>
                <div class="modal mvc-modal--wide mvc-modal--compact" id="edit-investment-modal" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="modal-overlay" data-modal-close></div>
                    <div class="modal-content" tabindex="-1" aria-labelledby="edit-investment-title">
                        <header class="modal-header">
                            <div>
                                <h2 id="edit-investment-title">Manage position</h2>
                                <p class="modal-header__sub" id="inv-subtitle">&nbsp;</p>
                            </div>
                            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                        </header>

                        <div class="modal-body">
                            <input type="hidden" id="inv-id" value="">

                            <ul class="mvc-summary">
                                <li class="mvc-summary__row">
                                    <span class="k"><i class="ph ph-user"></i> Member</span>
                                    <span class="v" id="inv-user"></span>
                                </li>
                                <li class="mvc-summary__row">
                                    <span class="k"><i class="ph ph-chart-line-up"></i> Plan</span>
                                    <span class="v" id="inv-plan"></span>
                                </li>
                                <li class="mvc-summary__row">
                                    <span class="k">Progress</span>
                                    <span class="v" id="inv-progress"></span>
                                </li>
                                <li class="mvc-summary__row">
                                    <span class="k">Next payout</span>
                                    <span class="v" id="inv-next"></span>
                                </li>
                                <li class="mvc-summary__row">
                                    <span class="k">Matures</span>
                                    <span class="v" id="inv-maturity"></span>
                                </li>
                                <li class="mvc-summary__row mvc-summary__row--total">
                                    <span class="k">Earned so far</span>
                                    <span class="v" id="inv-earned"></span>
                                </li>
                            </ul>

                            <?php // Rate is the one schedule field safe to edit directly:
                                  // the cron re-reads it each run, so it only affects
                                  // future periods. ?>
                            <div class="mvc-field">
                                <div class="mvc-field__top">
                                    <label class="mvc-field__label" for="inv-roi">Rate per payout</label>
                                    <span class="mvc-field__hint" id="inv-per-payout"></span>
                                </div>
                                <div class="mvc-field__row">
                                    <input type="number" step="0.01" min="0" max="999.99" class="mvc-field__input mvc-field__input--num" id="inv-roi">
                                    <span class="mvc-field__chip">%</span>
                                    <button type="button" class="tf-button f12-bold" id="inv-save-rate">Save</button>
                                </div>
                                <p class="mvc-field__note">Applies from the next scheduled payout. Nothing already paid changes.</p>
                            </div>

                            <details class="mvc-disclosure" id="inv-actions">
                                <summary>
                                    Actions
                                    <span class="mvc-disclosure__count">bonus &middot; term &middot; close</span>
                                </summary>
                                <div class="mvc-disclosure__body">

                                    <div class="mvc-field">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="inv-bonus">Add a bonus payout</label>
                                            <span class="mvc-field__hint">USD</span>
                                        </div>
                                        <div class="mvc-field__row">
                                            <span class="mvc-field__chip">$</span>
                                            <input type="number" step="0.01" min="0.01" class="mvc-field__input mvc-field__input--num" id="inv-bonus" placeholder="25.00">
                                            <button type="button" class="tf-button f12-bold bg-Accent text-Black" id="inv-add-bonus">Credit</button>
                                        </div>
                                        <p class="mvc-field__note">Credits the wallet now and logs a payout on the member's activity.</p>
                                    </div>

                                    <div class="mvc-field">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="inv-payouts-total">Total payouts</label>
                                            <span class="mvc-field__hint" id="inv-term-hint"></span>
                                        </div>
                                        <div class="mvc-field__row">
                                            <input type="number" step="1" min="1" max="520" class="mvc-field__input mvc-field__input--num" id="inv-payouts-total">
                                            <button type="button" class="tf-button f12-bold bg-Accent text-Black" id="inv-save-term">Apply</button>
                                        </div>
                                        <p class="mvc-field__note">The maturity date moves with it, so the last payout and the principal release stay on the same day.</p>
                                    </div>

                                    <div class="mvc-field">
                                        <div class="mvc-field__top">
                                            <span class="mvc-field__label">Close this position</span>
                                        </div>
                                        <div class="mvc-field__row">
                                            <button type="button" class="tf-button f12-bold" id="inv-settle">Settle now</button>
                                            <button type="button" class="tf-button f12-bold bg-Red text-White" id="inv-cancel">Cancel &amp; refund</button>
                                        </div>
                                        <p class="mvc-field__note"><strong>Settle</strong> releases the principal and closes the position. <strong>Cancel</strong> refunds the principal and unwinds the position.</p>
                                    </div>

                                </div>
                            </details>
                        </div>

                        <div class="modal-footer-actions">
                            <button type="button" class="button-close-modal tf-button" data-modal-close>Done</button>
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
<script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/mvc-pagination.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/plans.js') ?>" defer></script>
<script src="/assets/js/chart.min.js"></script>
</body>
</html>