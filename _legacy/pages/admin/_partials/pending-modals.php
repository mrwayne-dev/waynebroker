<?php
// ============================================================
// PENDING QUEUES - shared partial
//
// These six dialogs used to live only in pages/admin/dashboard.php, which is
// why clicking the pending cards on /admin.wallets did nothing but redirect.
// Included by BOTH pages now:
//
//   dashboard.php - the GLOBAL queue, opened from the quick-action buttons
//   wallets.php   - PER-USER, opened from a wallet row's Actions cell
//
// The renderers in assets/js/admin/admin.js take an optional user_id and a
// refresh callback, so one set of markup serves both.
// ============================================================
?>
<!-- =========================================================
PENDING DEPOSITS - SIMPLIFIED MODAL
========================================================= -->
<?php // mvc-modal--wide: six columns will not fit the default
      // 540px dialog. Method / Asset and Paid? are new - without
      // them an admin was crediting a crypto transfer with no
      // idea which chain it arrived on. ?>
<div class="modal mvc-modal--wide" id="pending-deposits-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-overlay" data-modal-close></div>

    <div class="modal-content" tabindex="-1" aria-labelledby="pending-deposits-title">
        <div class="modal-header">
            <div>
                <h2 id="pending-deposits-title">Pending deposits</h2>
                <?php // mvcOpenPendingDeposits() writes the scope here, so it is
                      // obvious whether this is the global queue or one member's. ?>
                <p class="modal-header__sub">Approving a deposit credits the wallet immediately.</p>
            </div>
            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
        </div>

        <div class="modal-body">

            <div class="mvc-scroll-table">
                <table class="mvc-table" id="pending-deposit-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Paid?</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pending-deposits-list">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- =========================================================
CONFIRM / CANCEL A DEPOSIT
Replaces native confirm() and prompt(). Both block the event
loop, prompt() is on a deprecation path, and the old confirm
text was built from a data-amount the cancel button never
carried - so it always read "$0.00".
========================================================= -->
<div class="modal" id="confirm-deposit-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-overlay" data-modal-close></div>
    <div class="modal-content" tabindex="-1" aria-labelledby="confirm-deposit-title">
        <div class="modal-header">
            <div>
                <h2 id="confirm-deposit-title">Credit this deposit?</h2>
                <p class="modal-header__sub">The wallet is credited immediately.</p>
            </div>
            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <ul class="mvc-summary">
                <li class="mvc-summary__row">
                    <span class="k"><i class="ph ph-user"></i> Member</span>
                    <span class="v" id="confirm-deposit-user"></span>
                </li>
                <li class="mvc-summary__row">
                    <span class="k"><i class="ph ph-coins"></i> Asset</span>
                    <span class="v" id="confirm-deposit-asset"></span>
                </li>
                <li class="mvc-summary__row">
                    <span class="k"><i class="ph ph-hash"></i> Reference</span>
                    <span class="v" id="confirm-deposit-reference"></span>
                </li>
                <li class="mvc-summary__row mvc-summary__row--total">
                    <span class="k">Amount</span>
                    <span class="v" id="confirm-deposit-amount"></span>
                </li>
            </ul>
            <div class="modal-actions">
                <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                <button type="button" class="modal-confirm-btn" id="confirm-deposit-btn">Credit wallet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal mvc-modal--danger" id="cancel-deposit-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-overlay" data-modal-close></div>
    <div class="modal-content" tabindex="-1" aria-labelledby="cancel-deposit-title">
        <div class="modal-header">
            <div>
                <h2 id="cancel-deposit-title">Cancel this deposit?</h2>
                <p class="modal-header__sub">The reason is emailed to the member.</p>
            </div>
            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <ul class="mvc-summary">
                <li class="mvc-summary__row">
                    <span class="k"><i class="ph ph-user"></i> Member</span>
                    <span class="v" id="cancel-deposit-user"></span>
                </li>
                <li class="mvc-summary__row mvc-summary__row--total">
                    <span class="k">Amount</span>
                    <span class="v" id="cancel-deposit-amount"></span>
                </li>
            </ul>
            <div class="mvc-field mvc-field--textarea">
                <div class="mvc-field__top">
                    <label class="mvc-field__label" for="cancel-deposit-reason">Reason</label>
                    <span class="mvc-field__hint">At least 10 characters</span>
                </div>
                <div class="mvc-field__row">
                    <textarea class="mvc-field__input" id="cancel-deposit-reason" rows="2" maxlength="400"
                              placeholder="We could not find a matching transfer for this reference."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="button-close-modal tf-button" data-modal-close>Keep pending</button>
                <button type="button" class="modal-confirm-btn" id="cancel-deposit-btn" disabled>Cancel deposit</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
PENDING WITHDRAWALS - MODAL
========================================================= -->
<?php // Was `table.tab-sell-order` with no scroll wrapper: content-driven
      // flex cells that never lined up with their headers, an orange
      // row-hover at 2.81:1, and two buttons squeezed into a 4-column
      // flex row on a phone. Same .mvc-table pattern as its sibling. ?>
<div class="modal mvc-modal--wide" id="pending-withdrawals-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-overlay" data-modal-close></div>

    <div class="modal-content" tabindex="-1" aria-labelledby="pending-withdrawals-title">
        <header class="modal-header">
            <div>
                <h2 id="pending-withdrawals-title">Pending withdrawals</h2>
                <p class="modal-header__sub">Completing a request releases the member's funds.</p>
            </div>
            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
        </header>

        <div class="modal-body">
            <div class="mvc-scroll-table">
                <table class="mvc-table" id="pending-withdrawals-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pending-withdrawals-list">
                        <tr><td class="mvc-empty" colspan="5">Loading withdrawals...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php /* =========================================================
CONFIRM / CANCEL A WITHDRAWAL
Mirrors the deposit dialogs above. These two were still on
native confirm()/prompt(), so an admin releasing funds saw
only "Complete Withdrawal #14?" - no member, no amount.
========================================================= */ ?>
<div class="modal" id="confirm-withdrawal-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-overlay" data-modal-close></div>
    <div class="modal-content" tabindex="-1" aria-labelledby="confirm-withdrawal-title">
        <div class="modal-header">
            <div>
                <h2 id="confirm-withdrawal-title">Release these funds?</h2>
                <p class="modal-header__sub">This cannot be undone from the panel.</p>
            </div>
            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <ul class="mvc-summary">
                <li class="mvc-summary__row">
                    <span class="k"><i class="ph ph-user"></i> Member</span>
                    <span class="v" id="confirm-withdrawal-user"></span>
                </li>
                <li class="mvc-summary__row">
                    <span class="k"><i class="ph ph-bank"></i> Method</span>
                    <span class="v" id="confirm-withdrawal-method"></span>
                </li>
                <li class="mvc-summary__row mvc-summary__row--total">
                    <span class="k">Amount</span>
                    <span class="v" id="confirm-withdrawal-amount"></span>
                </li>
            </ul>
            <div class="modal-actions">
                <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                <button type="button" class="modal-confirm-btn" id="confirm-withdrawal-btn">Release funds</button>
            </div>
        </div>
    </div>
</div>

<div class="modal mvc-modal--danger" id="cancel-withdrawal-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-overlay" data-modal-close></div>
    <div class="modal-content" tabindex="-1" aria-labelledby="cancel-withdrawal-title">
        <div class="modal-header">
            <div>
                <h2 id="cancel-withdrawal-title">Cancel this withdrawal?</h2>
                <p class="modal-header__sub">The amount returns to the member's wallet.</p>
            </div>
            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <ul class="mvc-summary">
                <li class="mvc-summary__row">
                    <span class="k"><i class="ph ph-user"></i> Member</span>
                    <span class="v" id="cancel-withdrawal-user"></span>
                </li>
                <li class="mvc-summary__row mvc-summary__row--total">
                    <span class="k">Amount</span>
                    <span class="v" id="cancel-withdrawal-amount"></span>
                </li>
            </ul>
            <div class="mvc-field mvc-field--textarea">
                <div class="mvc-field__top">
                    <label class="mvc-field__label" for="cancel-withdrawal-reason">Reason</label>
                    <span class="mvc-field__hint">At least 10 characters</span>
                </div>
                <div class="mvc-field__row">
                    <textarea class="mvc-field__input" id="cancel-withdrawal-reason" rows="2" maxlength="400"
                              placeholder="The payout details on file could not be verified."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="button-close-modal tf-button" data-modal-close>Keep pending</button>
                <button type="button" class="modal-confirm-btn" id="cancel-withdrawal-btn" disabled>Cancel withdrawal</button>
            </div>
        </div>
    </div>
</div>
