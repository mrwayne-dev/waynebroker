/**
 * ============================================================
 *  Maveren Capital - Transaction.js
 *  Handles loading, searching, filtering, pagination & export
 * ============================================================
 */

$(document).ready(function () {
  const listEl = $('#transactionList');
  const searchInput = $('.form-search input');
  // Scoped to the FILTER menu, not every .dropdown-menu on the page.
  //
  // The topbar's user menu is also a .dropdown-menu, so the unscoped selector
  // bound Profile, Transactions and Log out as well - and the handler calls
  // preventDefault(), which meant those links did nothing on this page except
  // filter the table by "profile" or "log out". Logging out was impossible
  // from /dashboard.transactions.
  const filterMenu = $('[data-txn-filter] a');
  const exportBtn = $('.tf-button.style-2');
  const paginationEl = $('#pagination');
  let currentStatus = 'all';
  let currentPage = 1;
  let limit = 10;

  async function loadTransactions(page = 1, status = currentStatus, search = '') {
    listEl.html('<tr><td colspan="5" class="text-center text-muted p-3">Loading transactions...</td></tr>');
    try {
      // Predates fetchApi() and never adopted it, so the CSRF header has to be
      // added here too. credentials:'include' was also missing - it happened to
      // work because this is same-origin, but it is not optional now that the
      // server rejects an unauthenticated POST.
      const res = await fetch('/api/backend/transactions.php', {
        method: 'POST',
        headers: mvcWithCsrf({ 'Content-Type': 'application/json' }),
        credentials: 'include',
        body: JSON.stringify({ page, limit, status, search })
      }).then(r => r.json());

      if (res.status !== 'success') throw new Error(res.message);

      const data = res.data.transactions;
      const pagination = res.data.pagination;

      // Shared with the wallet page and the dashboard overview. Exported by
      // dashboard.js, which is loaded (deferred) immediately before this file.
      // The local copy this replaced printed the amount raw - no currency
      // formatting - so $1200.5 showed here and $1,200.50 everywhere else.
      window.mvcRenderTransactionRows(data, listEl, {
        emptyText: 'No transactions found.',
      });

      if (!data.length) {
        paginationEl.empty();
        return;
      }

      renderPagination(pagination);
    } catch (err) {
      console.error('Error loading transactions:', err);
      listEl.html('<tr><td colspan="5" class="text-center text-danger p-3">Failed to load transactions.</td></tr>');
      paginationEl.empty();
    }
  }

  // Shared with the four admin tables. The local renderer this replaced
  // emitted .page-btn, had no Previous/Next and no window, so 40 pages meant
  // 40 buttons in a row.
  function renderPagination({ page, pages }) {
    window.mvcRenderPagination('#pagination', {
      page: page,
      pages: pages,
      onPage: function (n) {
        currentPage = n;
        loadTransactions(n, currentStatus, searchInput.val().trim());
      },
    });
  }

  // Search form
  $('.form-search').on('submit', function (e) {
    e.preventDefault();
    loadTransactions(1, currentStatus, searchInput.val().trim());
  });

  // Filter dropdown
  filterMenu.on('click', function (e) {
    e.preventDefault();
    currentStatus = $(this).text().toLowerCase() || 'all';
    loadTransactions(1, currentStatus, searchInput.val().trim());
  });

  // Export to CSV
  exportBtn.on('click', function (e) {
    e.preventDefault();
    window.location.href = '/api/backend/transactions.php?export=true';
  });

  // Initial load
  loadTransactions();
});
