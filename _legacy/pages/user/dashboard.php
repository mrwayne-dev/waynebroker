<?php
// pages/user/dashboard.php
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
$ref       = str_pad((string) (int) $user_id, 4, '0', STR_PAD_LEFT);
?>
<?php
  $page_title = "Dashboard | Maveren Capital";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
<div id="wrapper">
  <div id="page">
    <div class="layout-wrap loader-off">
      <div id="preload" class="preload-container">
        <div class="preloading"><span></span></div>
      </div>

      <?php $active = "dashboard"; include __DIR__ . "/_partials/sidebar.php"; ?>
      <?php include __DIR__ . "/_partials/dock.php"; ?>

      <div class="section-content-right">
        <?php $page_heading = "Dashboard"; include __DIR__ . "/_partials/topbar.php"; ?>

        <div class="main-content">
          <div class="main-content-inner">
            <div class="main-content-wrap">
              <div class="tf-container">
                <div class="mvc-stack">

                  <?php // ---------------------------------------------------
                        // BALANCES
                        // Every id below is written by loadDashboardData() in
                        // assets/js/dashboard.js. The markup around them changed;
                        // the contract did not.
                        // --------------------------------------------------- ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Wallet overview</h2>
                      <a href="#" class="mvc-panel__action" data-refresh-dashboard>
                        <i class="ph ph-arrows-clockwise" aria-hidden="true"></i> Refresh balances
                      </a>
                    </div>

                    <div class="mvc-stats">
                      <div class="mvc-stat mvc-stat--featured">
                        <span class="mvc-stat__label">Main wallet</span>
                        <span class="mvc-stat__value">$<span id="total-balance">0.00</span></span>
                        <span class="mvc-stat__meta">MVC-MAIN-<?= $ref ?></span>
                      </div>

                      <div class="mvc-stat">
                        <span class="mvc-stat__label">Total earnings</span>
                        <span class="mvc-stat__value">$<span id="total-earnings">0.00</span></span>
                        <span class="mvc-stat__meta">MVC-ERN-<?= $ref ?></span>
                      </div>

                      <div class="mvc-stat">
                        <span class="mvc-stat__label">Invested</span>
                        <span class="mvc-stat__value">$<span id="total-investments">0.00</span></span>
                        <span class="mvc-stat__meta"><span id="active-positions">0</span> active position(s)</span>
                      </div>

                      <div class="mvc-stat">
                        <span class="mvc-stat__label">Next payout</span>
                        <span class="mvc-stat__value">$<span id="next-payout-amount">0.00</span></span>
                        <span class="mvc-stat__meta">due <span id="next-payout-date">&mdash;</span></span>
                      </div>
                    </div>
                  </section>

                  <?php // ---------------------------------------------------
                        // ACCOUNT + ALLOCATION
                        // The old version had an empty band under the heading
                        // where a divider and no content sat; the panel is now
                        // just the two things it actually contains.
                        // --------------------------------------------------- ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Account</h2>
                    </div>

                    <div class="mvc-split">
                      <ul class="mvc-kv">
                        <li><span>Account name</span><strong id="card-name">Main Wallet</strong></li>
                        <li><span>Account holder</span><strong id="card-holder"><?= $user_name ?></strong></li>
                        <li><span>Reference</span><strong id="card-id">MVC-<?= $ref ?>-9011-3298</strong></li>
                        <li><span>Opened</span><strong id="card-valid">08/26</strong></li>
                        <li><span>Held with</span><strong id="card-bank">Maveren Capital</strong></li>
                      </ul>

                      <div class="mvc-chart">
                        <canvas id="cardUsageChart" width="190" height="190"
                                aria-label="Allocation across weekly plans, monthly plans and wallet cash"
                                role="img"></canvas>
                        <?php // Seeded so the panel reads correctly before the fetch
                              // resolves, and still reads if it fails. Real text, not
                              // canvas labels, so the translator can reach it. ?>
                        <ul class="mvc-chart__legend">
                          <li><span class="mvc-chart__dot" style="background: var(--Primary)"></span><span>Weekly</span> <strong>0%</strong></li>
                          <li><span class="mvc-chart__dot" style="background: var(--mvc-teal)"></span><span>Monthly</span> <strong>0%</strong></li>
                          <li><span class="mvc-chart__dot" style="background: var(--Gray)"></span><span>Wallet</span> <strong>0%</strong></li>
                        </ul>
                      </div>
                    </div>
                  </section>

                  <div class="mvc-cols-2">
                    <?php // ------------------------------------------------- ?>
                    <section class="mvc-panel">
                      <div class="mvc-panel__head">
                        <h2 class="mvc-panel__title">Latest updates</h2>
                      </div>

                      <?php // These notices used to be three hardcoded blocks of copy.
                            // They looked like announcements and never changed, while
                            // the announcements an admin actually published sat in a
                            // table nothing on this side ever read. mvcRenderAnnouncements()
                            // in assets/js/dashboard.js fills this container from the
                            // published announcements returned by get_data. ?>
                      <div id="dash-announcements">
                        <div class="mvc-notice mvc-notice--empty">
                          <span class="mvc-notice__icon"><i class="ph ph-megaphone" aria-hidden="true"></i></span>
                          <div>
                            <div class="mvc-notice__body">Loading updates&hellip;</div>
                          </div>
                        </div>
                      </div>
                    </section>

                    <?php // -------------------------------------------------
                          // RECENT ACTIVITY
                          // mvcRenderTransactionRows() writes <tr> into
                          // tbody#recent-activity, so this stays a real table.
                          // ------------------------------------------------- ?>
                    <section class="mvc-panel">
                      <div class="mvc-panel__head">
                        <h2 class="mvc-panel__title">Recent activity</h2>
                        <a href="/dashboard.transactions" class="mvc-panel__action">
                          View all <i class="ph ph-arrow-right" aria-hidden="true"></i>
                        </a>
                      </div>

                      <div class="mvc-scroll-table">
                        <table class="mvc-table mvc-table--quiet">
                          <thead>
                            <tr>
                              <th scope="col">Date</th>
                              <th scope="col">Type</th>
                              <th scope="col">Amount</th>
                            </tr>
                          </thead>
                          <tbody id="recent-activity"></tbody>
                        </table>
                      </div>
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
</div>

<div id="loader" class="hidden">
  <div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
</div>
<div id="toast-container"></div>

<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/countto.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/dashboard.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/chart.min.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/dashboard-chart.js') ?>" defer></script>

</body>
</html>
