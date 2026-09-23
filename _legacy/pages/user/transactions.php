<?php
// pages/user/transactions.php

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
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;
$user_role = $_SESSION['role'] ?? 'user';

// Placeholder for dynamic data
$transactions = [];
?>
<?php
  $page_title = "Transactions | Maveren Capital";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
<div id="wrapper">
  <div id="page">
    <div class="layout-wrap loader-off">
      <div id="preload" class="preload-container">
        <div class="preloading"><span></span></div>
      </div>

      <?php $active = "transactions"; include __DIR__ . "/_partials/sidebar.php"; ?>
      <?php include __DIR__ . "/_partials/dock.php"; ?>

      <div class="section-content-right">
        <?php $page_heading = "Transactions"; include __DIR__ . "/_partials/topbar.php"; ?>

        <div class="main-content">
          <div class="main-content-inner">
            <div class="main-content-wrap">
              <div class="tf-container">

                <section class="mvc-panel">
                  <div class="mvc-panel__head">
                    <h2 class="mvc-panel__title">All activity</h2>
                  </div>

                  <?php // transaction.js binds .form-search, .form-search input,
                        // .dropdown-menu a and .tf-button.style-2 by selector, so
                        // those class names are load-bearing. Search, filter and
                        // export are all live - they are not decoration. ?>
                  <div class="mvc-toolbar">
                    <form class="form-search" role="search">
                      <div class="button-submit">
                        <button type="submit" aria-label="Search transactions">
                          <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        </button>
                      </div>
                      <label class="visually-hidden" for="txn-search">Search transactions</label>
                      <input id="txn-search" type="search" placeholder="Search by reference, type or amount">
                    </form>

                    <div class="mvc-toolbar__right">
                      <div class="dropdown default style-fill">
                        <button class="btn btn-secondary dropdown-toggle tf-button bg-Accent f12-bold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="ph ph-sliders-horizontal" aria-hidden="true"></i> Filter
                        </button>
                        <?php // The handler lowercases the link TEXT and sends it as the
                              // status, so these labels are the API contract. ?>
                        <ul class="dropdown-menu dropdown-menu-end" data-txn-filter>
                          <li><a href="#">All</a></li>
                          <li><a href="#">Completed</a></li>
                          <li><a href="#">Pending</a></li>
                          <li><a href="#">Failed</a></li>
                        </ul>
                      </div>

                      <a href="#" class="tf-button style-2 bg-Primary f12-bold">
                        <i class="ph ph-download-simple" aria-hidden="true"></i> Export CSV
                      </a>
                    </div>
                  </div>

                  <div class="mvc-scroll-table">
                    <table class="mvc-table mvc-table--quiet">
                      <thead>
                        <tr>
                          <th scope="col">Reference</th>
                          <th scope="col">Date</th>
                          <th scope="col">Type</th>
                          <th scope="col">Amount</th>
                          <th scope="col">Status</th>
                        </tr>
                      </thead>
                      <tbody id="transactionList"></tbody>
                    </table>
                  </div>

                  <div id="pagination"></div>
                </section>

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

<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/dashboard.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/mvc-pagination.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/transaction.js') ?>" defer></script>
</body>
</html>
