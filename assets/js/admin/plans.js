/**
 * FILE: /assets/js/admin/plans.js
 * ============================================================
 * Maveren Capital Admin Funds.js
 * Purpose: Frontend logic for the Admin Fund Management (XYields) page.
 * Handles: Metrics, XYield Plan CRUD, Active XYield list/pagination/edit.
 * Assumes global utility functions (fetchApi, formatCurrency, showToast, showModal, closeModal) are available.
 * ============================================================
 */
;(function ($) {
    "use strict";

    // Global state for tables and search
    let currentActivePage = 1;
    let currentSearchTerm = '';
    
    // --- UI Helpers ---

    /** Renders a status badge. */
    function renderStatusBadge(status) {
        status = status.toLowerCase();

        let badgeClass = 'bg-Gray';
        if (status === 'active') {
            badgeClass = 'bg-Green';
        } else if (status === 'completed') {
            badgeClass = 'bg-Primary';
        } else if (status === 'hidden' || status === 'cancelled') {
            badgeClass = 'bg-Orange';
        }

        return `<div class="box-status ${badgeClass}"><span class="font-poppins key-sort">${status.charAt(0).toUpperCase() + status.slice(1)}</span></div>`;
    }
    
    // --- Core Data Fetcher & UI Renderer ---

    /**
     * Loads all data for the funds dashboard (Metrics, Plans, Active XYields).
     */
    async function loadFundsDashboard() {
        // Show loading indicators
        const plansBody = $('#plans-table-body');
        const activeBody = $('#active-investments-body');
        plansBody.empty().html('<tr><td class="mvc-empty" colspan="6">Loading plans...</td></tr>');
        activeBody.empty().html('<tr><td class="mvc-empty" colspan="8">Loading active investments...</td></tr>');
        $('#active-pagination').empty();

        try {
            const res = await fetchApi('/api/admin/plans.php', {
                search: currentSearchTerm,
                active_page: currentActivePage
            }, "GET");

            if (res.status !== 'success') {
                window.showToast(res.message || 'Failed to load dashboard data.', 'error');
                plansBody.html('<tr><td class="mvc-empty" colspan="6">Error loading plans.</td></tr>');
                activeBody.html('<tr><td class="mvc-empty" colspan="8">Error loading investments.</td></tr>');
                return;
            }

            const data = res.data;
            updateMetrics(data.metrics);
            renderPlansTable(data.plans);
            renderActiveXYieldsTable(data.active_investments);
            renderActivePagination(data.active_page, data.active_total_pages);
            
            if (typeof window.counter === 'function') {
                window.counter();
            }

        } catch (error) {
            console.error('API Error loading funds dashboard:', error);
            window.showToast('A network error occurred while fetching data.', 'error');
            plansBody.html('<tr><td class="mvc-empty" colspan="6">Network error.</td></tr>');
            activeBody.html('<tr><td class="mvc-empty" colspan="8">Network error.</td></tr>');
        }
    }

    /**
     * Updates the summary cards.
     */
    function updateMetrics(m) {
        if (!m) return;
        const format = window.formatCurrency || ((amount) => Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        
        $('#total-active-invest').text(format(m.total_active_invest ?? 0));
        $('#total-roi-paid').text(format(m.total_roi_paid ?? 0));
        $('#ongoing-plans').text(m.ongoing_plans_count ?? 0); 
        $('#next-maturity').text(m.next_maturity ?? '—');
    }
    
    /**
     * Renders the XYield Plans table.
     */
    function renderPlansTable(plans) {
        const tableBody = $('#plans-table-body');
        tableBody.empty();

        if (!plans || plans.length === 0) {
            tableBody.html('<tr><td class="mvc-empty" colspan="6">No investment plans defined.</td></tr>');
            return;
        }

        const esc = window.mvcEsc;

        plans.forEach(plan => {
            const nextStatus = plan.status === 'active' ? 'hidden' : 'active';
            /* Inline buttons, not a Bootstrap dropdown - .mvc-scroll-table is
               `overflow-x: auto` and clips an absolutely positioned menu. */
            const row = `
                <tr data-plan-id="${plan.id}"
                    data-min="${plan.min_amount}" data-max="${plan.max_amount}"
                    data-roi="${plan.roi_percent}" data-cadence="${esc(plan.cadence)}"
                    data-duration="${plan.duration_days}" data-risk="${esc(plan.risk)}">
                    <td><span class="f14-bold">${esc(plan.title)}</span></td>
                    <td class="mvc-td-muted">${esc(plan.term_display)}</td>
                    <td class="mvc-td-amount is-in">${esc(plan.roi_display)}</td>
                    <td class="mvc-td-muted">${esc(plan.risk)}</td>
                    <td>${renderStatusBadge(plan.status)}</td>
                    <td>
                        <button type="button" class="tf-button f12-bold action-edit-plan" data-id="${plan.id}">Edit</button>
                        <button type="button" class="tf-button f12-bold bg-Accent text-Black action-toggle-status"
                            data-id="${plan.id}" data-status="${nextStatus}">${plan.status === 'active' ? 'Hide' : 'Activate'}</button>
                    </td>
                </tr>
            `;
            tableBody.append(row);
        });
    }

    /**
     * Renders the Active XYields table.
     */
    function renderActiveXYieldsTable(investments) {
        const tableBody = $('#active-investments-body');
        tableBody.empty();

        if (!investments || investments.length === 0) {
            tableBody.html('<tr><td class="mvc-empty" colspan="8">No active investments found.</td></tr>');
            return;
        }

        const esc = window.mvcEsc;

        investments.forEach(inv => {
            const row = `
                <tr data-inv-id="${inv.id}">
                    <td>
                        <span class="f14-bold">${esc(inv.user_name)}</span>
                        <div class="f12-regular text-Gray">${esc(inv.user_email)}</div>
                    </td>
                    <td>${esc(inv.plan_name)}</td>
                    <td class="mvc-td-amount">$${window.formatCurrency(inv.amount)}</td>
                    <td class="mvc-td-muted">${esc(inv.roi_percent)}%</td>
                    <td>${renderStatusBadge(inv.status)}</td>
                    <td class="mvc-td-muted">${esc(inv.date_started)}</td>
                    <td class="mvc-td-muted">${esc(inv.maturity_date)}</td>
                    <td>
                        <button type="button" class="tf-button f12-bold action-edit-investment" data-id="${inv.id}">Edit</button>
                    </td>
                </tr>
            `;
            tableBody.append(row);
        });
    }
    
    /**
     * Renders the pagination for the Active Investments table.
     * Shared renderer - the local one had no Previous/Next and no window.
     */
    function renderActivePagination(currentPage, totalPages) {
        window.mvcRenderPagination('#active-pagination', {
            page: currentPage,
            pages: totalPages,
            onPage: function (n) {
                currentActivePage = n;
                loadFundsDashboard();
            },
        });
    }

    // --- Modal Logic ---

    /** Prepares the modal for a new plan entry. */
    function setupAddPlanModal() {
        $('#plan-modal-title').text('Add New Plan');
        $('#plan-form')[0].reset();
        $('#plan-id').val(''); 
        $('#plan-risk').val('low');
        $('#plan-status').val('active');
        $('#plan-cadence').val('monthly');
        $('.modal-confirm-btn').text('Save Plan').removeClass('bg-Accent text-Black').addClass('bg-Primary text-White');
        window.showModal('#plan-modal');
    }

    /** Fetches plan data and populates the Edit Plan modal. */
    async function setupEditPlanModal(id) {
        try {
            window.showToast('Loading plan details...', 'info');
            const res = await fetchApi('/api/admin/plans.php', {
                fetch: 'plan_details',
                id: id
            }, "GET");

            if (res.status === 'success') {
                const plan = res.data;
                
                $('#plan-modal-title').text(`Edit Plan: ${plan.title}`);
                $('#plan-id').val(plan.id);
                $('#plan-name').val(plan.title);
                $('#plan-min').val(plan.min_amount);
                $('#plan-max').val(plan.max_amount);
                $('#plan-roi').val(plan.roi_percent);
                $('#plan-cadence').val((plan.cadence || 'monthly').toLowerCase());
                $('#plan-duration').val(plan.duration_days);
                $('#plan-risk').val(plan.risk.toLowerCase());
                $('#plan-status').val(plan.status.toLowerCase());
                
                $('.modal-confirm-btn').text('Update Plan').removeClass('bg-Primary text-White').addClass('bg-Accent text-Black');
                window.showModal('#plan-modal');
                window.showToast('Plan details loaded.', 'success');
            } else {
                window.showToast(res.message || 'Failed to load plan details for editing.', 'error');
            }
        } catch (error) {
            console.error('Error loading plan details:', error);
            window.showToast('Network error while fetching details.', 'error');
        }
    }

    /**
     * Load a position into the manage dialog.
     *
     * The dialog shows the schedule as READ-ONLY facts and offers intents.
     * The API derives payouts_total/maturity_date together, so nothing here
     * can produce the combinations that corrupt the cron's catch-up loop.
     */
    async function setupEditXYieldModal(id) {
        try {
            const res = await fetchApi('/api/admin/plans.php', {
                fetch: 'investment_details',
                id: id
            }, "GET");

            if (res.status !== 'success') {
                window.showToast(res.message || 'Failed to load the position.', 'error');
                return;
            }

            const inv = res.data;
            const esc = window.mvcEsc;
            const money = (v) => '$' + window.formatCurrency(v);
            const isActive = String(inv.status).toLowerCase() === 'active';

            $('#inv-id').val(inv.id);
            $('#inv-subtitle').text(isActive
                ? 'Active position. Changes apply from the next payout.'
                : `This position is ${inv.status} - it is closed and the cron will not touch it.`);

            $('#inv-user').text(inv.user_display || '');
            $('#inv-plan').text(`${inv.plan_name} (${inv.cadence})`);
            $('#inv-progress').text(`${inv.payouts_made} of ${inv.payouts_total} payouts`);
            $('#inv-next').text(isActive ? formatDate(inv.next_payout_date) : '—');
            $('#inv-maturity').text(formatDate(inv.maturity_date));
            $('#inv-earned').text(money(inv.roi_earned));

            $('#inv-roi').val(inv.roi_percent);
            $('#inv-per-payout').text(`${money(inv.per_payout)} per payout`);
            $('#inv-payouts-total').val(inv.payouts_total);
            $('#inv-term-hint').text(`${inv.payouts_made} already paid`);
            $('#inv-bonus').val('');

            // Everything actionable is disabled on a closed position rather than
            // hidden, so the dialog still explains what happened.
            $('#inv-roi, #inv-bonus, #inv-payouts-total, #inv-save-rate, #inv-add-bonus, #inv-save-term, #inv-settle, #inv-cancel')
                .prop('disabled', !isActive);
            $('#inv-actions').prop('open', isActive);

            window.showModal('#edit-investment-modal');
        } catch (error) {
            console.error('Error loading investment details:', error);
            window.showToast('Network error while fetching details.', 'error');
        }
    }

    /** yyyy-mm-dd -> "Aug 07, 2026". Returns an em dash for a null date. */
    function formatDate(d) {
        if (!d) return '—';
        const dt = new Date(d + 'T00:00:00');
        if (isNaN(dt)) return d;
        return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' });
    }

    /**
     * POST one position action and refresh the dialog in place, so the summary
     * reflects what just happened without the admin reopening it.
     */
    async function runInvestmentAction(payload, opts) {
        opts = opts || {};
        const id = $('#inv-id').val();
        if (!id) return;

        try {
            const res = await fetchApi('/api/admin/plans.php', Object.assign({ id: id }, payload), "POST");
            if (res.status === 'success') {
                window.showToast(res.message, 'success');
                loadFundsDashboard();
                if (opts.close) {
                    window.closeModal('#edit-investment-modal');
                } else {
                    setupEditXYieldModal(id);   // re-read, do not guess
                }
            } else {
                window.showToast(res.message || 'That action did not go through.', 'error');
            }
        } catch (err) {
            console.error('Investment action error:', err);
            window.showToast('Network error. Nothing was changed.', 'error');
        }
    }

    /**
     * Stage a close action in the confirm dialog.
     *
     * Reads the figures already on screen rather than refetching: the manage
     * dialog was populated from the server moments ago, and the API re-checks
     * everything under FOR UPDATE anyway.
     */
    let pendingCloseMode = null;

    function stageInvestmentClose(mode) {
        pendingCloseMode = mode;

        const isCancel = mode === 'cancel';
        $('#close-investment-sub').text(isCancel
            ? 'The principal goes back to the wallet and the position is unwound.'
            : 'The principal is released and the position is marked complete.');

        $('#close-inv-user').text($('#inv-user').text());
        $('#close-inv-plan').text($('#inv-plan').text());
        $('#close-inv-label').text('Returns to wallet');
        // The principal is not on screen as a field any more, so state what the
        // member has already been paid, which is the figure that surprises people.
        $('#close-inv-amount').text('Principal + ' + $('#inv-earned').text() + ' already paid');
        $('#close-inv-note').text(isCancel
            ? 'Recorded as a refund. Remaining scheduled payouts will not happen.'
            : 'Recorded as a release. Payouts already made are not reversed.');
        $('#close-investment-btn').text(isCancel ? 'Cancel & refund' : 'Settle now');

        window.showModal('#close-investment-modal');
    }

    $(document).on('click', '#close-investment-btn', function () {
        if (!pendingCloseMode) return;
        const mode = pendingCloseMode;
        pendingCloseMode = null;
        window.closeModal('#close-investment-modal');
        runInvestmentAction({ action: 'investment_close', mode: mode }, { close: true });
    });

    // --- Interaction Binding ---
    function bindInteractions() {
        
        // 1. Add Plan Button
        $('#add-plan-btn').on('click', function() {
            setupAddPlanModal();
        });
        
        // 2. Edit Plan Action Button (delegated click handler on plans table)
        $(document).on('click', '.action-edit-plan', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = $(this).data('id');
            setupEditPlanModal(id);
        });

        // 3. Edit XYield Action Button (delegated click handler on investments table)
        $(document).on('click', '.action-edit-investment', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = $(this).data('id');
            setupEditXYieldModal(id);
        });
        
        // 4. Toggle Plan Status (for 'active' or 'hidden') - uses edit_plan action
        $(document).on('click', '.action-toggle-status', async function(e) {
             e.preventDefault();
             e.stopPropagation();
             const id = $(this).data('id');
             const newStatus = $(this).data('status'); // 'active' or 'hidden'
             const title = $(this).closest('tr').find('.f14-bold').text();

             const ok = await mvcConfirm({
                 title: `Set "${title}" to ${newStatus}?`,
                 body: newStatus === 'active'
                     ? 'The plan becomes visible on the public site and members can invest in it.'
                     : 'The plan is hidden from the public site. Investments already running are unaffected.',
                 confirmLabel: 'Change status',
             });
             if (!ok) return;

             // edit_plan is a full update, so the row's current values have to be
             // resent or they would be overwritten with placeholders. They are read
             // back from the rendered row via data attributes.
             const $row = $(this).closest('tr');
             const payload = {
                 action: 'edit_plan',
                 id: id,
                 title: title,
                 min_amount: parseFloat($row.data('min')),
                 max_amount: parseFloat($row.data('max')),
                 roi_percent: parseFloat($row.data('roi')),
                 cadence: $row.data('cadence'),
                 duration: parseInt($row.data('duration'), 10),
                 risk: $row.data('risk'),
                 status: newStatus
             };

             window.showToast(`Updating plan status...`, 'info', 5000);
             
             const res = await fetchApi('/api/admin/plans.php', payload, "POST");

             if (res.status === 'success') {
                 window.showToast(res.message, 'success');
                 loadFundsDashboard(); 
             } else {
                 window.showToast(res.message || `Status update failed.`, 'error');
             }
         });


        // 5. Plan Form Submission (Add/Edit Plan)
        $('#plan-form').on('submit', async function(e) {
            e.preventDefault();
            
            const id = $('#plan-id').val();
            const isEdit = !!id;
            const action = isEdit ? 'edit_plan' : 'add_plan';
            
            const payload = {
                action: action,
                id: id,
                title: $('#plan-name').val(),
                min_amount: parseFloat($('#plan-min').val()),
                max_amount: parseFloat($('#plan-max').val()),
                roi_percent: parseFloat($('#plan-roi').val()),
                cadence: $('#plan-cadence').val(),
                duration: parseInt($('#plan-duration').val()),
                risk: $('#plan-risk').val(),
                status: $('#plan-status').val()
            };

            window.showToast(`${isEdit ? 'Updating' : 'Creating'} plan...`, 'info', 5000);
            
            const res = await fetchApi('/api/admin/plans.php', payload, "POST");

            if (res.status === 'success') {
                window.showToast(res.message, 'success');
                window.closeModal('#plan-modal');
                loadFundsDashboard(); 
            } else {
                window.showToast(res.message || `${isEdit ? 'Update' : 'Creation'} failed.`, 'error');
            }
        });

        // 6. Position actions.
        //
        // One button per intent. The API derives the schedule columns for each,
        // so no combination an admin can produce here corrupts the payout
        // schedule - which is why the raw fields are gone.

        $(document).on('click', '#inv-save-rate', function () {
            const roi = parseFloat($('#inv-roi').val());
            if (isNaN(roi) || roi < 0 || roi > 999.99) {
                return window.showToast('Enter a rate between 0 and 999.99.', 'error');
            }
            runInvestmentAction({ action: 'edit_investment', roi_percent: roi });
        });

        $(document).on('click', '#inv-add-bonus', function () {
            const amount = parseFloat($('#inv-bonus').val());
            if (isNaN(amount) || amount <= 0) {
                return window.showToast('Enter a bonus amount greater than zero.', 'error');
            }
            runInvestmentAction({ action: 'investment_bonus', amount: amount });
        });

        $(document).on('click', '#inv-save-term', function () {
            const total = parseInt($('#inv-payouts-total').val(), 10);
            if (isNaN(total) || total < 1) {
                return window.showToast('Enter a payout count of at least 1.', 'error');
            }
            runInvestmentAction({ action: 'investment_term', payouts_total: total });
        });

        // Both close actions move money, so they route through the shared
        // confirm dialog rather than firing on a single click.
        $(document).on('click', '#inv-settle', function () {
            stageInvestmentClose('settle');
        });

        $(document).on('click', '#inv-cancel', function () {
            stageInvestmentClose('cancel');
        });

        // 7. General search handling (assuming it searches active investments)
        // Since there is no dedicated search bar in the provided HTML, 
        // this is commented out, but ready for implementation if a search input is added.
        /*
        $('#general-search-form').on('submit', function(e) {
            e.preventDefault();
            currentSearchTerm = $('#search-input').val().trim();
            currentActivePage = 1;
            loadFundsDashboard();
        });
        */

    }

    // --- Initialization ---
    $(function () {
        bindInteractions();
        loadFundsDashboard(); 

        // Expose refresh function globally if needed
        window.refreshFundsDashboard = loadFundsDashboard;
    });

})(jQuery);