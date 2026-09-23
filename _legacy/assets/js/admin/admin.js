/**
 * ============================================================
 * Maveren Capital Admin.js (Consolidated and Updated for Deposit and Withdrawal Actions)
 * Purpose: Provides Admin Dashboard data loading, UI binding, and quick action logic.
 * ============================================================
 */
;(function ($) {
    "use strict";

    /**
     * Text-only insertion for table renderers.
     *
     * admin.js loads (deferred) before every page-specific admin script, so
     * each of them can use this instead of interpolating member-supplied
     * display names, emails and plan titles straight into innerHTML - which
     * is what users.js, wallet.js, transactions.js and plans.js all did.
     * The member-side equivalent is escapeHtml() in dashboard.js, which no
     * admin page loads.
     */
    window.mvcEsc = function (v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    };

    // Make formatCurrency globally available
window.formatCurrency = function(amount) {
    if (amount == null || isNaN(Number(amount))) return '0.00';
    return Number(amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

    /* ===================== Core UI Behaviors (Essential Helpers) ===================== */

    var selectImages = function () {
        if ($(".image-select").length > 0) {
            const selectIMG = $(".image-select");
            selectIMG.find("option").each((idx, elem) => {
                const selectOption = $(elem);
                const imgURL = selectOption.attr("data-thumbnail");
                if (imgURL) {
                    selectOption.attr(
                        "data-content",
                        "<img src='%i'/> %s"
                            .replace(/%i/, imgURL)
                            .replace(/%s/, selectOption.text())
                    );
                }
            });
            selectIMG.selectpicker();
        }
    };
    var menuleft = function () {
        if ($('div').hasClass('section-menu-left')) {
            var bt = $(".section-menu-left").find(".has-children");
            bt.on("click", function () {
                var args = { duration: 200 };
                if ($(this).hasClass("active")) {
                    $(this).children(".sub-menu").slideUp(args);
                    $(this).removeClass("active");
                } else {
                    $(".sub-menu").slideUp(args);
                    $(this).children(".sub-menu").slideDown(args);
                    $(".menu-item.has-children").removeClass("active");
                    $(this).addClass("active");
                }
            });
            $('.sub-menu-item').on('click', function(event){
                event.stopPropagation();
            });
        }
    };
    var tabs = function(){
        $('.widget-tabs').each(function(){
            $(this).find('.widget-content-tab').children().hide();
            $(this).find('.widget-content-tab').children(".active").show();
            $(this).find('.widget-menu-tab').find('li').on('click',function(){
                var liActive = $(this).index();
                var contentActive=$(this).siblings().removeClass('active')
                    .parents('.widget-tabs').find('.widget-content-tab')
                    .children().eq(liActive);
                contentActive.addClass('active').fadeIn("slow");
                contentActive.siblings().removeClass('active');
                $(this).addClass('active').parents('.widget-tabs')
                    .find('.widget-content-tab').children().eq(liActive).siblings().hide();
            });
        });
    };
    var collapse_menu = function () {
        $(".button-show-hide").on("click", function () {
            $('.layout-wrap').toggleClass('full-width');
        });
    };
    var showpass = function() {
        $(".show-pass").on("click", function () {
            $(this).toggleClass("active");
            var input = $(this).parents(".password").find(".password-input");
            if (input.attr("type") === "password") {
                input.attr("type", "text");
            } else if (input.attr("type") === "text") {
                input.attr("type", "password");
            }
        });
    };
    var counter = function () {
        var $counter = $(".counter-scroll"); 
        if ($counter.length === 0) return;
        
        if ($().countTo) {
            $('.wallet-card-balance span').each(function () {
                const targetText = $(this).text().replace(/[^0-9.]/g, '');
                const targetValue = Number(targetText);
                if (!isNaN(targetValue) && targetValue > 0) {
                    $(this).countTo({
                        to: targetValue,
                        speed: 1500,
                        decimals: targetText.includes('.') ? 2 : 0 
                    });
                }
            });
        }
    };

    /**
     * Where an approval/cancellation should refresh back to.
     *
     * processDepositAction / processWithdrawalAction used to call
     * loadAdminDashboardData() unconditionally, which means nothing on
     * /admin.wallets - the row vanished but the balance column stayed stale.
     * Whoever opened the queue registers what to refresh instead.
     */
    let pendingQueueRefresh = null;

    function runQueueRefresh() {
        if (typeof pendingQueueRefresh === 'function') {
            pendingQueueRefresh();
        } else if (typeof loadAdminDashboardData === 'function') {
            loadAdminDashboardData();
        }
    }

    /**
     * Open the pending-withdrawals queue.
     *
     * @param {object} opts
     *   userId   - scope to one member; omit for the global queue
     *   userName - shown in the subtitle so the scope is visible
     *   onChange - called after an approval or cancellation
     */
    window.mvcOpenPendingWithdrawals = async function (opts) {
        opts = opts || {};
        pendingQueueRefresh = opts.onChange || null;

        $('#pending-withdrawals-modal .modal-header__sub').text(
            opts.userName
                ? `Pending withdrawals for ${opts.userName}.`
                : "Completing a request releases the member's funds."
        );

        showModal('#pending-withdrawals-modal');

        const listEl = $('#pending-withdrawals-list');
        listEl.html('<tr><td class="mvc-empty" colspan="5">Loading withdrawals...</td></tr>');

        try {
            const params = opts.userId ? { user_id: opts.userId } : {};
            const res = await fetchApi('/api/admin/get_pending_withdrawals.php', params, "GET");

            listEl.empty();

            if (res.status === 'success' && res.data.length > 0) {
                // Text-only insertion: full_name is member-supplied and used to
                // reach innerHTML raw.
                const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();

                res.data.forEach(wd => {
                    const amount = '$' + formatCurrency(wd.amount);
                    listEl.append(`
                        <tr id="withdrawal-row-${wd.id}">
                            <td>${esc(wd.user)}</td>
                            <td class="mvc-td-amount is-out">${amount}</td>
                            <td>${esc(wd.method_label || '—')}</td>
                            <td class="mvc-td-muted">${esc(wd.date)}</td>
                            <td>
                                <button class="complete-withdrawal-btn tf-button bg-Green text-White"
                                    data-id="${wd.id}" data-amount="${wd.amount}"
                                    data-user="${esc(wd.user)}" data-method="${esc(wd.method_label || '')}">Complete</button>
                                <button class="cancel-withdrawal-btn tf-button bg-Accent text-Black"
                                    data-id="${wd.id}" data-amount="${wd.amount}"
                                    data-user="${esc(wd.user)}">Cancel</button>
                            </td>
                        </tr>
                    `);
                });
            } else {
                // An .mvc-empty row inside the table, not a sibling div: the table
                // keeps its header and the empty state lands in the same column box.
                listEl.html('<tr><td class="mvc-empty" colspan="5">No pending withdrawal requests.</td></tr>');
            }

        } catch (err) {
            showToast("Failed to load pending withdrawals", "error");
            listEl.html('<tr><td class="mvc-empty" colspan="5">Could not load withdrawals.</td></tr>');
        }
    };

    // The dashboard quick action opens the same queue, unscoped.
    $(document).on('click', 'a[href="/admin/withdrawals/pending"]', function (e) {
        e.preventDefault();
        window.mvcOpenPendingWithdrawals({});
    });

// ACTION BUTTONS - Complete/Cancel Withdrawals
//
// Were native confirm()/prompt(). Neither could show the member or the
// amount, so "Complete Withdrawal #14?" was the entire brief an admin got
// before releasing funds; the prompt also had no way to enforce a usable
// cancellation reason, and it is on a deprecation path.
let pendingWithdrawalId = null;

$(document).on('click', '.complete-withdrawal-btn', function () {
    const $b = $(this);
    pendingWithdrawalId = $b.data('id');
    $('#confirm-withdrawal-user').text($b.data('user') || '');
    $('#confirm-withdrawal-method').text($b.data('method') || '—');
    $('#confirm-withdrawal-amount').text('$' + formatCurrency($b.data('amount')));
    showModal('#confirm-withdrawal-modal');
});

$(document).on('click', '#confirm-withdrawal-btn', function () {
    if (!pendingWithdrawalId) return;
    closeModal('#confirm-withdrawal-modal');
    processWithdrawalAction(pendingWithdrawalId, 'complete');
    pendingWithdrawalId = null;
});

$(document).on('click', '.cancel-withdrawal-btn', function () {
    const $b = $(this);
    pendingWithdrawalId = $b.data('id');
    $('#cancel-withdrawal-user').text($b.data('user') || '');
    $('#cancel-withdrawal-amount').text('$' + formatCurrency($b.data('amount')));
    $('#cancel-withdrawal-reason').val('');
    $('#cancel-withdrawal-btn').prop('disabled', true);
    showModal('#cancel-withdrawal-modal');
});

// The reason reaches a customer-facing email, so one character is not enough.
$(document).on('input', '#cancel-withdrawal-reason', function () {
    $('#cancel-withdrawal-btn').prop('disabled', $(this).val().trim().length < 10);
});

$(document).on('click', '#cancel-withdrawal-btn', function () {
    const reason = $('#cancel-withdrawal-reason').val().trim();
    if (!pendingWithdrawalId || reason.length < 10) return;
    closeModal('#cancel-withdrawal-modal');
    processWithdrawalAction(pendingWithdrawalId, 'cancel', reason);
    pendingWithdrawalId = null;
});

    
    // --- Utility Functions ---
    let adminActivityChart = null; 
    
    // This is defined globally at the top, kept here for reference if needed
    // function formatCurrency(amount) { /* ... */ }
    
    function showToast(message, type = 'info', timeout = 4000) {
        const container = $('#toast-container');
        if (!container.length) {
            console.error('Toast container #toast-container not found in the DOM.');
            return;
        }

        if (container.children().length > 3) {
            container.children().first().remove();
        }

        let icon = (type === 'success') ? 'ph-check-circle' : (type === 'error' ? 'ph-warning-circle' : 'ph-info');

        const toastEl = $(`
            <div class="toast toast-${type}">
                <i class="ph ${icon}" style="font-size:22px"></i>
                <div class="toast-message">${message}</div>
            </div>
        `);

        container.append(toastEl);

        if (timeout > 0) {
            setTimeout(() => {
                toastEl.remove();
            }, timeout);
        }
    }
    
    // The element that opened the currently visible dialog, so focus can be
    // handed back when it closes.
    let lastModalTrigger = null;

    /**
     * Shows a modal. Uses 'is-open' class to match provided CSS.
     */
    function showModal(selector) {
        const modal = $(selector);
        if (!modal.length) return;
        // Both classes: .is-open drives the CSS, .open is what the member-side
        // helper in dashboard.js sets, so the two dialects stay interchangeable.
        modal.addClass('is-open').addClass('open').attr('aria-hidden', 'false');
        modal.find('[data-modal-close], .button-close-modal, .modal-overlay')
             .off('click.mvcModal').on('click.mvcModal', () => closeModal(selector));

        // Remember the trigger so focus can go back to it on close, rather
        // than to the top of the document.
        lastModalTrigger = document.activeElement;

        // A dialog that reopens where the last one was left scrolled shows its
        // footer and no title.
        modal.find('.modal-body').scrollTop(0);

        setTimeout(() => {
            // Was `input, button, select, textarea`, which in half these
            // dialogs is <input type="hidden"> - and a hidden input cannot
            // take focus, so focus stayed on the trigger BEHIND the overlay
            // while the dialog claimed aria-modal="true". Skip anything that
            // is not really focusable, and prefer the first real control over
            // the close button.
            const focusables = modal.find(
                'input, select, textarea, button, [href], [tabindex]:not([tabindex="-1"])'
            ).filter(function () {
                if (this.disabled || this.type === 'hidden') return false;
                if (this.getAttribute('aria-hidden') === 'true') return false;
                return $(this).is(':visible');
            });

            const preferred = focusables.not('.modal-close, .button-close-modal').first();
            (preferred.length ? preferred : focusables.first()).trigger('focus');
        }, 10);

        $('body').css('overflow', 'hidden');
    }
    
    
    /**
     * Closes a modal and resets the form if it's the email modal.
     */
    function closeModal(selector) {
        const modal = $(selector);
        if (!modal.length) return;
        modal.attr('aria-hidden', 'true');
        setTimeout(() => {
            modal.removeClass('is-open').removeClass('open');
            // Reset form for good UX
            if (selector === '#email-modal') {
                $('#email-form')[0]?.reset();
                $('#email-user-id-group').prop('hidden', true);
                $('#email-user-id').prop('required', false);
            }
        }, 300);
        // Only release the scroll lock once nothing else is open.
        if (!$('.modal.is-open').not(modal).length) {
            $('body').css('overflow', '');
        }

        // Hand focus back to whatever opened the dialog. Without this it fell
        // to the top of the document, so closing a row action lost the row.
        if (lastModalTrigger && document.body.contains(lastModalTrigger)) {
            try { lastModalTrigger.focus(); } catch (e) { /* detached */ }
        }
        lastModalTrigger = null;
    }
    
    // --- Admin Dashboard Core Functions (Data Loading) ---
    
    var loadAdminDashboardData = async function () {
        try {
            // Calls the correct Admin Endpoint
            const res = await fetchApi('/api/admin/dashboard.php');

            if (res.status !== 'success') {
                showToast(res.message || 'Failed to load Admin Dashboard data.', 'error');
                return;
            }

            const m = res.data.metrics;
            const a = res.data.pending_alerts;
            const txns = res.data.recent_activity;
            const chartData = res.data.chart_data;

            // 1. Update Main Cards
            $('#total-revenue').text(formatCurrency(m.total_revenue ?? 0));
            // #total-donations was a leftover from the inherited product: the
            // element exists in no admin page and the API no longer sends it.
            $('#total-aum').text(formatCurrency(m.total_aum ?? 0));
            $('#active-investments').text(m.active_investments ?? 0);
            $('#total-users').text(m.total_users ?? 0);
            
            // 2. Update Quick Action Alerts/Counts
            const depositBtn = $('a[href="/admin/transactions/pending"]');
            depositBtn.html(`
                <i class="ph ph-plus-circle"></i>
                Pending Deposits (${a.deposits ?? 0})
            `);
            const withdrawalBtn = $('a[href="/admin/withdrawals/pending"]');
            withdrawalBtn.html(`
                <i class="ph ph-arrow-square-out"></i>
                Pending Withdrawals (${a.withdrawals ?? 0})
            `);
            
            // Placeholder: Update Activity Info Panel
            $('#peak-activity').text((a.deposits > 0 || a.withdrawals > 0) ? 'Pending Actions' : 'Normal');
            $('#avg-daily-users').text(Math.round((m.total_users ?? 0) / 30) || 0);

            // 3. Update Recent Activity Table
            const tableBody = $('#recent-activity');
            tableBody.empty();

            if (txns && txns.length > 0) {
                const esc = window.mvcEsc;
                txns.forEach(tx => {
                    // Was `text-green`/`text-orange`/`text-red`, lowercase - the
                    // utilities are `.text-Green` etc, so these matched no rule
                    // and every status rendered as plain body text. A .box-status
                    // chip is what the reference table uses.
                    const status = String(tx.status || '').toLowerCase();
                    const badge = status === 'completed' ? 'bg-Green'
                                : status === 'pending'   ? 'bg-Orange'
                                : 'bg-Salmon';
                    // Deposits arrive, withdrawals leave. Painting every amount
                    // orange meant nothing stood out.
                    const dir = String(tx.type || '').toLowerCase().indexOf('withdraw') === 0 ? 'is-out' : 'is-in';
                    const row = `
                        <tr>
                            <td class="mvc-td-muted">${esc(tx.date)}</td>
                            <td>${esc(tx.user)}</td>
                            <td>${esc(tx.type)}</td>
                            <td class="mvc-td-amount ${dir}">$${formatCurrency(tx.amount)}</td>
                            <td><div class="box-status ${badge}"><span class="font-poppins">${esc(status.toUpperCase())}</span></div></td>
                        </tr>`;
                    tableBody.append(row);
                });
            } else {
                tableBody.append('<tr><td class="mvc-empty" colspan="5">No recent system activity.</td></tr>');
            }
            
            // 4. Render Doughnut Chart
            renderActivityChart(chartData);
            
            // 5. Rerun the counter animation for a fresh count
            counter();

        } catch (error) {
            console.error('Error loading Admin Dashboard:', error);
            showToast('An error occurred while loading dashboard data.', 'error');
        }
    };

    function renderActivityChart(data) {
        const ctx = document.getElementById("activityChart");
        if (!ctx) return;
        
        const datasetValues = [
            data.revenue || 0,
            data.investments || 0,
            data.users || 0
        ];

        // --Primary is declared on body.mvc-dash, not on <html>, so reading it
        // off documentElement always came back empty and the chart silently
        // fell back to an indigo that is nowhere in the brand palette.
        const brand = getComputedStyle(document.body).getPropertyValue("--Primary").trim();
        const colors = [
            brand || '#FF5B04',
            '#FFC107',
            '#9C27B0'
        ];
        
        if (adminActivityChart instanceof Chart) {
            adminActivityChart.destroy();
        }

        adminActivityChart = new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: ["Revenue", "Invested", "Users"],
                datasets: [{
                    data: datasetValues,
                    backgroundColor: colors,
                    borderWidth: 0,
                    cutout: "70%"
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
        
        // Order must match datasetValues above and the legend in the markup.
        const chartLabels = ['chart-revenue', 'chart-investments', 'chart-users'];
        
        chartLabels.forEach((id, i) => {
            $(`#${id}`).text(`${(datasetValues[i] ?? 0).toFixed(1)}%`);
        });
    }

    // --- Quick Action Bindings ---
    function bindQuickActions() {
        // Only bind the email button now
        $('#send-email-btn').on('click', function() {
            showModal('#email-modal');
        });
        
        // Toggle User ID field based on selection
        // slideDown/slideUp wrote an INLINE display, which beats the `hidden`
        // attribute - so the field stayed exposed to assistive tech either way.
        // Toggle the attribute instead; it is the only thing that has to agree
        // with `required`, and a hidden required field blocks submit silently.
        $('#email-recipients').on('change', function() {
            const isSpecific = $(this).val() === 'specific';
            $('#email-user-id-group').prop('hidden', !isSpecific);
            $('#email-user-id').prop('required', isSpecific);
            if (!isSpecific) $('#email-user-id').val('');
            if (isSpecific) $('#email-user-id').trigger('focus');
        });
        
        // Close modals via ESC key. Was hardcoded to #email-modal, so every
        // other admin dialog ignored Escape.
        $(document).on('keydown', function (e) {
            if (e.key !== 'Escape') return;
            const open = $('.modal.is-open');
            if (open.length) closeModal('#' + open.last().attr('id'));
        });
    }
    
    // --- Send Email Form Handler ---
    function bindEmailForm() {
        $('#email-form').on('submit', async function(e) {
            e.preventDefault();
            
            // Show loader/progress toast immediately
            showToast('Preparing and sending emails. This may take a moment...', 'info', 5000);
            
            const recipient_group = $('#email-recipients').val();
            const user_id = $('#email-user-id').val();
            const subject = $('#email-subject').val();
            const body = $('#email-body').val();
            const priority = $('#email-priority').val();

            if (!recipient_group || recipient_group === '') {
                showToast('Please select a recipient group.', 'warning');
                return;
            }
            if (recipient_group === 'specific' && (!user_id || user_id.trim() === '' || isNaN(user_id))) {
                showToast('Please enter a valid numeric User ID for specific recipients.', 'warning');
                return;
            }
            if (!subject || !body) {
                showToast('Subject and Message Body are required.', 'warning');
                return;
            }

            try {
                
                const payload = {
                    recipient_group: recipient_group,
                    subject: subject,
                    body: body,
                    priority: priority
                };

                if (recipient_group === 'specific') {
                    payload.user_id = user_id.trim();
                }

                // Assuming fetchApi is available globally
                const res = await fetchApi('/api/admin/email.php', payload, 'POST'); 

                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    closeModal('#email-modal');
                } else {
                    showToast(res.message || 'Failed to send email.', 'error');
                }
            } catch (error) {
                console.error('Email submission error:', error);
                showToast('A network error occurred or the server failed to respond.', 'error');
            }
        });
    }


    /**
     * Sends the complete/cancel action for a deposit to the backend.
     * @param {number} id - The transaction ID.
     * @param {string} action - 'complete' or 'cancel'.
     * @param {string} [reason=''] - Reason for cancellation.
     */
    async function processDepositAction(id, action, reason = '') {
        showToast(`${action.charAt(0).toUpperCase() + action.slice(1)}ing transaction #${id}...`, 'info', 6000);
        
        try {
            // Using the new endpoint
            const res = await fetchApi('/api/admin/process_deposit.php', {
                id: id,
                action: action,
                reason: reason
            }, 'POST');
            
            if (res.status === 'success') {
                showToast(res.message, 'success');
                // Both rows: each deposit with a snapshot renders a second,
                // collapsible detail <tr>. Leaving it behind meant the
                // "no pending deposits" check below could never fire.
                $(`#deposit-row-${id}, #deposit-detail-${id}`).remove();
                // Refresh main dashboard stats (to update pending counts)
                runQueueRefresh();
                // Check if table is now empty
                if ($('#pending-deposits-list').children().length === 0) {
                    $('#pending-deposits-list')
                        .html('<tr><td class="mvc-empty" colspan="6">No pending deposit requests.</td></tr>');
                }
            } else {
                showToast(res.message || `Failed to ${action} deposit.`, 'error');
            }

        } catch (err) {
            console.error('Deposit action error:', err);
            showToast('A network error occurred or the server failed to respond.', 'error');
        }
    }
    
    // ===============================================
    // NEW: PROCESS WITHDRAWAL (Reusable like deposit version)
    // ===============================================
    async function processWithdrawalAction(id, action, reason = '') {
        showToast(`${action.charAt(0).toUpperCase() + action.slice(1)}ing withdrawal #${id}...`, 'info');

        try {
            const res = await fetchApi('/api/admin/process_withdrawal.php', {
                id: id,
                action: action,
                reason: reason
            }, 'POST');

            if (res.status === 'success') {
                showToast(res.message, 'success');
                // Remove the row from the table
                $(`#withdrawal-row-${id}`).remove();
                // Refresh whatever opened the queue - the dashboard metrics or
                // the wallets table. See pendingQueueRefresh.
                runQueueRefresh();
                // Check if table is now empty
                if ($('#pending-withdrawals-list').children().length === 0) {
                    $('#pending-withdrawals-list')
                        .html('<tr><td class="mvc-empty" colspan="5">No pending withdrawal requests.</td></tr>');
                }
            } else {
                showToast(res.message || 'Failed to process withdrawal.', 'error');
            }

        } catch (err) {
            showToast('Server error occurred.', 'error');
        }
    }

    // --- Initialization ---
    $(function () {
        // Core UI Bindings 
        selectImages();
        menuleft();
        tabs();
        collapse_menu();
        showpass();
        
        // Admin-specific bindings
        bindQuickActions(); 
        bindEmailForm();

        // ------------------------------------------------
        // DEPOSIT ACTIONS (Existing Handlers)
        // ------------------------------------------------

        // Text-only insertion. Addresses and hashes are user-influenced and
        // must never reach innerHTML.
        function escAdmin(v) {
            return $('<div>').text(v == null ? '' : String(v)).html();
        }

        /**
         * Open the pending-deposits queue.
         *
         * Same shape as mvcOpenPendingWithdrawals: optional userId scope,
         * optional refresh callback. The dashboard quick action calls it
         * unscoped; a wallet row calls it for one member.
         */
        window.mvcOpenPendingDeposits = async function (opts) {
            opts = opts || {};
            pendingQueueRefresh = opts.onChange || null;
            const scopedUserId = opts.userId || 0;

            $('#pending-deposits-modal .modal-header__sub').text(
                opts.userName
                    ? `Pending deposits for ${opts.userName}.`
                    : 'Approving a deposit credits the wallet immediately.'
            );

            showModal('#pending-deposits-modal');
            $('#pending-deposits-list')
                .html('<tr><td class="mvc-empty" colspan="6">Loading deposits...</td></tr>');

            try {
                // Fetch the deposits from the updated endpoint
                const params = scopedUserId ? { user_id: scopedUserId } : {};
                const res = await fetchApi('/api/admin/get_pending_deposits.php', params, "GET");

                const listEl = $('#pending-deposits-list');

                listEl.empty(); // Clear old rows

                if (res.status === 'success' && res.data.length > 0) {

                    res.data.forEach(dep => {
                        const asset = dep.asset ? `${escAdmin(dep.asset)} · ${escAdmin(dep.network)}` : '—';
                        const paid = dep.marked_paid
                            ? `<div class="box-status bg-Green"><span class="key-sort">Marked paid</span></div>`
                            : `<span class="text-Gray f12-regular">Not yet</span>`;

                        // data-amount and data-user now on BOTH buttons. The cancel
                        // button carried neither, while the handler read data-amount -
                        // so every cancellation prompt said "$0.00".
                        listEl.append(`
                            <tr id="deposit-row-${dep.id}">
                                <td>${dep.user}</td>
                                <td class="mvc-td-amount is-in">$${formatCurrency(dep.amount)}</td>
                                <td>${escAdmin(dep.method_label)}<div class="f12-regular text-Gray">${asset}</div></td>
                                <td>${paid}</td>
                                <td class="mvc-td-muted">${dep.date}</td>
                                <td>
                                    ${dep.address ? `<button class="tf-button f12-bold deposit-details-btn"
                                        data-id="${dep.id}" aria-expanded="false"
                                        aria-controls="deposit-detail-${dep.id}">Details</button>` : ''}
                                    <button class="complete-deposit-btn tf-button bg-Green text-White"
                                        data-id="${dep.id}" data-amount="${dep.amount}"
                                        data-user="${dep.user}" data-asset="${asset}"
                                        data-reference="${dep.reference}">Complete</button>
                                    <button class="cancel-deposit-btn tf-button bg-Accent text-Black"
                                        data-id="${dep.id}" data-amount="${dep.amount}"
                                        data-user="${dep.user}">Cancel</button>
                                </td>
                            </tr>
                        `);

                        if (dep.address) {
                            listEl.append(`
                                <tr id="deposit-detail-${dep.id}" class="mvc-detail-row hidden">
                                    <td colspan="6">
                                        <ul class="mvc-address-list"><li class="mvc-address">
                                            <div class="mvc-address__head">
                                                <span class="mvc-address__label">${escAdmin(dep.address_label)}</span>
                                                <span class="mvc-address__meta">Ref ${escAdmin(dep.reference)}</span>
                                            </div>
                                            <div class="mvc-address__row">
                                                <code class="mvc-address__value">${escAdmin(dep.address)}</code>
                                            </div>
                                            ${dep.memo_tag ? `<div class="mvc-address__row">
                                                <span class="mvc-address__memo-label">${escAdmin(dep.memo_label || 'Memo')}</span>
                                                <code class="mvc-address__value">${escAdmin(dep.memo_tag)}</code>
                                            </div>` : ''}
                                            ${dep.tx_hash ? `<div class="mvc-address__row">
                                                <span class="mvc-address__memo-label">Tx hash</span>
                                                <code class="mvc-address__value">${escAdmin(dep.tx_hash)}</code>
                                            </div>` : ''}
                                            ${dep.marked_paid_at ? `<p class="mvc-address__note">Member marked this paid on ${escAdmin(dep.marked_paid_at)}.</p>` : ''}
                                        </li></ul>
                                    </td>
                                </tr>
                            `);
                        }
                    });

                } else {
                    // An .mvc-empty row inside the table rather than a sibling
                    // div, so the header stays and the message lands in the
                    // column box. Same as the withdrawals queue.
                    listEl.html('<tr><td class="mvc-empty" colspan="6">No pending deposit requests.</td></tr>');
                }

            } catch (err) {
                showToast("Failed to load pending deposits", "error");
                $('#pending-deposits-list')
                    .html('<tr><td class="mvc-empty" colspan="6">Could not load deposits.</td></tr>');
            }
        };

        // The dashboard quick action opens the same queue, unscoped.
        $(document).on('click', 'a[href="/admin/transactions/pending"]', function (e) {
            e.preventDefault();
            window.mvcOpenPendingDeposits({});
        });

        // Per-row disclosure for the address / memo / transaction hash.
        $(document).on('click', '.deposit-details-btn', function () {
            const id = $(this).data('id');
            const $row = $(`#deposit-detail-${id}`);
            const open = !$row.hasClass('hidden');
            $row.toggleClass('hidden', open);
            $(this).attr('aria-expanded', String(!open));
        });

        // The pending deposit currently staged in a confirm/cancel dialog.
        let pendingDepositId = null;

        // ACTION HANDLER: Complete Deposit
        //
        // Was a native confirm(). Both confirm() and prompt() block the event
        // loop, prompt() is on a deprecation path, and neither could show the
        // asset or reference an admin needs to check before moving money.
        $(document).on('click', '.complete-deposit-btn', function () {
            const $b = $(this);
            pendingDepositId = $b.data('id');
            $('#confirm-deposit-user').text($b.data('user') || '');
            $('#confirm-deposit-asset').text($b.data('asset') || '—');
            $('#confirm-deposit-reference').text($b.data('reference') || '');
            $('#confirm-deposit-amount').text('$' + formatCurrency($b.data('amount')));
            showModal('#confirm-deposit-modal');
        });

        // Delegated, not a direct bind: the modals come from a shared partial
        // now, and a direct bind requires the markup to exist at ready.
        $(document).on('click', '#confirm-deposit-btn', function () {
            if (!pendingDepositId) return;
            closeModal('#confirm-deposit-modal');
            processDepositAction(pendingDepositId, 'complete');
            pendingDepositId = null;
        });

        // ACTION HANDLER: Cancel Deposit (reason is required, and is emailed)
        $(document).on('click', '.cancel-deposit-btn', function () {
            const $b = $(this);
            pendingDepositId = $b.data('id');
            $('#cancel-deposit-user').text($b.data('user') || '');
            $('#cancel-deposit-amount').text('$' + formatCurrency($b.data('amount')));
            $('#cancel-deposit-reason').val('');
            $('#cancel-deposit-btn').prop('disabled', true);
            showModal('#cancel-deposit-modal');
        });

        // The reason goes straight into a customer-facing email, so a
        // one-character answer is not good enough. prompt() gave no way to
        // enforce that at all.
        $(document).on('input', '#cancel-deposit-reason', function () {
            $('#cancel-deposit-btn').prop('disabled', $(this).val().trim().length < 10);
        });

        $(document).on('click', '#cancel-deposit-btn', function () {
            const reason = $('#cancel-deposit-reason').val().trim();
            if (!pendingDepositId || reason.length < 10) return;
            closeModal('#cancel-deposit-modal');
            processDepositAction(pendingDepositId, 'cancel', reason);
            pendingDepositId = null;
        });




        // The "save deposit address" and "view deposit addresses" handlers
        // lived here. They wrote and read a single address per method into two
        // hardcoded `settings` columns. Addresses are now a table with a row
        // per chain, driven by assets/js/admin/deposit_addresses.js against
        // /api/admin/deposit_addresses.php.

        // Initial data load 
        loadAdminDashboardData(); 

        window.refreshDashboard = loadAdminDashboardData;

        // ------- Expose Global Modal Functions -------
        window.showModal = showModal;
        window.closeModal = closeModal;
        window.formatCurrency = formatCurrency; // Ensure it's globally available if needed

    });

})(jQuery);