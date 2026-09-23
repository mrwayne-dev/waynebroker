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

// Optional: admin variables for display
$admin_id = $_SESSION['admin_id'];
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
$admin_email = $_SESSION['admin_email'] ?? '';
?>

<?php
  $page_title = "Admin Dashboard | Maveren Capital";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
    <!-- #wrapper -->
    <div id="wrapper">
        <!-- #page -->
        <div id="page" class="">
            <!-- layout-wrap -->
            <div class="layout-wrap loader-off">
                <!-- preload -->
                <div id="preload" class="preload-container">
                    <div class="preloading">
                        <span></span>
                    </div>
                </div>
                <!-- /preload -->
                <!-- section-menu-left -->
                <?php $active = "dashboard"; include __DIR__ . "/_partials/sidebar.php"; ?>
                <?php include __DIR__ . "/_partials/dock.php"; ?>
                <!-- section-content-right -->
                <div class="section-content-right">
                    <!-- header-dashboard -->
                    <?php $page_heading = "Admin Dashboard"; include __DIR__ . "/_partials/topbar.php"; ?>
                    <!-- main-content -->
                    <div class="main-content">
                        <!-- main-content-wrap -->
                        <div class="main-content-inner">
                            <!-- main-content-wrap -->
                            <div class="main-content-wrap">
                                <div class="tf-container">
                                    
                                    <div class="row">
                                        <!-- ============================= -->
                                        <!-- ADMIN DASHBOARD CARDS OVERVIEW SECTION (Full Width, Bigger Cards) -->
                                        <!-- ============================= -->
                                        <div class="col-12 mb-40">
                                            <div class="wallet-overview">
                                                <div class="section-header flex justify-between items-center mb-16">
                                                    <h6 class="label-01">Dashboard Overview</h6>
                                                    <a href="#" class="f14-regular flex items-center gap8 text-Primary" data-refresh-dashboard>
                                                        <i class="ph ph-arrows-clockwise"></i> Refresh Stats
                                                    </a>
                                                </div>

                                                <div class="wallet-cards grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap20">

                                                    <!-- Total Revenue -->
                                                    <div class="wallet-card wallet-main">
                                                        <div class="wallet-card-header">Total Revenue</div>
                                                        <div class="wallet-card-balance">$<span id="total-revenue">0.00</span></div>
                                                        <div class="wallet-card-footer">
                                                            MVC-REV-<?= str_pad($admin_id, 3, '0', STR_PAD_LEFT) ?>
                                                        </div>
                                                    </div>

                                                    <!-- AUM (Total Allocated Capital) -->
                                                    <div class="wallet-card wallet-aum">
                                                        <div class="wallet-card-header">Total AUM</div>
                                                        <div class="wallet-card-balance">$<span id="total-aum">0.00</span></div>
                                                        <div class="wallet-card-footer">
                                                            MVC-AUM-<?= str_pad($admin_id, 3, '0', STR_PAD_LEFT) ?>
                                                        </div>
                                                    </div>

                                                    <!-- Capital invested -->
                                                    <div class="wallet-card wallet-investments">
                                                        <div class="wallet-card-header">Capital invested</div>
                                                        <div class="wallet-card-balance"><span id="active-investments">0</span></div>
                                                        <div class="wallet-card-footer">
                                                            MVC-INV-<?= str_pad($admin_id, 3, '0', STR_PAD_LEFT) ?>
                                                        </div>
                                                    </div>

                                                    <!-- Total Users -->
                                                    <div class="wallet-card wallet-members">
                                                        <div class="wallet-card-header">Total Members</div>
                                                        <div class="wallet-card-balance"><span id="total-users">0</span></div>
                                                        <div class="wallet-card-footer">
                                                            MVC-USR-<?= str_pad($admin_id, 3, '0', STR_PAD_LEFT) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ============================= -->
                                        <!-- ACTIVITY OVERVIEW & CHART SECTION -->
                                        <!-- ============================= -->
                                        <div class="col-12 mb-32">
                                            <div class="wg-box card-details mb-32">
                                                <div class="title flex justify-between items-center">
                                                    <h6 class="label-01">Activity Overview</h6>
                                                </div>

                                                <hr class="divider mb-24">

                                                <div class="card-details-grid">
                                                    <!-- Left: Chart Only -->
                                                    <div class="card-chart-panel text-center">
                                                        <canvas id="activityChart" width="200" height="200"></canvas>
                                                        <ul class="chart-legend flex justify-center gap16 mt-12 flex-wrap">
                                                            <li class="flex items-center gap6">
                                                                <div class="dot bg-Primary"></div> <span>Revenue</span> <strong id="chart-revenue">0%</strong>
                                                            </li>
                                                            <li class="flex items-center gap6">
                                                                <div class="dot bg-Green"></div> <span>AUM</span> <strong id="chart-aum">0%</strong>
                                                            </li>
                                                            <li class="flex items-center gap6">
                                                                <div class="dot bg-Accent"></div> <span>Invested</span> <strong id="chart-investments">0%</strong>
                                                            </li>
                                                            <li class="flex items-center gap6">
                                                                <div class="dot bg-Purple"></div> <span>Users</span> <strong id="chart-users">0%</strong>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <!-- Right: Chart Legend / Placeholder -->
                                                    <div class="card-info-panel">
                                                        <ul class="card-info-list">
                                                            <li>
                                                                <span>Period</span>
                                                                <strong id="chart-period">Last 30 Days</strong>
                                                            </li>
                                                            <li>
                                                                <span>Peak Activity</span>
                                                                <strong id="peak-activity">N/A</strong>
                                                            </li>
                                                            <li>
                                                                <span>Avg. Daily Users</span>
                                                                <strong id="avg-daily-users">0</strong>
                                                            </li>
                                                            <li>
                                                                <span>Top Performing Feature</span>
                                                                <strong id="top-feature">N/A</strong>
                                                            </li>
                                                            <li>
                                                                <span>System Uptime</span>
                                                                <strong id="system-uptime">99.9%</strong>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ============================= -->
                                        <!-- RECENT ACTIVITY & NOTIFICATIONS/QUICK ACTIONS (Side by Side) -->
                                        <!-- ============================= -->
                                        <div class="row">
                                            <div class="col-lg-6 mb-32">
                                                <!-- Recent Activity Section -->
                                                <div class="wg-box gap16">
                                                    <div class="title mb-12 flex justify-between items-center">
                                                        <div class="label-01">Recent Activity</div>
                                                        <a href="/admin/transactions" class="f12-bold text-Primary">View All</a>
                                                    </div>
                                                    <?php // Was `table.tab-sell-order`: content-driven flex cells
                                                          // with no widths, no scroll wrapper, and an orange
                                                          // row-hover at 2.81:1 contrast. ?>
                                                    <div class="mvc-scroll-table">
                                                        <table class="mvc-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Date</th>
                                                                    <th>Member</th>
                                                                    <th>Type</th>
                                                                    <th>Amount</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="recent-activity">
                                                                <tr><td class="mvc-empty" colspan="5">Loading activity...</td></tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <!-- /Recent Activity Section -->
                                            </div>
                                            <div class="col-lg-6 mb-32">
                                                <!-- Notifications & Quick Actions -->
                                                <div class="row">
                                                    <!-- Quick Actions Panel -->
                                                    <div class="col-md-12 mb-24">
                                                        <div class="wg-box quick-actions-box">
                                                            <div class="title mb-12">
                                                                <div class="label-01">Quick Actions</div>
                                                            </div>

                                                            <div class="quick-actions-grid">
                                                                <button id="send-email-btn" class="quick-action-btn bg-GrayLight text-Black">
                                                                    <i class="ph ph-paper-plane-tilt"></i>
                                                                    Send Email
                                                                </button>

                                                                <?php // Two buttons that set and viewed a single address each became
                                                                      // one link to the CRUD page, now that there is a row per chain. ?>
                                                                <a href="/admin.deposit-addresses" class="quick-action-btn bg-Green text-White">
                                                                    <i class="ph ph-wallet"></i>
                                                                    Deposit Addresses
                                                                </a>


                                                                <a href="/admin/transactions/pending" class="quick-action-btn bg-Accent text-Black">
                                                                    <i class="ph ph-plus-circle"></i>
                                                                    Pending Deposits
                                                                </a>

                                                                <a href="/admin/withdrawals/pending" class="quick-action-btn bg-Green text-White">
                                                                    <i class="ph ph-arrow-square-out"></i>
                                                                    Pending Withdrawals
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <!-- /main-content-wrap -->
                        </div>
                        <!-- /main-content-wrap -->
                    </div>
                    <!-- /main-content -->

                    <!-- Modals -->

                    <?php // Broadcast email. Rebuilt on .mvc-field - the last legacy
                          // .form-group dialog in the admin panel. "Donors Only" was
                          // dropped: it is a leftover recipient group from the
                          // inherited product with no matching data here. ?>
                    <div class="modal mvc-modal--compact" id="email-modal" role="dialog" aria-modal="true" aria-hidden="true">
                        <div class="modal-overlay" data-modal-close></div>
                        <div class="modal-content" tabindex="-1" aria-labelledby="email-modal-title">
                            <header class="modal-header">
                                <div>
                                    <h2 id="email-modal-title">Send email</h2>
                                    <p class="modal-header__sub">Goes out immediately to everyone in the group.</p>
                                </div>
                                <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                            </header>
                            <div class="modal-body">
                                <form id="email-form" autocomplete="off">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <label class="mvc-field__label" for="email-recipients">Recipients</label>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <select class="mvc-field__input" id="email-recipients" required>
                                                        <option value="">Select...</option>
                                                        <option value="all">All members</option>
                                                        <option value="active">Active members</option>
                                                        <option value="investors">Investors only</option>
                                                        <option value="specific">A single member</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <label class="mvc-field__label" for="email-priority">Priority</label>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <select class="mvc-field__input" id="email-priority">
                                                        <option value="normal">Normal</option>
                                                        <option value="high">High</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mvc-field" id="email-user-id-group" hidden>
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="email-user-id">Member ID</label>
                                            <span class="mvc-field__hint">Exact match</span>
                                        </div>
                                        <div class="mvc-field__row">
                                            <input type="text" class="mvc-field__input" id="email-user-id" inputmode="numeric" maxlength="12" placeholder="e.g. 42">
                                        </div>
                                    </div>

                                    <div class="mvc-field">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="email-subject">Subject</label>
                                        </div>
                                        <div class="mvc-field__row">
                                            <input type="text" class="mvc-field__input" id="email-subject" maxlength="150" placeholder="Scheduled maintenance this weekend" required>
                                        </div>
                                    </div>

                                    <div class="mvc-field mvc-field--textarea">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="email-body">Message</label>
                                        </div>
                                        <div class="mvc-field__row">
                                            <textarea class="mvc-field__input" id="email-body" rows="5" maxlength="4000" placeholder="Write the message members will receive." required></textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="modal-footer-actions">
                                <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                                <button type="submit" form="email-form" class="modal-confirm-btn">Send email</button>
                            </div>
                        </div>
                    </div>


                    <?php // The "view deposit addresses" and "set deposit address" modals
                          // lived here. Both were built around `settings` holding exactly
                          // one cash-mailing address and one wallet address; addresses are
                          // now a table with a row per chain, managed at
                          // /admin.deposit-addresses. ?>

                    <?php // The six pending-queue dialogs moved to a shared partial so
                          // /admin.wallets can open the same ones per user. ?>
                    <?php include __DIR__ . '/_partials/pending-modals.php'; ?>



                </div>
                <!-- /section-content-right -->
            </div>
            <!-- /layout-wrap -->
        </div>
        <!-- /#page -->
    </div>
    <!-- /#wrapper -->
    <div id="loader" class="hidden">
        <div class="line-loader">
            <div></div><div></div><div></div><div></div><div></div>
        </div>
    </div>
    <!-- Toast Notifications -->
    <div id="toast-container"></div>
    
<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/countto.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>

<!-- Chart.js CDN -->
<script src="/assets/js/chart.min.js"></script>

<!-- Iconify CDN -->
</body>
</html>