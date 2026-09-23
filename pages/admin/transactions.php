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
  $page_title = "Transactions | Maveren Capital Admin";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
    <div id="wrapper">
        <div id="page" class="">
            <div class="layout-wrap loader-off">
                <div id="preload" class="preload-container">
                    <div class="preloading"><span></span></div>
                </div>

                <!-- Sidebar - Transactions page is active -->
                <?php $active = "transactions"; include __DIR__ . "/_partials/sidebar.php"; ?>
                <?php include __DIR__ . "/_partials/dock.php"; ?>
                <!-- /Sidebar -->

                <!-- Main Content -->
                <div class="section-content-right">
                    <!-- Header -->
                    <?php $page_heading = "Transactions"; include __DIR__ . "/_partials/topbar.php"; ?>
                    <!-- /Header -->

                    <!-- Main Content -->
                    <div class="main-content">
                        <div class="main-content-inner">
                            <div class="main-content-wrap">
                                <div class="tf-container">

                                    <!-- TRANSACTION STATS CARDS (same style as users page) -->
                                    <div class="row mb-32">
                                        <div class="col-12">
                                            <div class="wallet-cards grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap20">
                                                <div class="wallet-card wallet-main">
                                                    <div class="wallet-card-header">Total Transactions</div>
                                                    <div class="wallet-card-balance"><span id="total-transactions">0</span></div>
                                                    <div class="wallet-card-footer"> <i class="ph ph-clock-counter-clockwise"></i> All Time</div>
                                                </div>
                                                <div class="wallet-card wallet-green">
                                                    <div class="wallet-card-header">Total Volume</div>
                                                    <div class="wallet-card-balance">$<span id="total-volume">0.00</span></div>
                                                    <div class="wallet-card-footer"> <i class="ph ph-chart-line"></i> Processed</div>
                                                </div>
                                                <div class="wallet-card wallet-accent">
                                                    <div class="wallet-card-header">Pending</div>
                                                    <div class="wallet-card-balance"><span id="pending-count">0</span></div>
                                                    <div class="wallet-card-footer"> <i class="ph ph-clock"></i> Awaiting Action</div>
                                                </div>
                                                <div class="wallet-card wallet-purple">
                                                    <div class="wallet-card-header">Today</div>
                                                    <div class="wallet-card-balance"><span id="today-count">0</span></div>
                                                    <div class="wallet-card-footer"> <i class="ph ph-calendar-blank"></i> Transactions</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SEARCH + FILTERS (exactly like user transactions page) -->
                                    <div class="topbar-search mb-24">
                                        <form class="form-search flex-grow">
                                            <fieldset class="name">
                                                <input type="text" id="transaction-search" placeholder="Search by ID, user, or reference..." class="show-search style-1">
                                            </fieldset>
                                            <div class="button-submit">
                                                <button type="submit"><i class="ph ph-magnifying-glass"></i></button>
                                            </div>
                                        </form>
                                        <div class="right">
                                            <a href="#" id="export-csv" class="tf-button style-2 f12-bold d-md-flex d-none">
                                                <i class="ph ph-export"></i>
                                                Export Report
                                            </a>
                                            <div class="dropdown default style-fill">
                                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="ph ph-funnel"></i> Filter
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a href="#" data-filter="all">All Transactions</a></li>
                                                    <li><a href="#" data-filter="deposit">Deposits</a></li>
                                                    <li><a href="#" data-filter="withdrawal">Withdrawals</a></li>
                                                    
                                                    <li><a href="#" data-filter="investment">Investments</a></li>
                                                    <li><a href="#" data-filter="pending">Pending Only</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TRANSACTIONS TABLE (real table, horizontal scroll on mobile) -->
                                    <div class="mvc-scroll-table">
                                        <table class="mvc-table w-100">
                                            <thead>
                                                <tr>
                                                    <th>Transaction ID</th>
                                                    <th>Date</th>
                                                    <th>User</th>
                                                    <th>Type</th>
                                                    <th>Amount (USD)</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="transactions-table-body">
                                                <!-- JS will populate -->
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

                </div>
            </div>
        </div>
    </div>

    <div id="loader" class="hidden">
        <div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
    </div>
    <div id="toast-container"></div>

    <script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
    <script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
    <script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
    <script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>
    <script src="<?= mvc_asset('../../assets/js/mvc-pagination.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>
    <script src="<?= mvc_asset('../../assets/js/admin/transactions.js') ?>" defer></script>

    <script src="/assets/js/chart.min.js"></script>
</body>
</html>