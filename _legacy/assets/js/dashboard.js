/**
 * ============================================================
 *  Maveren Capital Dashboard.js (updated: fixes pending modal, toast, data loading, bank suggestions, polish items, icons)
 * ============================================================
 */
;(function ($) {
  "use strict";
  /* ===================== Core UI Behaviors (existing) ===================== */
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
  var select_colors_theme = function () {
    if ($('div').hasClass("select-colors-theme")) {
      $(".select-colors-theme .item").on("click", function () {
        $(this).parents(".select-colors-theme").find(".active").removeClass("active");
        $(this).toggleClass("active");
      });
    }
  };
  var icon_function = function () {
    if ($('div').hasClass("list-icon-function")) {
      $(".list-icon-function .trash").on("click", function () {
        $(this).parents(".item-row").remove();
      });
    }
  };
  var box_search=function(){
    $(document).on('click',function(e){
      var clickID=e.target.id;
      if((clickID!=='s')){
          $('.box-content-search').removeClass('active');
      }});
    $(document).on('click',function(e){
        var clickID=e.target.class;
        if((clickID!=='a111')){
            $('.show-search').removeClass('active');
        }});
    $('.show-search').on('click',function(event){
      event.stopPropagation();}
    );
    $('.search-form').on('click',function(event){
      event.stopPropagation();}
    );
    var input =  $('.header-dashboard').find('.form-search').find('input');
    input.on('input', function() {
      if ($(this).val().trim() !== '') {
        $('.box-content-search').addClass('active');
      } else {
        $('.box-content-search').removeClass('active');
      }
    });
  };
  var preloader = function () {
    setTimeout(function () {
      $("#preload").fadeOut("slow", function () {
          $(this).remove();
      });
    }, 300);
  };
  var variant_picker = function () {
    if ($(".variant-picker-item").length) {
      $(".variant-picker-item label").on("click", function () {
        $(this)
          .closest(".variant-picker-item")
          .find(".variant-picker-label-value")
          .text($(this).data("value"));
      });
    }
  };
  var fullcheckbox = function () {
    $('.total-checkbox').on('click', function () {
      if ( $(this).is(':checked') ) {
        $(this).closest('.wrap-checkbox').find('.tf-table-item').addClass("checked");
        $(this).closest('.wrap-checkbox').find('.checkbox-item').prop('checked', true);
      } else {
        $(this).closest('.wrap-checkbox').find('.tf-table-item').removeClass("checked");
        $(this).closest('.wrap-checkbox').find('.checkbox-item').prop('checked', false);
      }
    });
    $('.tf-table-item .checkbox-item').on('click', function () {
      $(this).closest('.tf-table-item').toggleClass("checked");
    });
  };
  var counter = function () {
    if (!$(document.body).hasClass("counter-scroll")) return;
    var $counter = $(".counter");
    if ($counter.length === 0) return;
    var a = 0;
    var oTop = $counter.offset().top - window.innerHeight;
    if (a === 0 && oTop < 500) {
      if ($().countTo) {
        $counter.find(".number").each(function () {
          var to = $(this).data("to"), speed = $(this).data("speed");
          $(this).countTo({ to: to, speed: speed });
        });
      }
      a = 1;
    }
    $(window).scroll(function () {
      if (a === 0 && $(window).scrollTop() > oTop) {
        if ($().countTo) {
          $counter.find(".number").each(function () {
            var to = $(this).data("to"), speed = $(this).data("speed");
            $(this).countTo({ to: to, speed: speed });
          });
        }
        a = 1;
      }
    });
  };
  var sort = function () {
    $(".title-sort .btn-key-sort").click(function() {
      let columnIndex = $(this).data("index");
      let type = $(this).data("type");
      let table = $(".content-sort tbody");
      let rows = table.find("tr").toArray();
      let isAscending = $(this).data("order") === "asc";
      rows.sort((rowA, rowB) => {
        let cellA = $(rowA).children("td").find(".key-sort").eq(columnIndex).text().trim();
        let cellB = $(rowB).children("td").find(".key-sort").eq(columnIndex).text().trim();
        if (type === "number") {
          return isAscending ? (Number(cellA) - Number(cellB)) : (Number(cellB) - Number(cellA));
        } else {
          return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        }
      });
      table.append(rows);
      $(this).data("order", isAscending ? "desc" : "asc");
    });
  };

  
  /* ===================== Utilities (new) ===================== */
  // Modal open/close. Kept behaviourally identical to the admin copy in
  // assets/js/admin/admin.js: both close hooks are bound (.button-close-modal
  // is the admin markup dialect, [data-modal-close] the member one) and body
  // scroll is locked while open, which matters most for the phone sheet.
  function showModal(selector) {
    const modal = $(selector);
    if (!modal.length) return;
    modal.attr('aria-hidden', 'false').addClass('open').addClass('is-open');
    modal.find('[data-modal-close], .button-close-modal, .modal-overlay')
         .off('click.mvcModal').on('click.mvcModal', () => closeModal(selector));
    // Accessibility: Focus the first focusable element in the modal
    setTimeout(() => {
        modal.find('input, button, select, textarea').first().focus();
    }, 10); // Small delay to allow modal to be visually ready
    $('body').css('overflow', 'hidden');
  }
  // Close modal - with fade-out sync delay
  function closeModal(selector) {
    const modal = $(selector);
    if (!modal.length) return;
    modal.attr('aria-hidden', 'true');
    // Delay slightly longer than the CSS exit animation.
    setTimeout(() => {
        modal.removeClass('open').removeClass('is-open');
    }, 300);
    // Only release the scroll lock once nothing else is open.
    if (!$('.modal.is-open').not(modal).length) {
      $('body').css('overflow', '');
    }
  }
  // Copy input value to clipboard and show toast
  function copyToClipboard(selector) {
    const el = $(selector);
    if (!el.length) return;
    const value = el.val() || el.text();
    if (!value) {
      showToast('Nothing to copy', 'error');
      return;
    }
    navigator.clipboard?.writeText(value).then(() => {
      showToast('Copied to clipboard', 'success');
    }).catch(() => {
      // fallback
      const tmp = document.createElement('textarea');
      tmp.value = value;
      document.body.appendChild(tmp);
      tmp.select();
      try {
        document.execCommand('copy');
        showToast('Copied to clipboard', 'success');
      } catch (e) {
        showToast('Could not copy', 'error');
      }
      tmp.remove();
    });
  }
  // Format to currency (USD default)
  function formatCurrency(amount) {
    if (amount == null || isNaN(Number(amount))) return '0.00';
    return Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  // Mirrors formatPaymentMethod() in api/utilities/helpers.php so a slug reads
  // the same whether the label is built server-side or here.
  function formatMethodLabel(method) {
    const slug = String(method || '').trim().toLowerCase();
    const labels = {
      secure_exchange: 'Crypto Checkout',
      // Manual transfer to an address we publish. Not 'wallet_address',
      // which is the member's own payout address on a withdrawal.
      deposit_address: 'Deposit Address',
      local_bank: 'Local Bank',
      wallet_address: 'Wallet Address',
      wallet: 'Wallet',
      system: 'System',
      wire_transfer: 'Wire Transfer',
      cash_mailing: 'Cash Mailing',
    };
    if (labels[slug]) return labels[slug];
    if (!slug) return 'N/A';
    // Global regex, not a string pattern: String.replace('_', ' ') swaps only
    // the first underscore, which left "wallet address_extra" style leftovers.
    return slug.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }
  /* ===================== Shared transaction table ======================
   * There used to be THREE renderers for one dataset: the wallet <ul>, the
   * dashboard <tr> and the transactions page <tr>. They disagreed on the
   * field name (tx.date vs tx.created_at), on what drove the colour (type in
   * two of them, status in the third) and on whether the amount was passed
   * through formatCurrency at all - so the same row read differently
   * depending on which page you were looking at.
   *
   * One renderer, colouring by status, always formatting currency.
   * ==================================================================== */
  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function statusBadgeClass(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'completed' || s === 'success') return 'bg-Green';
    if (s === 'pending' || s === 'processing') return 'bg-Orange';
    return 'bg-Salmon';
  }

  /**
   * @param {Array}  rows
   * @param {jQuery} $tbody
   * @param {{columns?: string[], emptyText?: string}} [opts]
   *        columns defaults to the full five-column transactions layout.
   */
  /* ====================================================================
   * ANNOUNCEMENTS
   * The "Latest updates" panel on the dashboard used to be three blocks of
   * hardcoded copy. It looked like a live feed and never changed, while the
   * announcements an admin published sat in a table the member side never
   * read. This renders the published ones returned by dashboard.php get_data.
   *
   * Titles and bodies are admin-authored free text going into innerHTML, so
   * every field is escaped on the way in.
   * ==================================================================== */
  function announcementIcon(category) {
    switch (String(category || '').toLowerCase()) {
      case 'maintenance': return 'ph-wrench';
      case 'security':    return 'ph-shield-check';
      case 'payout':      return 'ph-calendar-check';
      case 'plans':       return 'ph-calendar-dots';
      case 'alert':       return 'ph-warning-circle';
      default:            return 'ph-megaphone';
    }
  }

  function mvcRenderAnnouncements(items, $box) {
    if (!$box || !$box.length) return;

    if (!Array.isArray(items) || items.length === 0) {
      $box.html(
        '<div class="mvc-notice mvc-notice--empty">' +
          '<span class="mvc-notice__icon"><i class="ph ph-megaphone" aria-hidden="true"></i></span>' +
          '<div><div class="mvc-notice__body">No updates right now. ' +
          'Anything the team publishes will appear here.</div></div>' +
        '</div>'
      );
      return;
    }

    $box.html(items.map(function (a) {
      return '' +
        '<div class="mvc-notice">' +
          '<span class="mvc-notice__icon"><i class="ph ' + escapeHtml(announcementIcon(a.category)) + '" aria-hidden="true"></i></span>' +
          '<div>' +
            '<div class="mvc-notice__title">' + escapeHtml(a.title) + '</div>' +
            '<div class="mvc-notice__body">' + escapeHtml(a.body) + '</div>' +
            '<div class="mvc-notice__meta">' + escapeHtml(a.date) + '</div>' +
          '</div>' +
        '</div>';
    }).join(''));
  }

  function mvcRenderTransactionRows(rows, $tbody, opts) {
    if (!$tbody || !$tbody.length) return;

    const options = opts || {};
    const columns = options.columns || ['reference', 'date', 'type', 'amount', 'status'];
    const emptyText = options.emptyText || 'No transactions found.';

    $tbody.empty();

    if (!rows || !rows.length) {
      $tbody.append(
        `<tr><td class="mvc-empty" colspan="${columns.length}">${escapeHtml(emptyText)}</td></tr>`
      );
      return;
    }

    rows.forEach(function (tx) {
      // The two endpoints spell the timestamp differently; accept both rather
      // than making one of them lie about its own shape.
      const date = tx.date || tx.created_at || '';
      const cells = columns.map(function (col) {
        switch (col) {
          case 'reference':
            return `<td class="mvc-td-muted">#${escapeHtml(tx.reference || '')}</td>`;
          case 'date':
            return `<td class="mvc-td-muted">${escapeHtml(date)}</td>`;
          case 'type':
            return `<td>${escapeHtml(tx.type || '')}</td>`;
          case 'amount':
            return `<td class="mvc-td-amount ${amountDirection(tx.type)}">$${formatCurrency(tx.amount)}</td>`;
          case 'status':
            return `<td><div class="box-status ${statusBadgeClass(tx.status)}">` +
                   `<span class="font-poppins">${escapeHtml(String(tx.status || '').toUpperCase())}</span></div></td>`;
          default:
            return '<td></td>';
        }
      });
      // A pending deposit row is a second, free entry point back to its
      // transfer instructions - no extra chrome anywhere on the page.
      const pending = String(tx.status || '').toLowerCase() === 'pending'
        && String(tx.type || '').toLowerCase().includes('deposit')
        && tx.reference;
      const rowAttrs = pending
        ? ` class="is-actionable" data-pending-ref="${escapeHtml(tx.reference)}" title="View transfer instructions"`
        : '';
      $tbody.append(`<tr${rowAttrs}>${cells.join('')}</tr>`);
    });
  }

  // Money leaving the account reads orange, money arriving reads green.
  // Matches against the HUMAN label the API sends (formatTransactionType).
  function amountDirection(type) {
    const t = String(type || '').toLowerCase();
    // "Principal Released" is capital coming back, so it must not ride the
    // 'investment' branch the way its old investment_release slug did.
    if (t.includes('withdraw')) return 'is-out';
    if (t.includes('investment') && !t.includes('principal')) return 'is-out';
    return 'is-in';
  }

  /* ===================== Icon Helper for Activity ===================== */
  // NOTE: matches run against the HUMAN label the API now sends
  // (formatTransactionType in api/utilities/helpers.php), not the raw slug.
  // "Principal Released" is why 'principal'/'release' are matched explicitly:
  // the old slug was investment_release and rode the 'investment' branch.
  function getIconForType(type) {
    const t = (type || '').toLowerCase();
    if (t.includes('deposit')) return 'ph-arrow-circle-down';
    if (t.includes('withdraw')) return 'ph-arrow-circle-up';
    if (t.includes('principal') || t.includes('release')) return 'ph-arrow-u-left-up';
    if (t.includes('investment')) return 'ph-chart-line';
    if (t.includes('roi') || t.includes('payout')) return 'ph-trend-up';
    if (t.includes('infrastructure')) return 'ph-buildings';
    if (t.includes('maintenance')) return 'ph-wrench';
    return 'ph-wallet';
  }
  // Determine color class for transaction amounts
function getAmountClass(type) {
  const t = (type || '').toLowerCase();
  if (t.includes('withdraw')) return 'negative';
  return 'positive'; // deposit, roi_payout, investment_release, etc.
}

  /* ===================== Toast helper (updated to match new CSS + prevent stacking) ===================== */
  // Basic toast implementation matching the new CSS styles
  function showToast(message, type = 'info', timeout = 4000) { // Changed default timeout to match CSS animation (4s)
    const container = $('#toast-container');
    if (!container.length) {
      console.error('Toast container #toast-container not found in the DOM.');
      return;
    }

    // Prevent toast stacking beyond 3
    if (container.children().length > 3) {
        container.children().first().remove();
    }

    // Determine the icon based on the type
    let icon = 'ph-info'; // Default icon
    if (type === 'success') icon = 'ph-check-circle';
    if (type === 'error') icon = 'ph-warning-circle';
    if (type === 'warning') icon = 'ph-warning';

    // Create the toast element with the correct classes and structure
    const toastEl = $(`
      <div class="toast toast-${type}">
        <i class="ph ${icon}" style="font-size:22px"></i>
        <div class="toast-message">${message}</div>
      </div>
    `);

    container.append(toastEl);

    // If a timeout is specified, remove the toast after the duration
    // The CSS handles the fadeOut animation via keyframes
    if (timeout > 0) {
      setTimeout(() => {
        toastEl.remove(); // Remove the toast element after the CSS animation completes
      }, timeout);
    }

    return toastEl; // Return the element if needed for further manipulation
  }
  /* ===================== Dashboard Data Loading (updated to handle both dashboard.php and wallet.php + icons) ===================== */
// Platform limits, set by an admin on /admin.settings and delivered with the
// wallet summary. 0 means no limit. The server enforces all of these
// regardless; these copies exist so the forms can state the rule up front
// rather than letting someone fill one in and find out on submit.
var mvcLimits = {
  withdrawal_min: 0, withdrawal_max: 0,
  deposit_min: 0,    deposit_max: 0,
};

var loadDashboardData = async function () {
  try {
    // The wallet page is routed as /dashboard.wallet (dot-separated, see .htaccess),
    // so a '/wallet' substring check never matched - match the trailing page segment.
    const onWalletPage = /wallet\/?$/.test(window.location.pathname.toLowerCase());

    // 🟩 When on the wallet page, fetch summary directly from wallet backend
    if (onWalletPage) {
      const walletRes = await fetchApi('/api/backend/wallet.php', { action: 'get_wallet_summary' });
      if (walletRes.status === 'success') {
        const w = walletRes.data;

        // --- Update Wallet Card Values (IDs from wallet.php) ---
        setSplitAmount('#total-balance', w.balance ?? 0);
        $('#withdraw-available').text(formatCurrency(w.balance ?? 0));
        $('#pending-withdrawals').text(formatCurrency(w.pending_withdrawals ?? 0));
        $('#total-deposited').text(formatCurrency(w.total_deposited ?? 0));
        $('#total-withdrawn').text(formatCurrency(w.total_withdrawn ?? 0));
        $('#total-investments').text(formatCurrency(w.total_investments ?? 0));
        $('#total-earnings').text(formatCurrency(w.total_earnings ?? 0));
        $('#wallet-total-earnings').text(formatCurrency(w.total_earnings ?? 0));

        // --- Allocations by payout cadence (computed live by wallet.php) ---
        const weekly  = Number(w.weekly_invested ?? 0);
        const monthly = Number(w.monthly_invested ?? 0);
        $('#weekly-invested').text(formatCurrency(weekly));
        $('#monthly-invested').text(formatCurrency(monthly));
        const totalInvested = (w.total_invested != null)
          ? Number(w.total_invested)
          : (weekly + monthly);
        $('#wallet-total-invested').text(formatCurrency(totalInvested));
        $('#next-payout-date').text(w.next_payout_date ?? '—');
        $('#next-payout-amount').text(formatCurrency(w.next_payout_amount ?? 0));

        // --- Admin-set transaction limits ---
        mvcLimits.withdrawal_min = Number(w.withdrawal_min ?? 0);
        mvcLimits.withdrawal_max = Number(w.withdrawal_max ?? 0);
        mvcLimits.deposit_min    = Number(w.deposit_min ?? 0);
        mvcLimits.deposit_max    = Number(w.deposit_max ?? 0);

        const $minHint = $('#withdraw-min-hint');
        if ($minHint.length) {
          const parts = [];
          if (mvcLimits.withdrawal_min > 0) parts.push('Minimum ' + formatCurrency(mvcLimits.withdrawal_min));
          if (mvcLimits.withdrawal_max > 0) parts.push('maximum ' + formatCurrency(mvcLimits.withdrawal_max) + ' per request');

          if (parts.length) {
            $minHint.text(parts.join(', ')).prop('hidden', false);
          } else {
            $minHint.text('').prop('hidden', true);
          }
          $('#withdraw-amount').attr('min', mvcLimits.withdrawal_min > 0 ? mvcLimits.withdrawal_min.toFixed(2) : '1');
          if (mvcLimits.withdrawal_max > 0) $('#withdraw-amount').attr('max', mvcLimits.withdrawal_max.toFixed(2));
          else $('#withdraw-amount').removeAttr('max');
        }

        // The deposit form's hint depends on the selected coin as well as the
        // platform floor, so it is rebuilt through the same helper the network
        // picker uses.
        applyDepositLimits();

        // NOTE: do not return early - fall through so the unified loader
        // also populates #wallet-activity (recent_activity from dashboard.php).
      } else {
        console.warn('Wallet summary failed:', walletRes.message);
      }
    }

    // 🟨 Otherwise, fetch from dashboard.php for all other pages
    const res = await fetchApi('/api/backend/dashboard.php', { action: 'get_data' });
    if (res.status === 'success') {
      const w = res.data.wallet || {};

      const parseNum = v => {
        if (v == null) return 0;
        if (typeof v === 'string') {
          const n = Number(v.replace(/[^0-9.\-]/g, ''));
          return isNaN(n) ? 0 : n;
        }
        return Number(v) || 0;
      };

      // --- Update Wallet Card Values (shared structure) ---
      // On the wallet page the dedicated wallet.php summary above is the source
      // of truth (correct $ figures); skip these so we don't clobber it - notably
      // dashboard.php returns pending_withdrawals as a COUNT, not a $ amount.
      // (onWalletPage computed once at the top of this function.)
      if (!onWalletPage) {
        setSplitAmount('#total-balance', parseNum(w.balance ?? 0));
        $('#withdraw-available').text(formatCurrency(parseNum(w.balance ?? 0)));
        $('#pending-withdrawals').text(Math.round(parseNum(w.pending_withdrawals ?? 0)));
        $('#total-deposited').text(formatCurrency(parseNum(w.total_deposited ?? 0)));
        $('#total-withdrawn').text(formatCurrency(parseNum(w.total_withdrawn ?? 0)));
        $('#total-investments').text(formatCurrency(parseNum(w.total_investments ?? w.investments ?? 0)));
        $('#total-earnings').text(formatCurrency(parseNum(w.total_earnings ?? 0)));
      }

      // Wallet activity now uses the shared table renderer and is loaded from
      // the transactions endpoint by loadWalletActivity(), so nothing to do
      // here - see mvcRenderTransactionRows below.

      // --- Dashboard Overview (dashboard.php) ---
      $('#wallet-balance').text(formatCurrency(parseNum(w.balance ?? 0)));
      $('#investments').text(formatCurrency(parseNum(w.investments ?? w.total_investments ?? 0)));

      // --- Live investment position summary (dashboard.php `investments` block) ---
      const inv = res.data.investments || {};
      $('#active-positions').text(parseNum(inv.active_count ?? 0));
      $('#active-capital').text(formatCurrency(parseNum(inv.active_capital ?? 0)));
      $('#roi-earned').text(formatCurrency(parseNum(inv.roi_earned ?? 0)));
      $('#portfolio-value').text(formatCurrency(parseNum(inv.portfolio_value ?? 0)));
      $('#next-payout-date').text(inv.next_payout_date ?? '—');
      $('#next-payout-amount').text(formatCurrency(parseNum(inv.next_payout_amount ?? 0)));


      // --- Recent Activity Table (dashboard.php) ---
      mvcRenderTransactionRows(res.data.recent_activity, $('#recent-activity'), {
        columns: ['date', 'type', 'amount'],
        emptyText: 'No activity yet. Deposits, withdrawals and payouts appear here.',
      });

      // --- Latest updates (admin announcements) ---
      mvcRenderAnnouncements(res.data.announcements, $('#dash-announcements'));

      // --- Pending count badge ---
      const pendingCount = (res.data.pending_count ?? 0);
      $('#pending-count').text(pendingCount ? `(${pendingCount})` : '');
    } else {
      console.error('Failed to load dashboard ', res.message);
      showToast('Failed to load dashboard ' + res.message, 'error');
    }
  } catch (error) {
    console.error('Error loading dashboard ', error);
    showToast('An error occurred while loading dashboard data.', 'error');
  }
};

  // Expose a programmatic refresh if needed elsewhere
  window.refreshDashboard = async function () {
    await loadDashboardData();
  };
  /* ===================== Auto-refresh (new) ===================== */
  // Auto-refresh interval (ms)
  const AUTO_REFRESH_INTERVAL_MS = 90000; 
  let autoRefreshTimer = null;
  function startAutoRefresh() {
    if (autoRefreshTimer) return;
    autoRefreshTimer = setInterval(() => {
      loadDashboardData().catch(err => console.error('Auto-refresh failed', err));
    }, AUTO_REFRESH_INTERVAL_MS);
    // initial immediate refresh as well
    loadDashboardData().catch(err => console.error('Initial refresh failed', err));
  }
  function stopAutoRefresh() {
    if (!autoRefreshTimer) return;
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = null;
  }
  /* ===================== Wallet: Deposit Flow =====================
   * Two routes:
   *   secure_exchange  - hands off to the crypto checkout, which issues its
   *                      own address and redirects.
   *   deposit_address  - manual transfer to an address WE publish. The
   *                      transaction stays pending until an admin confirms.
   * ================================================================ */

  // Filled by loadDepositNetworks(). Cached so the method picker and the
  // network <select> share one fetch rather than making a second request.
  let depositNetworks = [];
  // The pending rows currently on screen, so re-opening a deposit's
  // instructions needs no round trip.
  let pendingDeposits = [];

  function parseTxDetails(row) {
    try { return JSON.parse(row.details || '{}') || {}; } catch (e) { return {}; }
  }

  // One key. The old code probed six (deposit_address || address ||
  // wallet_address || payment_address || created_invoice_url ||
  // details.data.*) and then fell back to a get_deposit_details endpoint
  // that no longer exists. initiate_deposit now writes exactly one shape.
  function depositSnapshotOf(row) {
    const s = parseTxDetails(row).deposit_address;
    return (s && typeof s === 'object') ? s : null;
  }

  async function loadDepositNetworks() {
    // Needed by the deposit modal even though the read-only address card
    // it used to fill has been removed.
    if (!$('#deposit-form').length) return;
    try {
      const res = await fetchApi('/api/backend/wallet.php', { action: 'get_deposit_networks' });
      depositNetworks = (res && res.status === 'success' && res.data.networks) || [];
    } catch (err) {
      console.error('Deposit networks error', err);
      depositNetworks = [];
    }

    const $manual = $('#deposit-method-manual');
    const $select = $('#deposit-network');
    if (!depositNetworks.length) {
      // Nowhere to send: don't offer the route at all.
      $manual.attr('hidden', true);
      return;
    }

    $manual.removeAttr('hidden');
    $select.empty();
    depositNetworks.forEach(function (n) {
      $select.append(
        $('<option>').val(n.id).text(n.label)
          .attr('data-min', n.min_amount)
          .attr('data-confirmations', n.confirmations)
      );
    });
    syncDepositNetworkHint();
  }

  // Mirrors the server-side minimum check so the member sees it before
  // spending a round trip on it.
  function syncDepositNetworkHint() {
    const id = Number($('#deposit-network').val() || 0);
    const n = depositNetworks.find(function (x) { return x.id === id; });
    if (!n) return;

    const bits = [];
    if (n.min_amount > 0) bits.push('Min $' + formatCurrency(n.min_amount));
    if (n.confirmations > 0) bits.push(n.confirmations + ' confirmation' + (n.confirmations === 1 ? '' : 's'));
    $('#deposit-network-hint').text(bits.join(' · '));

    applyDepositLimits(n.min_amount > 0 ? Number(n.min_amount) : 0);
  }

  /**
   * Deposit min/max shown on the form.
   *
   * A coin can carry its own minimum on top of the platform floor, so the
   * STRICTER of the two is what a member actually has to meet - which is also
   * how api/backend/wallet.php enforces it: the global check runs first, then
   * the per-address one.
   *
   * With no argument, reads the minimum off the currently selected network, so
   * the summary loader can refresh the hint without knowing the selection.
   */
  function applyDepositLimits(coinMin) {
    const $hint = $('#deposit-min-hint');
    if (!$hint.length) return;

    if (coinMin == null) {
      const manual = $('#deposit-method').val() === 'deposit_address';
      const raw = manual ? Number($('#deposit-network').find(':selected').data('min') || 0) : 0;
      coinMin = isNaN(raw) ? 0 : raw;
    }

    const floor = Math.max(Number(mvcLimits.deposit_min || 0), Number(coinMin || 0));
    const effectiveMin = floor > 0 ? floor : 1;
    const max = Number(mvcLimits.deposit_max || 0);

    const parts = ['Minimum <strong>$' + formatCurrency(effectiveMin) + '</strong>'];
    if (max > 0) parts.push('maximum <strong>$' + formatCurrency(max) + '</strong>');
    $hint.html(parts.join(', '));

    $('#deposit-amount').attr('min', effectiveMin);
    if (max > 0) $('#deposit-amount').attr('max', max);
    else $('#deposit-amount').removeAttr('max');
  }

  function bindDepositMethodPicker() {
    const $segment = $('#deposit-method-segment');
    if (!$segment.length) return;

    $segment.on('click', '.mvc-segment__btn', function () {
      const method = $(this).data('method');
      $segment.find('.mvc-segment__btn')
        .removeClass('is-active').attr('aria-checked', 'false');
      $(this).addClass('is-active').attr('aria-checked', 'true');
      $('#deposit-method').val(method);

      const manual = method === 'deposit_address';
      $('#deposit-network-field').toggleClass('hidden', !manual).attr('aria-hidden', String(!manual));
      $('#deposit-summary-time').text(manual ? 'Credited after we confirm receipt' : 'Instant for crypto');

      if (manual) syncDepositNetworkHint();
      else {
        // secure_exchange carries no per-coin minimum, but the platform floor
        // still applies - this used to hardcode $1 regardless.
        applyDepositLimits(0);
      }
    });

    $('#deposit-network').on('change', syncDepositNetworkHint);
  }

  function bindDepositForm() {
    const form = $('#deposit-form');
    if (!form.length) return;
    form.on('submit', async function (e) {
      e.preventDefault();
      const amount = Number($('#deposit-amount').val() || 0);
      const method = $('#deposit-method').val();
      if (!amount || amount <= 0) {
        showToast('Enter a valid deposit amount', 'error');
        return;
      }
      if (!method) {
        showToast('Select a payment method', 'error');
        return;
      }

      const payload = { action: 'initiate_deposit', amount, method };
      if (method === 'deposit_address') {
        payload.deposit_address_id = Number($('#deposit-network').val() || 0);
        if (!payload.deposit_address_id) {
          showToast('Choose the coin and network you want to send', 'error');
          return;
        }
      }

      try {
        showToast('Processing deposit...', 'info', 2000);
        const res = await fetchApi('/api/backend/wallet.php', payload);
        if (res.status !== 'success') {
          showToast(res.message || 'Deposit failed', 'error');
          return;
        }

        // secure_exchange: the provider issues the address, so we leave.
        const redirect = res.data?.redirect_url || res.data?.payment_url || res.data?.redirect || null;
        if (redirect) {
          showToast('Redirecting to payment provider...', 'success', 2000);
          setTimeout(() => { window.location.href = redirect; }, 600);
          return;
        }

        closeModal('#deposit-modal');
        $('#deposit-amount').val('');

        if (res.data?.deposit_address) {
          // Rendered straight from the response - no second round trip.
          renderDepositInstructions({
            reference: res.data.reference,
            amount: res.data.amount,
            deposit_address: res.data.deposit_address,
            marked_paid: false,
          });
          showModal('#deposit-instructions-modal');
        } else {
          showToast('Deposit request submitted. Support will provide details shortly.', 'success');
        }

        await loadPendingDeposits();
        await loadDashboardData();
      } catch (err) {
        console.error('Deposit error', err);
        showToast('Failed to initiate deposit', 'error');
      }
    });
  }

  /* ===================== Wallet: Deposit instructions ===================== */

  // The address panel is a receipt, not a form step, so ONE renderer serves
  // both "just created" and "re-opened from the pending card".
  function renderDepositInstructions(d) {
    const s = d.deposit_address || {};

    $('#di-amount').text(formatCurrency(d.amount));
    // Asset ticker only. Appending the raw network slug here printed things
    // like "XLM · other", and the address card directly below already carries
    // the human network name in its label.
    $('#di-network-label').text(s.asset ? 'in ' + s.asset : '');
    $('#di-label').text(s.label || 'Deposit address');

    const meta = [];
    if (s.min_amount > 0) meta.push('Min $' + formatCurrency(s.min_amount));
    $('#di-meta').text(meta.join(' · '));

    // .attr AND .data together: the delegated copy handler reads
    // $(this).data('copy-text'), and jQuery caches .data() on first read -
    // .attr() alone would leave a stale value on the second open.
    setCopyTarget($('#di-address-copy'), s.address || '');
    $('#di-address').text(s.address || '');

    if (s.memo_tag) {
      $('#di-memo-row').removeClass('hidden');
      $('#di-memo-label').text(s.memo_label || 'Memo');
      $('#di-memo').text(s.memo_tag);
      setCopyTarget($('#di-memo-copy'), s.memo_tag);
    } else {
      $('#di-memo-row').addClass('hidden');
    }

    $('#di-instructions').toggleClass('hidden', !s.instructions).text(s.instructions || '');
    $('#di-reference').text(d.reference || '');

    const conf = Number(s.confirmations || 0);
    $('#di-conf-row').toggleClass('hidden', conf <= 0);
    $('#di-conf').text(conf + ' confirmation' + (conf === 1 ? '' : 's'));

    // Reset the hash step every open, or a previous deposit's hash leaks in.
    $('#di-tx-hash').val('');
    $('#di-hash-field').addClass('hidden');

    const $paid = $('#di-confirm-paid').data('reference', d.reference).removeData('armed');
    if (d.marked_paid) {
      $('#di-status').text('Marked as paid — awaiting confirmation');
      $paid.prop('disabled', true).text('Marked as paid');
    } else {
      $('#di-status').text('Awaiting your transfer');
      $paid.prop('disabled', false).text('I have paid');
    }

    $('#deposit-instructions-modal .modal-body').scrollTop(0);
  }

  function setCopyTarget($btn, value) {
    $btn.attr('data-copy-text', value).data('copy-text', value);
  }

  /* ===================== Wallet: Pending deposits ===================== */

  async function loadPendingDeposits() {
    const $box = $('#pending-deposits-box');
    const $list = $('#pending-deposits-list');
    if (!$list.length) return;

    try {
      const res = await fetchApi('/api/backend/wallet.php', { action: 'get_pending_deposits' });
      pendingDeposits = (res.status === 'success' && res.data.deposits) || [];
    } catch (err) {
      console.error('Pending deposits error', err);
      pendingDeposits = [];
    }

    // Only manual transfers give the member something to do. A pending
    // secure_exchange row is the provider's problem, not a to-do item.
    const actionable = pendingDeposits.filter(function (d) { return d.method === 'deposit_address'; });
    if (!actionable.length) { $box.attr('hidden', true); return; }

    $list.empty();
    actionable.forEach(function (d) {
      const s = depositSnapshotOf(d);
      const paid = !!parseTxDetails(d).user_marked_paid;
      $list.append(
        '<li class="mvc-address">' +
          '<div class="mvc-address__head">' +
            '<span class="mvc-address__label">$' + formatCurrency(d.amount) + ' · ' +
              escapeHtml(s ? s.label : 'Deposit') + '</span>' +
            '<span class="mvc-address__meta">' + (paid ? 'Marked as paid' : 'Awaiting transfer') + '</span>' +
          '</div>' +
          '<div class="mvc-address__row">' +
            '<code class="mvc-address__value">' + escapeHtml(d.reference) + '</code>' +
            '<button type="button" class="mvc-address__copy pending-open" data-ref="' +
              escapeHtml(d.reference) + '" aria-label="View deposit instructions">' +
              '<i class="ph ph-arrow-right"></i></button>' +
          '</div>' +
        '</li>'
      );
    });
    $box.removeAttr('hidden');
  }

  function openDepositInstructions(reference) {
    const d = pendingDeposits.find(function (x) { return x.reference === reference; });
    const s = d && depositSnapshotOf(d);
    if (!s) {
      showToast('This deposit has no transfer instructions. Contact support with the reference.', 'error');
      return;
    }
    renderDepositInstructions({
      reference: d.reference,
      amount: d.amount,
      deposit_address: s,
      marked_paid: !!parseTxDetails(d).user_marked_paid,
    });
    showModal('#deposit-instructions-modal');
  }

  function bindPendingDeposits() {
    $(document).on('click', '.pending-open', function () {
      openDepositInstructions($(this).data('ref'));
    });
    // Pending deposit rows in the Wallet Activity table are a second, free
    // entry point - no extra chrome on the page.
    $(document).on('click', '[data-pending-ref]', function () {
      openDepositInstructions($(this).data('pending-ref'));
    });
  }

  // "I have paid" is two-stage: the first press reveals the optional hash
  // field, the second submits. One button, no extra dialog.
  function bindPendingConfirmPaid() {
    $('#di-confirm-paid').on('click', async function () {
      const $btn = $(this);
      const ref = $btn.data('reference');
      if (!ref) {
        showToast('No deposit selected', 'error');
        return;
      }

      if (!$btn.data('armed')) {
        $btn.data('armed', true).text('Confirm payment');
        $('#di-hash-field').removeClass('hidden');
        $('#di-tx-hash').trigger('focus');
        return;
      }

      const txHash = ($('#di-tx-hash').val() || '').trim();
      try {
        showToast('Marking deposit as paid...', 'info');
        const res = await fetchApi('/api/backend/wallet.php', {
          action: 'confirm_deposit_payment', reference: ref, tx_hash: txHash,
        });

        if (res.status === 'success') {
          showToast(res.message || 'Marked as paid. Awaiting verification.', 'success');
          closeModal('#deposit-instructions-modal');
          await loadPendingDeposits();
          await loadDashboardData();
        } else {
          showToast(res.message || 'Failed to mark deposit paid', 'error');
        }
      } catch (err) {
        console.error('Confirm deposit error', err);
        showToast('Failed to mark deposit paid', 'error');
      }
    });
  }

  /* ===================== Wallet: Withdraw Flow (bankSuggestions integrated + fixes + polish) ===================== */
  // Bank suggestions map (from user-provided data)
  const bankSuggestions = { 
    'United States of America': ['Chase Bank', 'Bank of America', 'Wells Fargo', 'Citibank', 'U.S. Bank', 'PNC Bank', 'Capital One', 'TD Bank', 'Truist', 'Fifth Third Bank', 'Regions Bank', 'KeyBank', 'Huntington Bank', 'Ally Bank', 'Discover Bank'],
    'Germany': ['Deutsche Bank', 'Commerzbank', 'Sparkasse', 'HypoVereinsbank', 'Postbank', 'Volksbank', 'DZ Bank', 'Bayerische Landesbank', 'Landesbank Baden-Württemberg', 'Norddeutsche Landesbank', 'Helaba', 'KfW Bank', 'DekaBank', 'IKB Deutsche Industriebank', 'Württembergische Bank'],
    'France': ['BNP Paribas', 'Société Générale', 'Crédit Agricole', 'Banque Populaire', 'Caisse d\'Épargne', 'Crédit Mutuel', 'La Banque Postale', 'BPCE', 'Crédit Coopératif', 'Banque Tarneaud', 'Banque Palatine', 'CIC', 'LCL', 'HSBC France', 'Rothschild & Co'],
    'United Kingdom': ['HSBC', 'Barclays', 'Lloyds Bank', 'NatWest', 'Santander UK', 'Royal Bank of Scotland', 'Metro Bank', 'Starling Bank', 'Monzo', 'Revolut', 'Clydesdale Bank', 'TSB Bank', 'Co-operative Bank', 'Handelsbanken', 'AIB (NI)'],
    'Italy': ['UniCredit', 'Intesa Sanpaolo', 'Banca Monte dei Paschi', 'BPM', 'Banco di Desio', 'Banca Popolare di Sondrio', 'Credito Emiliano', 'Banca Mediolanum', 'Banca Carige', 'Banca Ifis', 'Banca Finnat', 'Banca Valsabbina', 'Banca Popolare del Frusinate', 'Banca di Credito Cooperativo', 'Cassa di Risparmio'],
    'Spain': ['BBVA', 'Santander', 'CaixaBank', 'Banco Sabadell', 'Bankinter', 'Unicaja', 'Kutxabank', 'Abanca', 'Liberbank', 'Banco Cooperativo Español', 'Cajamar', 'Caja Rural', 'Banco Popular', 'Ibercaja', 'Caja de Ingenieros'],
    'Netherlands': ['ING', 'ABN AMRO', 'Rabobank', 'SNS Bank', 'Triodos Bank', 'KNAB', 'bunq', 'Handelsbanken', 'SNS REAAL', 'ASN Bank', 'NN Bank', 'RegioBank', 'Blokker Bank', 'Colorful', 'Waterland Bank'],
    'Sweden': ['Swedbank', 'SEB', 'Handelsbanken', 'Nordea', 'Danske Bank', 'Länsförsäkringar Bank', 'SBAB', 'Ikano Bank', 'Resurs Bank', 'Marginalen Bank', 'Avanza Bank', 'Nordax Bank', 'Bluestep Bank', 'Svea Bank', 'Svea Bank'],
    'Switzerland': ['UBS', 'Credit Suisse', 'PostFinance', 'Raiffeisen', 'Cantonal Bank of Zurich', 'Migros Bank', 'Coop Bank', 'Banque Cantonale Vaudoise', 'Luzerner Kantonalbank', 'Banque Cantonale de Genève', 'Banque Cantonale Neuchâteloise', 'Valiant Bank', 'Banque Cantonale de Fribourg', 'Banque Cantonale du Valais', 'Banque Cantonale de Lausanne'],
    'Poland': ['PKO Bank Polski', 'mBank', 'Pekao SA', 'ING Bank Śląski', 'Santander Bank Polska', 'Alior Bank', 'Bank Millennium', 'Getin Bank', 'Credit Agricole Bank Polska', 'BNP Paribas Bank Polska', 'Deutsche Bank Polska', 'Citibank', 'Bank Pocztowy', 'BSK', 'Toyota Bank'],
    'Austria': ['Erste Bank', 'Raiffeisen Bank', 'BKS Bank', 'Oberbank', 'BAWAG', 'Addiko Bank', 'Hypo Noe Landesbank', 'Hypo Tirol Bank', 'Volksbank', 'Bank Austria', 'UniCredit Bank Austria', 'Sparkasse Oberösterreich', 'Sparkasse Niederösterreich', 'Wiener Städtische Sparkasse', 'Bank für Tirol und Vorarlberg'],
    'Greece': ['National Bank of Greece', 'Alpha Bank', 'Eurobank', 'Piraeus Bank', 'Attica Bank', 'Pancretan Bank', 'Cooperative Bank of Chania', 'Cooperative Bank of Heraklion', 'Bank of Cyprus Greece', 'National Bank of Greece', 'Eurobank Ergasias', 'Piraeus Bank', 'Alpha Bank Cyprus', 'Hellenic Bank', 'Cooperative Bank of Thessaly'],
    'Portugal': ['Caixa Geral de Depósitos', 'Banco Comercial Português', 'Novo Banco', 'Millennium BCP', 'Santander Totta', 'Banco BPI', 'ActivoBank', 'Banco Best', 'Banco Carregosa', 'Banco CTT', 'Banco Finantia', 'Banco Invest', 'Banco Montepio', 'Banco Popular Portugal', 'Banif'],
    'Norway': ['DNB', 'Nordea', 'SpareBank 1', 'Storebrand Bank', 'Sbanken', 'Handelsbanken', 'Pareto Bank', 'Santander Consumer Bank', 'BN Bank', 'Jyske Bank', 'Skandia Finans', 'SpareBank Møre', 'SpareBank Sør', 'SpareBank Vest', 'Varner Bank'],
    'Denmark': ['Danske Bank', 'Nordea', 'Jyske Bank', 'Sydbank', 'Spar Nord', 'Ringkjøbing Landbobank', 'Lån & Spar Bank', 'Saxo Bank', 'Sparekassen Sjælland', 'Sparekassen Kronjylland', 'Sparekassen Vendsyssel', 'Sparekassen Thy', 'Sparekassen Lolland', 'Sparekassen Guldborgsund', 'Sparekassen Ballerup'],
    'Belgium': ['BNP Paribas Fortis', 'KBC Bank', 'ING Belgium', 'AXA Bank', 'Argenta', 'Beobank', 'Belfius', 'Crelan', 'Keytrade Bank', 'N26', 'Hello Bank', 'Nagelmackers', 'Vdk Bank', 'Argenta Spaarbank', 'Belfius Bank'],
    'Finland': ['Nordea', 'OP Financial Group', 'Danske Bank', 'Aktia Bank', 'S-Pankki', 'Handelsbanken', 'Nordea Bank Abp', 'Osuuspankki', 'Säästöpankki Optia', 'Aktia', 'Nordea Finans', 'S-Pankki Oy', 'Handelsbanken Finland', 'Aktia Säästöpankki', 'S-Pankki Holding'],
    'Ireland': ['AIB', 'Bank of Ireland', 'Permanent TSB', 'KBC Bank Ireland', 'Ulster Bank', 'An Post Money', 'Revolut Bank UAB', 'N26', 'Rabobank Ireland', 'Santander Consumer Finance', 'Barclays', 'Credit Suisse', 'HSBC', 'J.P. Morgan', 'Citibank'],
    'Czech Republic': ['Česká spořitelna', 'Komerční banka', 'ČSOB', 'Raiffeisenbank', 'Fio banka', 'Air Bank', 'mBank', 'Trinity Bank', 'Česká spořitelna a.s.', 'Komerční banka, a.s.', 'Československá obchodní banka', 'Raiffeisenbank a.s.', 'MONETA Money Bank', 'UniCredit Bank Czech Republic', 'Citibank'],
    'Hungary': ['OTP Bank', 'K&H Bank', 'Erste Bank Hungary', 'CIB Bank', 'UniCredit Bank', 'Raiffeisen Bank', 'Budapest Bank', 'MKB Bank', 'Gránit Bank', 'Magnet Bank', 'HDB Bank', 'K&H Bank Zrt.', 'OTP Bank Nyrt.', 'Erste Bank Hungary Zrt.', 'UniCredit Bank Hungary Zrt.'],
    'Ukraine': ['PrivatBank', 'Oschadbank', 'UkrSibbank', 'Raiffeisen Bank Aval', 'Sense Bank', 'PUMB', 'FUIB', 'Credit Agricole Bank', 'UniCredit Bank', 'Ukreximbank', 'Prominvestbank', 'Idea Bank', 'Pivdenny Bank', 'A-Bank', 'Concord Bank']
  };
  /* ===================== Country Dropdown Population (only for select) ===================== */
  function populateCountryDropdown() {
    const select = $('#modal-bank-country');
    if (!select.length) return;
    select.empty();
    select.append('<option value="">Select Country</option>');
    const countries = Object.keys(bankSuggestions).sort((a, b) => a.localeCompare(b));
    countries.forEach(country => {
      select.append(`<option value="${country}">${country}</option>`);
    });
  }
  // Populate bank dropdown using bankSuggestions
  function populateBankDropdown(country) {
    const dropdown = $('#modal-bank-dropdown');
    dropdown.empty();
    if (!country || !bankSuggestions[country]) {
      dropdown.append('<div class="p-8 text-Gray">Select a valid country first</div>');
      return;
    }
    bankSuggestions[country].forEach(b => {
      const item = $(`<div class="bank-option p-8 cursor-pointer" data-name="${b}">${b}</div>`);
      item.on('click', function () {
        $('#modal-bank-search').val($(this).text());
        $('#modal-bank-name').val($(this).data('name'));
        dropdown.empty().hide(); // Clear and hide dropdown after selection
      });
      dropdown.append(item);
    });
    // Show the dropdown after populating
    dropdown.show();
  }
  // Show/hide UK-only sort-code input
  function toggleUKSortCode(country) {
    if (!country) return;
    if (country.toLowerCase().includes('united kingdom') || country.toLowerCase() === 'uk') {
      $('.uk-only').show();
    } else {
      $('.uk-only').hide();
    }
  }
  // Handle withdraw form submit -> open modal pre-filled
  function bindWithdrawForm() {
    const form = $('#withdraw-form');
    if (!form.length) return;
    form.on('submit', function (e) {
      e.preventDefault();
      const amount = Number($('#withdraw-amount').val() || 0);
      const method = $('#withdraw-method').val();
      if (!amount || amount <= 0) {
        showToast('Enter a valid withdrawal amount', 'error');
        return;
      }
      if (!method) {
        showToast('Select a withdrawal method', 'error');
        return;
      }
      // --- UX POLISH: Reset Withdraw Modal Fields ---
      $('#modal-bank-country').val('');
      $('#modal-bank-search').val('');
      $('#modal-bank-name').val('');
      $('#modal-account-holder').val('');
      $('#modal-iban').val('');
      $('#modal-bic').val('');
      $('#modal-sort-code').val('');
      $('#modal-bank-currency').val('EUR'); // Default currency
      $('#modal-transaction-ref').val('');
      $('#modal-coin').val('btc'); // Default coin
      $('#modal-wallet-address').val('');
      // Hide conditional fields
      $('.uk-only').hide();
      $('#local-bank-fields').addClass('hidden');
      $('#wallet-address-fields').addClass('hidden');
      // --- End Polish ---

      // Populate modal fields
      $('#modal-withdraw-amount').val(formatCurrency(amount));
      // String.replace with a string pattern swaps only the FIRST match, so a
      // three-word slug kept an underscore. formatMethodLabel handles them all.
      $('#modal-method-name').text(formatMethodLabel(method));
      // reset fields visibility
      $('#local-bank-fields').addClass('hidden');
      $('#wallet-address-fields').addClass('hidden');
      // show correct fields
      if (method === 'local_bank') {
        $('#local-bank-fields').removeClass('hidden');
        // Ensure country dropdown is populated when local bank is selected
        // Only populate if the options are missing or empty
        if ($('#modal-bank-country option').length <= 1) { // Only the default option exists
            populateCountryDropdown(); // Call the function to populate the select
        }
        // Ensure the bank dropdown is ready (it should be if bindBankUI is called on init)
        // Populate banks if a country was previously selected
        const currentCountry = $('#modal-bank-country').val();
        if (currentCountry) {
            populateBankDropdown(currentCountry);
        }
      } else if (method === 'wallet_address') {
        $('#wallet-address-fields').removeClass('hidden');
      }
      // store context on confirm button
      $('#confirm-withdraw').data('withdraw-amount', amount);
      $('#confirm-withdraw').data('withdraw-method', method);
      // Hand over to step 2 (payout details).
      closeModal('#withdraw-start-modal');
      showModal('#withdraw-modal');
    });
  }
  // Bind bank country change & bank search UI
  function bindBankUI() {
    // Handle country change: clear bank search and dropdown, populate dropdown for new country
    $('#modal-bank-country').on('change', function () {
      const country = $(this).val();
      $('#modal-bank-search').val(''); // Clear the search input
      $('#modal-bank-name').val('');   // Clear the hidden name field
      // Clear and hide the dropdown
      const dropdown = $('#modal-bank-dropdown');
      dropdown.empty().hide(); // Hide after clearing
      // Populate the dropdown based on the selected country
      populateBankDropdown(country);
      // Show/hide UK sort code
      toggleUKSortCode(country);
    });

    // Handle bank search input
    $('#modal-bank-search').on('input', function () {
      const val = $(this).val().toLowerCase();
      const country = $('#modal-bank-country').val();

      // Clear the hidden name field as user types
      $('#modal-bank-name').val('');

      if (!val.trim()) {
        // If input is empty, just hide the dropdown
        $('#modal-bank-dropdown').empty().hide();
        return;
      }

      if (!country) {
        // If no country is selected, show a message or clear dropdown
        const dropdown = $('#modal-bank-dropdown');
        dropdown.empty().append('<div class="p-8 text-Gray">Please select a country first.</div>').show();
        return;
      }

      // Fetch list for country then filter
      let banks = bankSuggestions[country];
      if (!banks) {
        // fallback insensitive keys (if needed, but country should be exact from select)
        const keys = Object.keys(bankSuggestions);
        const matchKey = keys.find(k => k.toLowerCase() === country.toLowerCase());
        if (matchKey) banks = bankSuggestions[matchKey];
      }

      const dropdown = $('#modal-bank-dropdown');
      dropdown.empty();

      if (!banks || !banks.length) {
        dropdown.append('<div class="p-8 text-Gray">No banks found for this country.</div>');
        dropdown.show(); // Ensure it's shown so user sees the message
        return;
      }

      const filtered = banks.filter(b => b.toLowerCase().includes(val));
      if (!filtered.length) {
        dropdown.append('<div class="p-8 text-Gray">No matching banks.</div>');
      } else {
        filtered.forEach(b => {
          const item = $(`<div class="bank-option p-8 cursor-pointer" data-name="${b}">${b}</div>`);
          item.on('click', function () {
            $('#modal-bank-search').val($(this).text()); // Set search input to selected name
            $('#modal-bank-name').val($(this).data('name')); // Set hidden name field
            dropdown.empty().hide(); // Clear and hide dropdown after selection
          });
          dropdown.append(item);
        });
      }
      dropdown.show(); // Show the dropdown after populating
    });

    // Hide dropdown if user clicks outside
    $(document).on('click', function (e) {
      if (!$(e.target).closest('#modal-bank-search, #modal-bank-dropdown').length) {
        $('#modal-bank-dropdown').hide();
      }
    });
  }
  // Confirm withdraw (modal confirm button)
  function bindConfirmWithdraw() {
    $('#confirm-withdraw').on('click', async function () {
      const amount = Number($(this).data('withdraw-amount') || 0);
      const method = $(this).data('withdraw-method') || '';
      if (!amount || !method) {
        showToast('Invalid withdraw context', 'error');
        return;
      }
      // Collect details based on method
      let details = {};
      if (method === 'local_bank') {
        details.country = $('#modal-bank-country').val();
        details.bank_name = $('#modal-bank-name').val() || $('#modal-bank-search').val();
        details.account_holder = $('#modal-account-holder').val();
        details.iban = $('#modal-iban').val();
        details.bic = $('#modal-bic').val();
        details.sort_code = $('#modal-sort-code').val();
        details.currency = $('#modal-bank-currency').val();
        details.transaction_ref = $('#modal-transaction-ref').val();
      } else if (method === 'wallet_address') {
        details.coin = $('#modal-coin').val();
        details.address = $('#modal-wallet-address').val();
      }
      // Basic validation
      if (method === 'local_bank' && (!details.bank_name || !details.account_holder)) {
        showToast('Please provide bank name and account holder', 'error');
        return;
      }
      if (method === 'wallet_address' && (!details.address || details.address.length < 6)) {
        showToast('Please provide a valid wallet address', 'error');
        return;
      }
      // Send withdraw request
      try {
        showToast('Submitting withdrawal request...', 'info');
        const res = await fetchApi('/api/backend/wallet.php', { action: 'withdraw_request', amount, method, details });
        if (res.status === 'success') {
          showToast(res.message || 'Withdrawal request submitted', 'success');
          closeModal('#withdraw-modal');
          // refresh balances & dashboard
          await loadDashboardData();
          // NOTE: Removed loadPendingDeposits() call here.
          // The pending deposit modal should only show when explicitly requested by the user (e.g., clicking the header button).
          // loadPendingDeposits(); // This would incorrectly show the deposit list after withdrawal.
        } else if (res.data && res.data.code === 'kyc_required') {
          // A blocked withdrawal is only actionable if we say where to go.
          // Close the modal first so the banner underneath is visible.
          closeModal('#withdraw-modal');
          showToast(res.message || 'Identity verification required', 'error');
          setTimeout(function () { window.location.href = '/dashboard.kyc'; }, 1800);
        } else {
          showToast(res.message || 'Withdrawal failed', 'error');
        }
      } catch (err) {
        console.error('Withdraw error', err);
        showToast('Failed to submit withdrawal', 'error');
      }
    });
  }
  /* ===================== Misc Bindings ===================== */
  function bindCopyButtons() {
    // dynamic copy buttons (pending-deposit-address-group has copy-btn)
    $(document).on('click', '.copy-btn', function () {
      const target = $(this).data('target');
      if (!target) return;
      copyToClipboard(`#${target}`);
    });
    // if you have any other copy triggers add class copy-trigger with data-target attr
    $(document).on('click', '.copy-trigger', function () {
      const target = $(this).data('target');
      copyToClipboard(target);
    });
  }
  /* ===================== Init & Wiring ===================== */
  /* ===================== Wallet: quick actions & balance privacy ===================== */
  // The deposit/withdraw forms used to be two inline panels on the page; they
  // are now modals opened from the circular action row under the balance card.
  /* ===================== Wallet page: activity + addresses ============= */

  // Reads the SAME endpoint as /dashboard.transactions rather than the
  // dashboard summary's recent_activity, so the wallet and the transactions
  // page cannot show different histories for the same account.
  async function loadWalletActivity() {
    const $body = $('#wallet-activity');
    if (!$body.length) return;

    try {
      const res = await fetchApi('/api/backend/transactions.php', {
        page: 1, limit: 8, status: 'all', search: '',
      });
      if (res.status !== 'success') {
        mvcRenderTransactionRows([], $body, { emptyText: 'Could not load activity.' });
        return;
      }
      mvcRenderTransactionRows(res.data.transactions, $body, {
        emptyText: 'No activity yet. Deposits, withdrawals and payouts appear here.',
      });
    } catch (err) {
      console.error('Wallet activity error', err);
      mvcRenderTransactionRows([], $body, { emptyText: 'Could not load activity.' });
    }
  }

  // The read-only "Deposit addresses" card this used to fill is gone: the
  // addresses now appear inside the deposit modal, where they are actionable.
  // The fetch itself lives in loadDepositNetworks() above, which feeds the
  // method picker's network <select>.

  function bindWalletActions() {
    $(document).on('click', '[data-open-modal]', function (e) {
      e.preventDefault();
      showModal($(this).data('open-modal'));
    });

    // Copies any literal string: the wallet reference on the balance card, and
    // the deposit addresses and memo tags in the address list. data-copy-label
    // names what was copied, since "Wallet reference copied" is wrong for both
    // of the latter.
    $(document).on('click', '[data-copy-text]', function () {
      const $btn = $(this);
      const text = String($btn.data('copy-text') || '');
      if (!text) return;
      const label = String($btn.data('copy-label') || 'Wallet reference');

      /* Swap the icon to a tick for ~1.6s alongside the toast.
       *
       * The toast is transient and appears in a corner; the confirmation people
       * actually look for is on the control they just pressed. The icon swap is
       * the same idiom as the balance-eye toggle below, plus a timer.
       *
       * The timer id lives on the element, so a second click clears the pending
       * revert instead of letting the first timer fire mid-way through the
       * second confirmation and leave the button stuck on the copy icon. */
      const showTick = () => {
        const $icon = $btn.find('i').first();
        if (!$icon.length) return;

        const prior = $btn.data('copyTimer');
        if (prior) {
          clearTimeout(prior);
        } else {
          // Only capture the resting class on the FIRST click of a burst -
          // otherwise the second click would record "ph ph-check" as the
          // resting state and the button would never go back.
          $btn.data('copyIconClass', $icon.attr('class'));
        }

        $icon.attr('class', 'ph ph-check');
        $btn.addClass('is-copied');

        $btn.data('copyTimer', setTimeout(() => {
          $icon.attr('class', $btn.data('copyIconClass') || 'ph ph-copy');
          $btn.removeClass('is-copied').removeData('copyTimer');
        }, 1600));
      };

      const done = () => {
        showTick();
        showToast(`${label} copied`, 'success');
      };

      // execCommand is the fallback, not the alternative: the async Clipboard
      // API rejects when the document lacks focus or the permission is denied,
      // so a rejection has to fall through here rather than surface an error.
      const legacyCopy = () => {
        const tmp = $('<textarea>').val(text)
          .css({ position: 'fixed', top: 0, left: 0, opacity: 0 }).appendTo('body');
        tmp[0].select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
        tmp.remove();
        ok ? done() : showToast('Could not copy', 'error');
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(legacyCopy);
      } else {
        legacyCopy();
      }
    });
  }

  // Eye toggle on the balance card. Masking is presentational only - the
  // figure stays in the DOM so the auto-refresh keeps updating it.
  //
  // The choice persists. Somebody who hides their balance is usually doing it
  // because of where they are sitting, and that does not stop being true when
  // they open the next page - re-revealing it on every navigation defeats the
  // point of the control. Per-browser only: this is a display preference, so it
  // belongs in localStorage and never goes to the server.
  const BALANCE_HIDDEN_KEY = 'mvc-balance-hidden';

  // Storage throws outright in private mode and when a browser is set to block
  // site data, so every access is guarded and a failure just means "shown".
  function readBalanceHidden() {
    try {
      return localStorage.getItem(BALANCE_HIDDEN_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function writeBalanceHidden(hidden) {
    try {
      localStorage.setItem(BALANCE_HIDDEN_KEY, hidden ? '1' : '0');
    } catch (e) {
      /* preference simply will not persist; the toggle still works */
    }
  }

  // Single place that paints the state, so the restore path and the click path
  // cannot drift apart.
  function applyBalanceHidden(card, btn, hidden) {
    card.toggleClass('is-masked', hidden);
    if (!btn.length) return;
    btn.attr('aria-pressed', String(hidden))
       .attr('aria-label', hidden ? 'Show balance' : 'Hide balance')
       .attr('title', hidden ? 'Show balance' : 'Hide balance');
    btn.find('i').attr('class', hidden ? 'ph ph-eye-slash' : 'ph ph-eye');
  }

  function bindBalanceVisibility() {
    const card = $('[data-balance-card]');
    if (!card.length) return;

    applyBalanceHidden(card, $('[data-balance-toggle]'), readBalanceHidden());

    $(document).on('click', '[data-balance-toggle]', function () {
      const hidden = !card.hasClass('is-masked');
      applyBalanceHidden(card, $(this), hidden);
      writeBalanceHidden(hidden);
    });
  }

  // Render an amount with de-emphasised decimals (see .amt-dec in the CSS).
  function setSplitAmount(selector, value) {
    const el = $(selector);
    if (!el.length) return;
    const s = formatCurrency(value);
    const dot = s.lastIndexOf('.');
    if (dot === -1) { el.text(s); return; }
    el.empty()
      .append($('<span>').addClass('amt-int').text(s.slice(0, dot)))
      .append($('<span>').addClass('amt-dec').text(s.slice(dot)));
  }

  $(function () {
    selectImages();
    menuleft();
    tabs();
    collapse_menu();
    showpass();
    select_colors_theme();
    icon_function();
    box_search();
    variant_picker();
    fullcheckbox();
    counter();
    sort();
    preloader();
    // Wallet & Dashboard specific
    loadDashboardData(); // This now updates both wallet.php and dashboard.php elements
    bindDepositForm();
    bindDepositMethodPicker();
    bindWithdrawForm(); // This now includes logic for populating countries on 'local_bank' + UX polish
    bindBankUI();       // This handles country change and bank search
    bindConfirmWithdraw();
    bindPendingConfirmPaid();
    bindPendingDeposits();
    bindCopyButtons();
    // Dynamic population - Call populateCountryDropdown to fill the select on page load
    populateCountryDropdown();
    // Start the auto-refresh timer
    startAutoRefresh();
    /* The #pending-deposits-btn binding that lived here targeted an id that
       existed in no page, so a member had no way back to a pending deposit at
       all. The #pending-deposits-box card on the wallet page replaces it. */
    bindWalletActions();
    bindBalanceVisibility();
    // Wallet page only - all three are no-ops when their containers are absent.
    loadWalletActivity();
    loadDepositNetworks();
    loadPendingDeposits();

    // NOWPayments sends the member back here via success_url / cancel_url after
    // the hosted invoice. Nothing read those parameters, so a member who had
    // just paid landed on an unchanged wallet with no acknowledgement at all -
    // the natural reading of which is "it failed", and the natural response is
    // to pay again. The credit itself is asynchronous (it lands on the IPN, not
    // on this redirect), so the copy promises confirmation rather than funds.
    handleDepositReturn();

    // close modals when escape pressed. Was a fixed list of two ids, so newer
    // dialogs ignored Escape; close whatever is actually open instead.
    $(document).on('keydown', function (e) {
      if (e.key !== 'Escape') return;
      const open = $('.modal.is-open');
      if (open.length) closeModal('#' + open.last().attr('id'));
    });
    // Ensure auto refresh stops on page unload
    window.addEventListener('beforeunload', function () {
      stopAutoRefresh();
    });
    // make refreshDashboard available on window (already defined above)
    window.refreshDashboard = loadDashboardData;
    window.refreshWalletActivity = loadWalletActivity;
  });

  /* ===================== Deposit return from NOWPayments =============== */
  // Strips its own query parameters afterwards via replaceState, so a refresh
  // or a back-navigation does not replay the toast.
  function handleDepositReturn() {
    // Same guard loadWalletActivity() uses. #wallet-balance is NOT on this page
    // - it belongs to the dashboard summary - so guarding on it made this a
    // silent no-op everywhere.
    if (!$('#wallet-activity').length) return;   // wallet page only

    const params = new URLSearchParams(window.location.search);
    const outcome = params.get('deposit');
    if (outcome !== 'success' && outcome !== 'cancel') return;

    if (outcome === 'success') {
      showToast(
        'Payment received. Your deposit is confirming on-chain and your balance ' +
        'updates automatically once the network confirms it.',
        'success', 7000
      );
      // The IPN may already have landed while the member was on the invoice.
      loadPendingDeposits();
      loadWalletActivity();
    } else {
      showToast('Deposit cancelled. Nothing was charged.', 'info', 5000);
    }

    params.delete('deposit');
    params.delete('ref');
    const qs = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
  }

  // transaction.js loads after this file (both deferred, so order holds) and
  // renders the same rows. Exporting the renderer is what keeps the wallet,
  // the dashboard and the transactions page from drifting apart again.
  window.mvcRenderTransactionRows = mvcRenderTransactionRows;
})(jQuery);

/* =========================================================
   Shared: plan-details side panel (dashboard product pages)
   Called by each product page's plan/asset select handler.
   Pass null to show the empty/placeholder state.
   data = { name, roi, roiLabel, risk, meta:[[k,v],...], summary }
   ========================================================= */
window.mvcRenderPlanPanel = function (data) {
  const empty = document.getElementById('pdp-empty');
  const content = document.getElementById('pdp-content');
  if (!content) return; // panel not on this page
  if (!data) {
    if (empty) empty.classList.remove('hidden');
    content.classList.add('hidden');
    return;
  }
  if (empty) empty.classList.add('hidden');
  content.classList.remove('hidden');

  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = (val == null || val === '') ? '—' : val;
  };
  set('pdp-name', data.name);

  // The rate arrives as one string ("1.45% per week"). Rendering it whole put
  // the qualifier at the same 38px as the figure, so the period is split off
  // into a <small>. Built with DOM nodes rather than innerHTML.
  const roiEl = document.getElementById('pdp-roi');
  if (roiEl) {
    roiEl.textContent = '';
    const raw = (data.roi == null || data.roi === '') ? '—' : String(data.roi);
    const at = raw.search(/\s+per\s+/i);
    if (at === -1) {
      roiEl.textContent = raw;
    } else {
      roiEl.appendChild(document.createTextNode(raw.slice(0, at)));
      const small = document.createElement('small');
      small.textContent = raw.slice(at);
      roiEl.appendChild(small);
    }
  }

  const roiLabel = document.getElementById('pdp-roi-label');
  if (roiLabel) roiLabel.textContent = data.roiLabel || 'Expected ROI';

  const risk = document.getElementById('pdp-risk');
  if (risk) {
    if (data.risk) { risk.textContent = data.risk; risk.style.display = ''; }
    else { risk.style.display = 'none'; }
  }

  const meta = document.getElementById('pdp-meta');
  if (meta) {
    meta.innerHTML = '';
    (data.meta || []).forEach(([k, v]) => {
      if (v == null || v === '') return;
      const li = document.createElement('li');
      const ks = document.createElement('span'); ks.className = 'k'; ks.textContent = k;
      const vs = document.createElement('span'); vs.className = 'v'; vs.textContent = v;
      li.appendChild(ks); li.appendChild(vs);
      meta.appendChild(li);
    });
  }
  set('pdp-summary', data.summary);
};
