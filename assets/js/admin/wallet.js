/**
 * ============================================================
 * Maveren Capital Admin Wallet.js
 * Purpose: Frontend logic for the Admin Wallet Management page.
 * Handles: Data fetching, metric rendering, table rendering, pagination, search/filter, and balance updates.
 * ============================================================
 */
;(function ($) {

    // Fix Bootstrap dropdown blocking buttons
$(document).on('click', '.dropdown-menu .dropdown-item', function (e) {
    e.preventDefault();
    e.stopPropagation();
});

    "use strict";

    // Global state
    let currentPage = 1;
    let currentFilter = 'all'; 
    let currentSearch = '';
    const itemsPerPage = 10; 

    // --- Core Data Fetcher & UI Renderer ---
    async function loadWallets(page = 1, filter = 'all', search = '') {
        const tableBody = $('#wallets-table-body');
        const paginationEl = $('#pagination');
        
        // Update state
        currentPage = page;
        currentFilter = filter;
        currentSearch = search;
        
        // Show loader
        tableBody.empty().html('<tr><td class="mvc-empty" colspan="5">Loading wallet data...</td></tr>');
        paginationEl.empty();
        
        try {
            // Assumes fetchApi is a global utility
            const res = await fetchApi('/api/admin/wallets.php', {
                page: page,
                filter: filter,
                search: search,
                per_page: itemsPerPage 
            }, "GET");

            if (res.status !== 'success') {
                window.showToast(res.message || 'Failed to load wallet list.', 'error');
                tableBody.html('<tr><td class="mvc-empty" colspan="5">Error loading data.</td></tr>');
                return;
            }

            const data = res.data;
            updateMetrics(data.metrics);
            renderWalletsTable(data.wallets);
            renderPagination(data.current_page, data.total_pages);

        } catch (error) {
            console.error('API Error loading wallets:', error);
            window.showToast('A network error occurred while fetching wallet data.', 'error');
            tableBody.html('<tr><td class="mvc-empty" colspan="5">Network error. Check console.</td></tr>');
        }
    }

    // --- Metric Update ---
    function updateMetrics(m) {
        if (!m) return;
        
        // Assumes formatCurrency is a global utility from admin.js
        const formatCurrency = window.formatCurrency || ((amount) => Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        $('#total-wallets').text(m.total_wallets ?? 0);
        $('#total-balance').text(formatCurrency(m.total_balance ?? 0));
        
        // These are counts, not currency
        $('#pending-deposits').text(m.pending_deposits_count ?? 0);
        $('#pending-withdrawals').text(m.pending_withdrawals_count ?? 0);

        // Rerun counter animation
        if (typeof counter === 'function') {
            counter();
        }
    }

    // --- Table Renderer ---
    function renderWalletsTable(wallets) {
        const tableBody = $('#wallets-table-body');
        tableBody.empty();

        if (!wallets || wallets.length === 0) {
            tableBody.html('<tr><td class="mvc-empty" colspan="5">No wallets found matching current criteria.</td></tr>');
            return;
        }
        
        // Assumes formatCurrency is a global utility
        const formatCurrency = window.formatCurrency || ((amount) => Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        const esc = window.mvcEsc;

        wallets.forEach(wallet => {
            // A zero balance is not an error, so it no longer renders red.
            const balanceClass = Number(wallet.balance) > 0 ? 'is-in' : '';

            const depCount = Number(wallet.pending_deposits_count || 0);
            const wdCount  = Number(wallet.pending_withdrawals_count || 0);

            /* Pending column: chips only when there IS something pending, so the
               column reads as an exception list rather than a wall of zeros.
               Counts come from a LEFT JOIN over transactions in the API - the
               stored wallets.pending_withdrawals figure drifts and is not used. */
            const pendingChips = [];
            if (depCount) {
                pendingChips.push(`<div class="box-status bg-Orange"><span class="font-poppins">${depCount} DEP</span></div>`);
            }
            if (wdCount) {
                pendingChips.push(`<div class="box-status bg-Primary"><span class="font-poppins">${wdCount} WDL</span></div>`);
            }
            const pendingCell = pendingChips.length
                ? pendingChips.join(' ')
                : '<span class="text-Gray f12-regular">&mdash;</span>';

            /* Inline buttons rather than a Bootstrap dropdown: the .mvc-table
               wrapper is `overflow-x: auto`, which clips an absolutely
               positioned .dropdown-menu.

               The two queue buttons open the SAME dialogs the dashboard uses
               (pages/admin/_partials/pending-modals.php), scoped to this member.
               They are only rendered when that member actually has something
               pending - an empty queue is not worth a click. */
            const actions = `
                <button type="button" class="tf-button f12-bold action-edit-balance"
                    data-wallet-id="${wallet.wallet_id}"
                    data-user-name="${esc(wallet.user_name)} (ID: ${wallet.user_id})"
                    data-current-balance="${wallet.balance}">Edit balance</button>
                ${depCount ? `<button type="button" class="tf-button f12-bold bg-Accent text-Black action-user-deposits"
                    data-user-id="${wallet.user_id}"
                    data-user-name="${esc(wallet.user_name)}">Deposits (${depCount})</button>` : ''}
                ${wdCount ? `<button type="button" class="tf-button f12-bold bg-Accent text-Black action-user-withdrawals"
                    data-user-id="${wallet.user_id}"
                    data-user-name="${esc(wallet.user_name)}">Withdrawals (${wdCount})</button>` : ''}
            `;

            const row = `
                <tr data-wallet-id="${wallet.wallet_id}" data-user-id="${wallet.user_id}">
                    <td>
                        <a href="/admin.users?search=${encodeURIComponent(wallet.user_id)}">${esc(wallet.user_name)}</a>
                        <div class="f12-regular text-Gray">${esc(wallet.user_email)}</div>
                    </td>
                    <td class="mvc-td-muted">${esc(wallet.wallet_id)}</td>
                    <td class="mvc-td-amount ${balanceClass}">$${formatCurrency(wallet.balance)}</td>
                    <td>${pendingCell}</td>
                    <td>${actions}</td>
                </tr>
            `;
            tableBody.append(row);
        });
    }

    // --- Pagination Renderer ---
    /**
     * Shared renderer (assets/js/mvc-pagination.js). This was one of three
     * byte-identical copies emitting `.page-link`, a Bootstrap class with no
     * matching rule in either stylesheet, plus a `disabled` class that had no
     * CSS and never set the attribute.
     */
    function renderPagination(currentPage, totalPages) {
        window.mvcRenderPagination('#pagination', {
            page: currentPage,
            pages: totalPages,
            onPage: function (n) {
                loadWallets(n, currentFilter, currentSearch);
            },
        });
    }

    // --- Search, Filter, and Export Handlers ---
    function bindInteractions() {
        
        // Ensure dropdown links inside actions-dropdown don't block parent clicks
        $(document).on('click', '.actions-dropdown .dropdown-item', function(e) {
            e.stopPropagation();
        });

        // 1. Search form submission
        $('.form-search').on('submit', function(e) {
            e.preventDefault();
            const searchVal = $('#wallet-search').val().trim();
            loadWallets(1, currentFilter, searchVal);
        });

        // 2. Filter dropdown click
        $('.dropdown-menu a[data-filter]').on('click', function(e) {
            e.preventDefault();
            const filterVal = $(this).data('filter');
            
            // Update the button text to show current filter
            $(this).closest('.dropdown').find('button').html(`<i class="ph ph-funnel"></i> ${$(this).text()}`);
            
            loadWallets(1, filterVal, currentSearch);
        });
        
        // 3. Export to CSV 
        $('#export-csv').on('click', function (e) {
            e.preventDefault();
            let exportUrl = `/api/admin/wallets.php?export=true&filter=${currentFilter}&search=${currentSearch}`;
            window.location.href = exportUrl;
            window.showToast('Preparing and downloading CSV report...', 'info', 5000);
        });

        // 4. Edit Balance Modal Trigger
        $(document).on('click', '.action-edit-balance', function(e) {
            e.preventDefault();
            e.stopPropagation(); // 🔥 This was missing!

            const walletId = $(this).data('wallet-id');
            const userName = $(this).data('user-name');
            const currentBalance = $(this).data('current-balance');

            $('#edit-wallet-id').val(walletId);
            // Member and current balance are now stated text, not disabled
            // inputs, so they are written with .text() rather than .val().
            $('#edit-wallet-user').text(userName);
            $('#edit-current-balance').text(`$${formatCurrency(currentBalance)}`);
            $('#edit-new-balance').val(currentBalance);

            window.showModal('#edit-balance-modal');
            // showModal focuses the first input, which is the hidden wallet id.
            window.setTimeout(function () {
                $('#edit-new-balance').trigger('focus').trigger('select');
            }, 60);
        });


        // 5. Edit Balance Form Submission
        $('#edit-balance-form').on('submit', async function(e) {
            e.preventDefault();

            const walletId = $('#edit-wallet-id').val();
            const newBalance = $('#edit-new-balance').val();
            
            if (isNaN(newBalance) || Number(newBalance) < 0) {
                window.showToast('Please enter a valid non-negative number for the balance.', 'error');
                return;
            }

            window.showToast(`Updating balance for Wallet ID ${walletId}...`, 'info', 5000);

            try {
                const res = await fetchApi('/api/admin/wallets.php', {
                    action: 'update_balance',
                    wallet_id: walletId,
                    new_balance: newBalance
                }, "POST");

                if (res.status === 'success') {
                    window.showToast(res.message, 'success');
                    window.closeModal('#edit-balance-modal');
                    // Refresh current list view
                    await loadWallets(currentPage, currentFilter, currentSearch); 
                } else {
                    window.showToast(res.message || 'Balance update failed.', 'error');
                }
            } catch (error) {
                console.error('Update balance error:', error);
                window.showToast('A network error occurred or the server failed to respond.', 'error');
            }
        });
        
        // 6. Per-user pending queues.
        //
        // These used to bounce the admin to /admin.dashboard because the modals
        // existed only there. The markup is a shared partial now, so the same
        // dialogs open here scoped to one member.
        //
        // The refresh callback is the point: admin.js's processDepositAction /
        // processWithdrawalAction called loadAdminDashboardData() unconditionally,
        // which means nothing on this page. They take a callback now, so the
        // wallets table is what refreshes after an approval.
        const refreshWallets = () => loadWallets(currentPage, currentFilter, currentSearch);

        $(document).on('click', '.action-user-deposits', function (e) {
            e.preventDefault();
            e.stopPropagation();
            window.mvcOpenPendingDeposits({
                userId: $(this).data('user-id'),
                userName: $(this).data('user-name'),
                onChange: refreshWallets,
            });
        });

        $(document).on('click', '.action-user-withdrawals', function (e) {
            e.preventDefault();
            e.stopPropagation();
            window.mvcOpenPendingWithdrawals({
                userId: $(this).data('user-id'),
                userName: $(this).data('user-name'),
                onChange: refreshWallets,
            });
        });

        // The metric cards are global figures, so they still open the global queue.
        $('#pending-deposits').closest('.wallet-card').on('click', function () {
            window.mvcOpenPendingDeposits({ onChange: refreshWallets });
        });

        $('#pending-withdrawals').closest('.wallet-card').on('click', function () {
            window.mvcOpenPendingWithdrawals({ onChange: refreshWallets });
        });
    }

    // --- Initialization ---
    $(function () {
        bindInteractions();
        // Initial load of the wallet list
        loadWallets(1); 
    });

})(jQuery);