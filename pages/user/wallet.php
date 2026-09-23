<?php
// pages/user/wallet.php

require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware (use_strict_mode, and cookie_secure that survives
// a TLS-terminating proxy - the inline options this replaced tested
// $_SERVER['HTTPS'] === 'on', which is unset there).
mvcSessionStart();

if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: /login');
    exit;
}
// Retrieve user data from session
$user_name = htmlspecialchars($_SESSION['full_name'] ?? 'User'); // Fallback to 'User' if not set
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;
$user_role = $_SESSION['role'] ?? 'user';

// Withdrawals are gated on identity verification (api/backend/wallet.php).
// Telling the member here, before they fill in a withdrawal form, is the
// difference between a banner and a rejected request.
$kyc_status = 'none';
try {
    require_once __DIR__ . '/../../config/database.php';
    $__st = getPDO()->prepare("SELECT kyc_status FROM users WHERE id = ?");
    $__st->execute([(int) $user_id]);
    $kyc_status = (string) ($__st->fetchColumn() ?: 'none');
} catch (Throwable $e) {
    // A failed lookup must not take the wallet page down. Falling back to
    // 'none' shows the prompt, which is the safe direction to be wrong in.
    error_log('wallet.php: kyc_status lookup failed: ' . $e->getMessage());
}
?>
<?php
  $page_title = "Wallet | Maveren Capital";
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
                <?php $active = "wallet"; include __DIR__ . "/_partials/sidebar.php"; ?>
                <?php include __DIR__ . "/_partials/dock.php"; ?>
                <!-- section-content-right -->
                <div class="section-content-right">
                    <!-- header-dashboard -->
                    <?php $page_heading = "Wallet"; include __DIR__ . "/_partials/topbar.php"; ?>
                    <!-- main-content -->
                    <div class="main-content">
                        <!-- main-content-wrap -->
                        <div class="main-content-inner">
                            <!-- main-content-wrap -->
                            <div class="main-content-wrap">
                                <div class="tf-container">

                                    <?php if ($kyc_status !== 'approved'): ?>
                                    <div class="mvc-kyc-banner mvc-kyc-banner--<?= htmlspecialchars($kyc_status) ?> mb-24">
                                        <i class="ph <?= $kyc_status === 'pending' ? 'ph-hourglass-medium' : 'ph-shield-warning' ?>"></i>
                                        <div class="mvc-kyc-banner__body">
                                            <div class="f14-bold mb-4">
                                                <?php if ($kyc_status === 'pending'): ?>
                                                    Your identity check is under review
                                                <?php elseif ($kyc_status === 'rejected'): ?>
                                                    Your identity check needs attention
                                                <?php else: ?>
                                                    Verify your identity to withdraw
                                                <?php endif; ?>
                                            </div>
                                            <div class="f12-regular">
                                                <?php if ($kyc_status === 'pending'): ?>
                                                    Deposits and investments are unaffected. Withdrawals open as soon as it is approved.
                                                <?php elseif ($kyc_status === 'rejected'): ?>
                                                    We could not verify your last submission. You can send new documents now.
                                                <?php else: ?>
                                                    Deposits and investments stay open. Withdrawals need a verified identity.
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($kyc_status !== 'pending'): ?>
                                            <a href="/dashboard.kyc" class="tf-button bg-Primary f12-bold">
                                                <?= $kyc_status === 'rejected' ? 'Resubmit' : 'Verify now' ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                      <div class="mvc-stack">

                                        <?php // BALANCE + QUICK ACTIONS
                                              // Every id here is written by dashboard.js, and
                                              // data-balance-card / data-balance-toggle / data-copy-text
                                              // drive hide-balance and copy-reference. ?>
                                        <div class="mvc-cols-2">
                                          <div class="mvc-balance" data-balance-card>
                                            <div class="mvc-balance__top">
                                              <span class="mvc-balance__label">
                                                <i class="ph ph-wallet" aria-hidden="true"></i> Total balance (USD)
                                              </span>
                                              <button type="button" class="mvc-balance__eye" data-balance-toggle
                                                      aria-pressed="false" aria-label="Hide balance" title="Hide balance">
                                                <i class="ph ph-eye" aria-hidden="true"></i>
                                              </button>
                                            </div>

                                            <div class="mvc-balance__value">$<span id="total-balance">0.00</span></div>
                                            <div class="mvc-balance__change">
                                              <i class="ph ph-trend-up" aria-hidden="true"></i>
                                              +$<span id="total-earnings">0.00</span> earned to date
                                            </div>

                                            <button type="button" class="mvc-balance__ref" data-copy-text="MVC-MAIN-<?= str_pad((string)(int)$_SESSION['user_id'], 4, '0', STR_PAD_LEFT) ?>"
                                                    title="Copy wallet reference">
                                              <i class="ph ph-copy" aria-hidden="true"></i>
                                              <span>MVC-MAIN-<?= str_pad((string)(int)$_SESSION['user_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                            </button>

                                            <div class="mvc-balance__sub">
                                              <div>
                                                <span>Invested across products</span>
                                                <strong>$<span id="wallet-total-invested">0.00</span></strong>
                                              </div>
                                              <div>
                                                <span>Pending withdrawals</span>
                                                <strong>$<span id="pending-withdrawals">0.00</span></strong>
                                              </div>
                                            </div>
                                          </div>

                                          <div class="mvc-stack">
                                            <section class="mvc-panel">
                                              <div class="mvc-panel__head">
                                                <h2 class="mvc-panel__title">Move money</h2>
                                              </div>
                                              <div class="mvc-quick">
                                                <button type="button" class="mvc-quick__item" data-open-modal="#deposit-modal">
                                                  <span class="mvc-quick__icon"><i class="ph ph-arrow-down" aria-hidden="true"></i></span>
                                                  Deposit
                                                </button>
                                                <button type="button" class="mvc-quick__item" data-open-modal="#withdraw-start-modal">
                                                  <span class="mvc-quick__icon"><i class="ph ph-arrow-up" aria-hidden="true"></i></span>
                                                  Withdraw
                                                </button>
                                                <a href="/dashboard.invest" class="mvc-quick__item">
                                                  <span class="mvc-quick__icon"><i class="ph ph-chart-line-up" aria-hidden="true"></i></span>
                                                  Invest
                                                </a>
                                                <a href="/dashboard.transactions" class="mvc-quick__item">
                                                  <span class="mvc-quick__icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></span>
                                                  History
                                                </a>
                                              </div>
                                            </section>

                                            <section class="mvc-panel">
                                              <div class="mvc-panel__head">
                                                <h2 class="mvc-panel__title">Lifetime activity</h2>
                                              </div>
                                              <ul class="mvc-kv">
                                                <li><span>Total deposited</span><strong>$<span id="total-deposited">0.00</span></strong></li>
                                                <li><span>Total withdrawn</span><strong>$<span id="total-withdrawn">0.00</span></strong></li>
                                                <li><span>Total earnings</span><strong>$<span id="wallet-total-earnings">0.00</span></strong></li>
                                              </ul>
                                            </section>
                                          </div>
                                        </div>

                                        <?php // WHERE THE MONEY IS ?>
                                        <section class="mvc-panel">
                                          <div class="mvc-panel__head">
                                            <h2 class="mvc-panel__title">Your portfolio</h2>
                                            <a href="/dashboard.invest" class="mvc-panel__action">
                                              Manage <i class="ph ph-arrow-right" aria-hidden="true"></i>
                                            </a>
                                          </div>

                                          <ul class="mvc-alloc">
                                            <li><a href="/dashboard.invest">
                                              <span class="mvc-alloc__icon"><i class="ph ph-calendar-dots" aria-hidden="true"></i></span>
                                              <span class="mvc-alloc__meta">
                                                <span class="mvc-alloc__name">Weekly plans</span>
                                                <span class="mvc-alloc__note">A payout every week</span>
                                              </span>
                                              <span class="mvc-alloc__value">$<span id="weekly-invested">0.00</span></span>
                                              <i class="ph ph-caret-right mvc-alloc__arrow" aria-hidden="true"></i>
                                            </a></li>
                                            <li><a href="/dashboard.invest">
                                              <span class="mvc-alloc__icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></span>
                                              <span class="mvc-alloc__meta">
                                                <span class="mvc-alloc__name">Monthly plan</span>
                                                <span class="mvc-alloc__note">A payout every month</span>
                                              </span>
                                              <span class="mvc-alloc__value">$<span id="monthly-invested">0.00</span></span>
                                              <i class="ph ph-caret-right mvc-alloc__arrow" aria-hidden="true"></i>
                                            </a></li>
                                            <li><a href="/dashboard.invest">
                                              <span class="mvc-alloc__icon"><i class="ph ph-trend-up" aria-hidden="true"></i></span>
                                              <span class="mvc-alloc__meta">
                                                <span class="mvc-alloc__name">Next payout</span>
                                                <span class="mvc-alloc__note">due <span id="next-payout-date">&mdash;</span></span>
                                              </span>
                                              <span class="mvc-alloc__value">$<span id="next-payout-amount">0.00</span></span>
                                              <i class="ph ph-caret-right mvc-alloc__arrow" aria-hidden="true"></i>
                                            </a></li>
                                            <li><a href="/dashboard.invest">
                                              <span class="mvc-alloc__icon"><i class="ph ph-chart-line-up" aria-hidden="true"></i></span>
                                              <span class="mvc-alloc__meta">
                                                <span class="mvc-alloc__name">Total invested</span>
                                                <span class="mvc-alloc__note">Capital currently deployed</span>
                                              </span>
                                              <span class="mvc-alloc__value">$<span id="total-investments">0.00</span></span>
                                              <i class="ph ph-caret-right mvc-alloc__arrow" aria-hidden="true"></i>
                                            </a></li>
                                          </ul>
                                        </section>

                                        <?php // AWAITING TRANSFER - revealed by JS only when one exists ?>
                                        <section class="mvc-panel" id="pending-deposits-box" hidden>
                                          <div class="mvc-panel__head">
                                            <h2 class="mvc-panel__title">Awaiting your transfer</h2>
                                            <span class="mvc-stat__meta">Credited once we confirm receipt</span>
                                          </div>
                                          <ul class="mvc-address-list" id="pending-deposits-list"></ul>
                                        </section>

                                        <?php // ACTIVITY - same renderer and markup as
                                              // /dashboard.transactions, so the two views of one
                                              // dataset cannot disagree. ?>
                                        <section class="mvc-panel">
                                          <div class="mvc-panel__head">
                                            <h2 class="mvc-panel__title">Wallet activity</h2>
                                            <a href="/dashboard.transactions" class="mvc-panel__action">
                                              View all <i class="ph ph-arrow-right" aria-hidden="true"></i>
                                            </a>
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
                                              <tbody id="wallet-activity">
                                                <tr><td class="mvc-empty" colspan="5">Loading activity&hellip;</td></tr>
                                              </tbody>
                                            </table>
                                          </div>
                                        </section>

                                      </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /main-content-wrap -->
                        </div>
                        <!-- /main-content-wrap -->
                        
                    </div>
                    <!-- /main-content -->
                </div>
                <!-- /section-content-right -->
            </div>
            <!-- /layout-wrap -->
        </div>
        <!-- /#page -->
    </div>
    <!-- /#wrapper -->

    <!-- Loader -->
    <div id="loader" class="hidden">
        <div class="line-loader">
            <div></div><div></div><div></div><div></div><div></div>
        </div>
    </div>
    <!-- Toast Container -->
    <div id="toast-container"></div>

<!-- ============================================================
     DEPOSIT MODAL
     Holds the fields that used to sit in the inline deposit panel.
     The IDs are unchanged, so bindDepositForm() in dashboard.js works
     against it without modification.
     ============================================================ -->
<div id="deposit-modal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" data-modal>
  <div class="modal-overlay" data-modal-close></div>
  <div class="modal-content" tabindex="-1" aria-labelledby="deposit-modal-title">
    <header class="modal-header">
      <h2 id="deposit-modal-title">Deposit Funds</h2>
      <button class="modal-close" type="button" aria-label="Close modal" data-modal-close>&times;</button>
    </header>
    <div class="modal-body">
      <form id="deposit-form">
        <div class="mvc-field">
          <div class="mvc-field__top">
            <label class="mvc-field__label" for="deposit-amount">Amount to deposit</label>
            <span class="mvc-field__hint" id="deposit-min-hint">Minimum <strong>$1</strong></span>
          </div>
          <div class="mvc-field__row">
            <input class="mvc-field__input" type="number" placeholder="0.00" min="1" step="0.01" id="deposit-amount" inputmode="decimal">
            <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
          </div>
        </div>

        <?php // Two live routes. secure_exchange hands off to the crypto
              // checkout, which issues its own address. deposit_address shows
              // one of the addresses an admin publishes and leaves the
              // transaction pending until they confirm the transfer.
              // The hidden input keeps #deposit-method readable to
              // bindDepositForm exactly as before. ?>
        <div class="mvc-field">
          <div class="mvc-field__top">
            <span class="mvc-field__label">Payment method</span>
          </div>
          <div class="mvc-segment" id="deposit-method-segment" role="radiogroup" aria-label="Payment method">
            <button type="button" class="mvc-segment__btn is-active" data-method="secure_exchange"
                    role="radio" aria-checked="true">
              <i class="ph ph-lightning"></i> Crypto checkout
            </button>
            <button type="button" class="mvc-segment__btn" data-method="deposit_address"
                    role="radio" aria-checked="false" id="deposit-method-manual" hidden>
              <i class="ph ph-qr-code"></i> Deposit address
            </button>
          </div>
          <input type="hidden" id="deposit-method" value="secure_exchange">
        </div>

        <?php // Revealed only for deposit_address; options come from
              // get_deposit_networks, the same fetch the flow already makes. ?>
        <div class="mvc-field hidden" id="deposit-network-field" aria-hidden="true">
          <div class="mvc-field__top">
            <label class="mvc-field__label" for="deposit-network">Coin and network</label>
            <span class="mvc-field__hint" id="deposit-network-hint"></span>
          </div>
          <div class="mvc-field__row">
            <select class="mvc-field__input" id="deposit-network"></select>
          </div>
        </div>

        <ul class="mvc-summary">
          <li class="mvc-summary__row">
            <span class="k"><i class="ph ph-clock"></i> Processing</span>
            <span class="v" id="deposit-summary-time">Instant for crypto</span>
          </li>
          <li class="mvc-summary__row">
            <span class="k"><i class="ph ph-receipt"></i> Fee</span>
            <span class="v">No deposit fee</span>
          </li>
        </ul>

        <div class="modal-actions">
          <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
          <button type="submit" class="modal-confirm-btn">Continue</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================
     WITHDRAW - STEP 1 (amount + method)
     Step 2 is #withdraw-modal below, which collects the payout
     details. bindWithdrawForm() validates here and hands over.
     ============================================================ -->
<div id="withdraw-start-modal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" data-modal>
  <div class="modal-overlay" data-modal-close></div>
  <div class="modal-content" tabindex="-1" aria-labelledby="withdraw-start-title">
    <header class="modal-header">
      <h2 id="withdraw-start-title">Withdraw Funds</h2>
      <button class="modal-close" type="button" aria-label="Close modal" data-modal-close>&times;</button>
    </header>
    <div class="modal-body">
      <form id="withdraw-form">
        <div class="mvc-field">
          <div class="mvc-field__top">
            <span class="mvc-field__label">Amount to withdraw</span>
            <span class="mvc-field__hint">Available <strong>$<span id="withdraw-available">0.00</span></strong></span>
          </div>
          <div class="mvc-field__row">
            <input class="mvc-field__input" type="number" placeholder="0.00" min="1" step="0.01" id="withdraw-amount" inputmode="decimal">
            <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
          </div>
          <?php // Filled by loadWalletSummary() in assets/js/dashboard.js from the
                // admin-set minimum. Stays hidden when there is no minimum, so the
                // form is unchanged for a site that has not set one. ?>
          <span class="mvc-field__hint" id="withdraw-min-hint" hidden></span>
        </div>

        <div class="mvc-field">
          <div class="mvc-field__top">
            <span class="mvc-field__label">Withdrawal method</span>
          </div>
          <div class="mvc-field__row">
            <select class="mvc-field__input" id="withdraw-method">
              <option selected disabled value="">Select method</option>
              <option value="local_bank">Local Bank</option>
              <option value="wallet_address">Wallet Address</option>
            </select>
          </div>
        </div>

        <ul class="mvc-summary">
          <li class="mvc-summary__row">
            <span class="k"><i class="ph ph-clock"></i> Processing time</span>
            <span class="v">1 to 3 business days</span>
          </li>
          <li class="mvc-summary__row">
            <span class="k"><i class="ph ph-shield-check"></i> Review</span>
            <span class="v">Manually approved</span>
          </li>
        </ul>

        <div class="modal-actions">
          <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
          <button type="submit" class="modal-confirm-btn">Continue</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Withdrawal Modal (step 2: payout details) -->
<div id="withdraw-modal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" data-modal>
  <div class="modal-overlay" data-modal-close></div>
  <div class="modal-content" tabindex="-1" aria-labelledby="withdraw-modal-title">
    <header class="modal-header">
      <h2 id="withdraw-modal-title">Withdraw Funds</h2>
      <button class="modal-close" type="button" aria-label="Close modal" data-modal-close>&times;</button>
    </header>

    <div class="modal-body">
      <div class="withdrawal-method">
        <label>Selected Method: <span id="modal-method-name"></span></label>
      </div>

      <div class="form-group">
        <label>Amount to Withdraw</label>
        <input type="text" id="modal-withdraw-amount" readonly>
      </div>

      <!-- Local Bank Fields -->
      <div id="local-bank-fields" class="bank-form hidden" aria-hidden="true">
        <div class="form-group">
          <label for="modal-bank-country">Country</label>
          <select id="modal-bank-country">
            <option value="">Select Country</option>
            <option value="United States of America">United States of America</option>
            <option value="Germany">Germany</option>
            <option value="France">France</option>
            <option value="United Kingdom">United Kingdom</option>
            <option value="Italy">Italy</option>
            <option value="Spain">Spain</option>
            <option value="Netherlands">Netherlands</option>
            <option value="Sweden">Sweden</option>
            <option value="Switzerland">Switzerland</option>
            <option value="Poland">Poland</option>
            <option value="Austria">Austria</option>
            <option value="Greece">Greece</option>
            <option value="Portugal">Portugal</option>
            <option value="Norway">Norway</option>
            <option value="Denmark">Denmark</option>
            <option value="Belgium">Belgium</option>
            <option value="Finland">Finland</option>
            <option value="Ireland">Ireland</option>
            <option value="Czech Republic">Czech Republic</option>
            <option value="Hungary">Hungary</option>
            <option value="Ukraine">Ukraine</option>
          </select>
        </div>

        <div class="form-group">
          <label for="modal-bank-search">Bank</label>
          <div class="bank-search-container">
            <input type="text" id="modal-bank-search" placeholder="Search for a bank..." autocomplete="off">
            <div id="modal-bank-dropdown"></div>
            <input type="hidden" id="modal-bank-name">
          </div>
          <small class="form-error" id="modal-bank-name-error"></small>
        </div>

        <div class="form-group">
          <label for="modal-account-holder">Account Holder Name</label>
          <input type="text" id="modal-account-holder" placeholder="Full Name">
        </div>

        <div class="form-group">
          <label for="modal-iban">IBAN</label>
          <input type="text" id="modal-iban" placeholder="e.g., DE89370400440532013000">
        </div>

        <div class="form-group">
          <label for="modal-bic">BIC/SWIFT Code</label>
          <input type="text" id="modal-bic" placeholder="e.g., DEUTDEFFXXX">
        </div>

        <div class="form-group uk-only">
          <label for="modal-sort-code">Sort Code (UK only)</label>
          <input type="text" id="modal-sort-code" placeholder="e.g., 12-34-56">
        </div>

        <div class="form-group">
          <label for="modal-bank-currency">Currency</label>
          <select id="modal-bank-currency">
            <option value="EUR">EUR</option>
            <option value="USD">USD</option>
            <option value="GBP">GBP</option>
            <option value="CHF">CHF</option>
            <option value="SEK">SEK</option>
            <option value="PLN">PLN</option>
            <option value="CZK">CZK</option>
            <option value="HUF">HUF</option>
            <option value="NOK">NOK</option>
            <option value="DKK">DKK</option>
            <option value="UAH">UAH</option>
          </select>
        </div>

        <div class="form-group">
          <label for="modal-transaction-ref">Transaction Reference</label>
          <input type="text" id="modal-transaction-ref" placeholder="e.g., Withdrawal October 2025">
        </div>

        <small class="form-error" id="withdraw-general-error"></small>
        <p class="note">Local bank conversions are based on currency selected.</p>
      </div>

      <!-- Wallet Address Fields -->
      <div id="wallet-address-fields" class="hidden" aria-hidden="true">
        <div class="form-group">
          <label for="modal-coin">Select Coin</label>
          <select id="modal-coin">
            <option value="btc">Bitcoin (BTC)</option>
            <option value="eth">Ethereum (ETH)</option>
            <option value="usdt">USDT</option>
            <option value="usdc">USDC</option>
          </select>
        </div>

        <div class="form-group">
          <label for="modal-wallet-address">Wallet Address</label>
          <input type="text" id="modal-wallet-address" placeholder="Enter wallet address">
        </div>
      </div>

      <?php // The cash-mailing branch was removed with the method itself. Its
            // JS counterparts in bindWithdrawForm / bindConfirmWithdraw went
            // with it, so nothing looks for #modal-cash-details any more. ?>

      <button type="button" class="modal-confirm-btn" id="confirm-withdraw">
        Confirm Withdrawal
      </button>
    </div>
  </div>
</div>

<!-- ============================================================
     DEPOSIT INSTRUCTIONS  (manual transfer to a published address)

     Replaces #pending-actions-modal, which had a single hardcoded
     method option, used the legacy .form-group dialect and carried a
     second, parallel clipboard mechanism (.copy-btn[data-target]).

     Named "instructions", not "address": #deposit-address-modal is
     already the admin CRUD dialog.

     The address block reuses .mvc-address*, which is already styled and
     already wired to the delegated [data-copy-text] handler - no new
     CSS and no new copy logic.
     ============================================================ -->
<div id="deposit-instructions-modal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" data-modal>
  <div class="modal-overlay" data-modal-close></div>
  <div class="modal-content" tabindex="-1" aria-labelledby="deposit-instructions-title">
    <header class="modal-header">
      <div>
        <h2 id="deposit-instructions-title">Send your deposit</h2>
        <p class="modal-header__sub">Funds are credited once we confirm the transfer.</p>
      </div>
      <button class="modal-close" type="button" aria-label="Close modal" data-modal-close>&times;</button>
    </header>
    <div class="modal-body">

      <div class="mvc-field">
        <div class="mvc-field__top">
          <span class="mvc-field__label">Send exactly</span>
          <span class="mvc-field__hint" id="di-network-label"></span>
        </div>
        <div class="mvc-field__row">
          <span class="mvc-field__input" id="di-amount">0.00</span>
          <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
        </div>
      </div>

      <ul class="mvc-address-list">
        <li class="mvc-address">
          <div class="mvc-address__head">
            <span class="mvc-address__label" id="di-label"></span>
            <span class="mvc-address__meta" id="di-meta"></span>
          </div>
          <div class="mvc-address__row">
            <code class="mvc-address__value" id="di-address"></code>
            <button type="button" class="mvc-address__copy" id="di-address-copy"
                    data-copy-label="Deposit address" aria-label="Copy deposit address">
              <i class="ph ph-copy"></i>
            </button>
          </div>
          <div class="mvc-address__row hidden" id="di-memo-row">
            <span class="mvc-address__memo-label" id="di-memo-label">Memo</span>
            <code class="mvc-address__value" id="di-memo"></code>
            <button type="button" class="mvc-address__copy" id="di-memo-copy"
                    data-copy-label="Memo" aria-label="Copy memo">
              <i class="ph ph-copy"></i>
            </button>
          </div>
          <p class="mvc-address__note hidden" id="di-instructions"></p>
        </li>
      </ul>

      <ul class="mvc-summary">
        <li class="mvc-summary__row">
          <span class="k"><i class="ph ph-hash"></i> Reference</span>
          <span class="v" id="di-reference"></span>
        </li>
        <li class="mvc-summary__row hidden" id="di-conf-row">
          <span class="k"><i class="ph ph-check-circle"></i> Network confirmations</span>
          <span class="v" id="di-conf"></span>
        </li>
        <li class="mvc-summary__row">
          <span class="k"><i class="ph ph-shield-check"></i> Status</span>
          <span class="v" id="di-status">Awaiting your transfer</span>
        </li>
      </ul>

      <?php // Revealed by "I have paid". Optional, but prompted: without a
            // hash the admin is approving on the amount alone. ?>
      <div class="mvc-field mvc-field--textarea hidden" id="di-hash-field">
        <div class="mvc-field__top">
          <label class="mvc-field__label" for="di-tx-hash">Transaction hash</label>
          <span class="mvc-field__hint">Optional, speeds up confirmation</span>
        </div>
        <div class="mvc-field__row">
          <textarea class="mvc-field__input mvc-field__input--mono" id="di-tx-hash" rows="2"
                    maxlength="120" spellcheck="false" placeholder="Paste the hash from your wallet"></textarea>
        </div>
      </div>

      <p class="note">Send only the named asset on the named network. Anything else is unrecoverable.</p>

      <div class="modal-actions">
        <button type="button" class="button-close-modal tf-button" data-modal-close>Close</button>
        <button type="button" class="modal-confirm-btn" id="di-confirm-paid">I have paid</button>
      </div>
    </div>
  </div>
</div>




<!-- core libs: jquery then bootstrap -->
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>

<!-- app/network layer (deferred) -->
<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>

<!-- plugins (deferred if they support it) -->
<script src="<?= mvc_asset('../../assets/js/countto.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>

<!-- main dashboard behaviour (deferred so it runs after DOM is parsed and after api.js) -->
<script src="<?= mvc_asset('../../assets/js/dashboard.js') ?>" defer></script>

    <!-- Iconify CDN -->
</body>
</html>