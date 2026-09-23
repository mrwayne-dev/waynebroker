<?php
// pages/user/invest.php
require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$user_id   = $_SESSION['user_id'] ?? null;
?>
<?php
  $page_title = "Invest | Maveren Capital";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
<div id="wrapper">
  <div id="page">
    <div class="layout-wrap loader-off">
      <div id="preload" class="preload-container">
        <div class="preloading"><span></span></div>
      </div>

      <?php $active = "invest"; include __DIR__ . "/_partials/sidebar.php"; ?>
      <?php include __DIR__ . "/_partials/dock.php"; ?>

      <div class="section-content-right">
        <?php $page_heading = "Invest"; include __DIR__ . "/_partials/topbar.php"; ?>

        <div class="main-content">
          <div class="main-content-inner">
            <div class="main-content-wrap">
              <div class="tf-container">
                <div class="mvc-stack">

                  <?php // Summary. Every id is written by loadSummary() in invest.js. ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Your portfolio</h2>
                      <a href="/dashboard.transactions" class="mvc-panel__action">
                        Transactions <i class="ph ph-arrow-right" aria-hidden="true"></i>
                      </a>
                    </div>

                    <div class="mvc-stats">
                      <div class="mvc-stat mvc-stat--featured">
                        <span class="mvc-stat__label">Capital invested</span>
                        <span class="mvc-stat__value" id="card-active-investments">$0.00</span>
                        <span class="mvc-stat__meta"><span id="card-total-roi">$0.00</span> earned to date</span>
                      </div>
                      <div class="mvc-stat">
                        <span class="mvc-stat__label">Active positions</span>
                        <span class="mvc-stat__value" id="card-ongoing-plans">0</span>
                        <span class="mvc-stat__meta">Running now</span>
                      </div>
                      <div class="mvc-stat">
                        <span class="mvc-stat__label">Next payout</span>
                        <span class="mvc-stat__value" id="card-next-payout-amount">$0.00</span>
                        <span class="mvc-stat__meta">due <span id="card-next-payout-date">&mdash;</span></span>
                      </div>
                      <div class="mvc-stat">
                        <span class="mvc-stat__label">Portfolio value</span>
                        <span class="mvc-stat__value" id="card-portfolio-value">$0.00</span>
                        <span class="mvc-stat__meta">Capital plus earnings</span>
                      </div>
                    </div>
                  </section>

                  <?php // ---------------------------------------------------
                        // THE SHELF
                        // Rendered by loadPlans() into #plans-grid. That element
                        // did not exist before, so the card renderer in invest.js
                        // drew into nothing and the only way to see a plan was to
                        // open the <select>.
                        // --------------------------------------------------- ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Choose a plan</h2>
                    </div>
                    <div class="mvc-plans" id="plans-grid"></div>
                  </section>

                  <div class="mvc-cols-2">
                    <?php // START A POSITION ?>
                    <section class="mvc-panel">
                      <div class="mvc-panel__head">
                        <h2 class="mvc-panel__title">Start a position</h2>
                      </div>

                      <form id="investment-form">
                        <div class="mvc-field">
                          <div class="mvc-field__top">
                            <label class="mvc-field__label" for="plan-select">Plan</label>
                          </div>
                          <div class="mvc-field__row">
                            <?php // The cards above set this; it remains the control the
                                  // form submits and the one a screen reader lands on. ?>
                            <select class="mvc-field__input" id="plan-select">
                              <option value="">Select a plan</option>
                            </select>
                          </div>
                        </div>

                        <div class="mvc-field">
                          <div class="mvc-field__top">
                            <label class="mvc-field__label" for="investment-amount">Amount to invest (USD)</label>
                          </div>
                          <div class="mvc-field__row">
                            <input class="mvc-field__input mvc-field__input--num" type="number"
                                   placeholder="0.00" min="1" step="0.01" id="investment-amount">
                          </div>
                        </div>

                        <div class="mvc-field">
                          <div class="mvc-field__top">
                            <label class="mvc-field__label">Wallet balance</label>
                          </div>
                          <div class="mvc-field__row">
                            <span class="mvc-field__input mvc-field__input--static">$<span id="wallet-balance">0.00</span></span>
                          </div>
                        </div>

                        <div class="mvc-cols-2">
                          <div class="mvc-field">
                            <div class="mvc-field__top">
                              <label class="mvc-field__label" for="term-duration">Term</label>
                            </div>
                            <div class="mvc-field__row">
                              <input class="mvc-field__input mvc-field__input--static" type="text" id="term-duration" readonly>
                            </div>
                          </div>
                          <div class="mvc-field">
                            <div class="mvc-field__top">
                              <label class="mvc-field__label" for="expected-roi">Rate per period</label>
                            </div>
                            <div class="mvc-field__row">
                              <input class="mvc-field__input mvc-field__input--static" type="text" id="expected-roi" readonly>
                            </div>
                          </div>
                        </div>

                        <?php // Live preview, shown by refreshPreview() once a plan and
                              // an amount are both present. ?>
                        <div class="mvc-summary" id="invest-preview" hidden>
                          <div class="mvc-summary__row"><span>Per payout</span><strong id="prev-per-payout">$0.00</strong></div>
                          <div class="mvc-summary__row"><span>Number of payouts</span><strong id="prev-payouts">0</strong></div>
                          <div class="mvc-summary__row"><span>First payout</span><strong id="prev-first">&mdash;</strong></div>
                          <div class="mvc-summary__row"><span>Total earned</span><strong id="prev-total-roi">$0.00</strong></div>
                          <div class="mvc-summary__row mvc-summary__row--total"><span>Returned at maturity</span><strong id="prev-total-return">$0.00</strong></div>
                        </div>

                        <button type="submit" class="tf-button bg-Primary f14-bold w-full mt-16" id="invest-btn" disabled>
                          <i class="ph ph-trend-up" aria-hidden="true"></i> Open position
                        </button>
                      </form>
                    </section>

                    <?php // PLAN DETAIL ?>
                    <section class="mvc-panel">
                      <div class="mvc-panel__head">
                        <h2 class="mvc-panel__title">Plan detail</h2>
                      </div>

                      <div class="mvc-empty" id="pdp-empty">
                        <i class="ph ph-hand-pointing" aria-hidden="true" style="font-size:30px; display:block; margin-bottom:10px;"></i>
                        Choose a plan above to see its full terms.
                      </div>

                      <div id="pdp-content" class="hidden">
                        <div class="mvc-panel__head">
                          <h3 class="mvc-panel__title" id="pdp-name">&mdash;</h3>
                          <span class="mvc-plan__tag" id="pdp-risk" style="display:none;"></span>
                        </div>
                        <div class="mvc-plan__rate" id="pdp-roi">&mdash;</div>
                        <div class="mvc-stat__meta" id="pdp-roi-label">Rate per payout period</div>
                        <ul class="mvc-kv" id="pdp-meta"></ul>
                        <p class="mvc-notice__body" id="pdp-summary"></p>
                      </div>
                    </section>
                  </div>

                  <?php // ACTIVE POSITIONS ?>
                  <section class="mvc-panel">
                    <div class="mvc-panel__head">
                      <h2 class="mvc-panel__title">Your positions</h2>
                    </div>

                    <div class="mvc-scroll-table">
                      <table class="mvc-table mvc-table--quiet">
                        <thead>
                          <tr>
                            <th scope="col">Plan</th>
                            <th scope="col">Invested</th>
                            <th scope="col">Rate</th>
                            <th scope="col">Term</th>
                            <th scope="col">Status</th>
                            <th scope="col">Started</th>
                          </tr>
                        </thead>
                        <tbody id="active-investments-table-body"></tbody>
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

<div id="loader" class="hidden">
  <div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
</div>
<div id="toast-container"></div>

<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/countto.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/dashboard.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/invest.js') ?>" defer></script>
</body>
</html>
