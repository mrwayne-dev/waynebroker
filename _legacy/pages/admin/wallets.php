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
  $page_title = "Wallets | Maveren Capital Admin";
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
            <?php $active = "wallets"; include __DIR__ . "/_partials/sidebar.php"; ?>
            <?php include __DIR__ . "/_partials/dock.php"; ?>
            <!-- /Sidebar -->

            <!-- Main Content -->
            <div class="section-content-right">
                <!-- Header -->
                <?php $page_heading = "Wallet Management"; include __DIR__ . "/_partials/topbar.php"; ?>
                <!-- /Header -->

                <!-- Main Content -->
                <div class="main-content">
                    <div class="main-content-inner">
                        <div class="main-content-wrap">
                            <div class="tf-container">

                                <!-- WALLET STATS CARDS -->
                                <div class="row mb-32">
                                    <div class="col-12">
                                        <div class="wallet-cards grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap20">
                                            <!-- Total Wallets -->
                                            <div class="wallet-card wallet-main">
                                                <div class="wallet-card-header">Total Wallets</div>
                                                <div class="wallet-card-balance"><span id="total-wallets">0</span></div>
                                                <div class="wallet-card-footer"> <i class="ph ph-wallet"></i> Active</div>
                                            </div>
                                            <!-- Total Balance -->
                                            <div class="wallet-card wallet-green">
                                                <div class="wallet-card-header">Total Balance</div>
                                                <div class="wallet-card-balance">$<span id="total-balance">0.00</span></div>
                                                <div class="wallet-card-footer"><i class="ph ph-bank"></i> All Users</div>
                                            </div>
                                            <!-- Pending Deposits -->
                                            <div class="wallet-card wallet-accent">
                                                <div class="wallet-card-header">Pending Deposits</div>
                                                <div class="wallet-card-balance"><span id="pending-deposits">0</span></div>
                                                <div class="wallet-card-footer"> <i class="ph ph-plus-circle"></i>  Requests</div>
                                            </div>
                                            <!-- Pending Withdrawals -->
                                            <div class="wallet-card wallet-purple">
                                                <div class="wallet-card-header">Pending Withdrawals</div>
                                                <div class="wallet-card-balance"><span id="pending-withdrawals">0</span></div>
                                                <div class="wallet-card-footer"><i class="ph ph-minus-circle"></i> Requests</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SEARCH + FILTERS -->
                                <div class="topbar-search mb-24">
                                    <form class="form-search flex-grow">
                                        <fieldset class="name">
                                            <input type="text" id="wallet-search" placeholder="Search by user, email, or wallet ID..." class="show-search style-1">
                                        </fieldset>
                                        <div class="button-submit">
                                            <button type="submit"><i class="ph ph-magnifying-glass"></i></button>
                                        </div>
                                    </form>
                                    <div class="right">
                                        <div class="dropdown default style-fill">
                                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="ph ph-funnel"></i> Filter
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a href="#" data-filter="all">All Wallets</a></li>
                                                <li><a href="#" data-filter="active">Active Only</a></li>
                                                <li><a href="#" data-filter="zero">Zero Balance</a></li>
                                                <li><a href="#" data-filter="pending">Needs Attention</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <?php // See the note on the users table: the div-grid header
                                      // and the table body were sized independently. ?>
                                <div class="mvc-scroll-table">
                                    <table class="mvc-table">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Wallet ID</th>
                                                <th>Balance</th>
                                                <th>Pending</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="wallets-table-body">
                                            <tr><td class="mvc-empty" colspan="5">Loading wallet data...</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- PAGINATION -->
                                <div id="pagination"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Main Content -->

                <!-- ====================== MODALS ====================== -->

                <?php // Shared with the admin dashboard. Opened per-user from a wallet
                      // row here, globally from the quick actions there. ?>
                <?php include __DIR__ . '/_partials/pending-modals.php'; ?>

                <?php /* Edit Wallet Balance.
                         Was the legacy .form-group/.form-control dialect with two
                         DISABLED inputs standing in for read-only text - which is
                         why it rendered as three identical grey boxes where only
                         the last one was editable. Member and current balance are
                         facts, so they are stated in an .mvc-summary; the one real
                         control gets the only field card. */ ?>
                <div class="modal mvc-modal--compact" id="edit-balance-modal" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="modal-overlay" data-modal-close></div>
                    <div class="modal-content" tabindex="-1" aria-labelledby="edit-balance-title">
                        <header class="modal-header">
                            <div>
                                <h2 id="edit-balance-title">Edit wallet balance</h2>
                                <p class="modal-header__sub">Sets the balance outright, it is not an adjustment.</p>
                            </div>
                            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                        </header>
                        <div class="modal-body">
                            <ul class="mvc-summary">
                                <li class="mvc-summary__row">
                                    <span class="k"><i class="ph ph-user"></i> Member</span>
                                    <span class="v" id="edit-wallet-user"></span>
                                </li>
                                <li class="mvc-summary__row mvc-summary__row--total">
                                    <span class="k">Current balance</span>
                                    <span class="v" id="edit-current-balance">$0.00</span>
                                </li>
                            </ul>
                            <form id="edit-balance-form" autocomplete="off">
                                <input type="hidden" id="edit-wallet-id" value="">
                                <div class="mvc-field">
                                    <div class="mvc-field__top">
                                        <label class="mvc-field__label" for="edit-new-balance">New balance</label>
                                        <span class="mvc-field__hint">USD</span>
                                    </div>
                                    <div class="mvc-field__row">
                                        <span class="mvc-field__chip">$</span>
                                        <input type="number" step="0.01" min="0" class="mvc-field__input mvc-field__input--num" id="edit-new-balance" required>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer-actions">
                            <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                            <button type="submit" form="edit-balance-form" class="modal-confirm-btn">Update balance</button>
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
<script src="<?= mvc_asset('../../assets/js/admin/wallet.js') ?>" defer></script>
<script src="/assets/js/chart.min.js"></script>
</body>
</html>