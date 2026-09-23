/**
 * FILE: /assets/js/mvc-pagination.js
 * ============================================================
 * The single pagination renderer.
 *
 * Replaced five: transaction.js (.page-btn, no prev/next, no window),
 * three byte-identical copies in admin/users.js, admin/wallet.js and
 * admin/transactions.js, and admin/plans.js (numbers only, no window).
 *
 * The admin four emitted `.page-link` - a Bootstrap class that only
 * styles itself inside `<li class="page-item">` markup this app has
 * never emitted, so they rendered as blue Bootstrap boxes fighting
 * .tf-button.style-1. They also applied a `disabled` CLASS but never
 * the attribute, and no stylesheet defined that class, so Previous on
 * page 1 looked and announced as live and did nothing when clicked.
 *
 * No jQuery on purpose: this file also loads on pages/user/transactions.php,
 * where api.js runs before jquery.min.js.
 * ============================================================
 */
(function (window, document) {
  'use strict';

  var ELLIPSIS = '…';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    // textContent, never innerHTML - page labels are numbers, but the
    // container is caller-supplied.
    if (text != null) n.textContent = String(text);
    return n;
  }

  /**
   * Which pages to show: always the first and last, plus `radius` either
   * side of the current one, with an ellipsis wherever the run breaks.
   * Returns a mix of numbers and the literal ellipsis string.
   */
  function windowOf(page, pages, radius) {
    var keep = {};
    keep[1] = true;
    keep[pages] = true;
    for (var i = page - radius; i <= page + radius; i++) {
      if (i >= 1 && i <= pages) keep[i] = true;
    }

    var sorted = Object.keys(keep).map(Number).sort(function (a, b) { return a - b; });
    var out = [];
    for (var j = 0; j < sorted.length; j++) {
      if (j > 0) {
        var gap = sorted[j] - sorted[j - 1];
        // A single skipped page costs the same width as the ellipsis that
        // would replace it, so render the page instead of hiding it for free.
        if (gap === 2) out.push(sorted[j] - 1);
        else if (gap > 2) out.push(ELLIPSIS);
      }
      out.push(sorted[j]);
    }
    return out;
  }

  function button(label, page, opts) {
    var b = el('button', 'mvc-pager__btn' + (opts.cls ? ' ' + opts.cls : ''), label);
    b.type = 'button';
    b.setAttribute('data-page', String(page));
    if (opts.ariaLabel) b.setAttribute('aria-label', opts.ariaLabel);
    if (opts.current) b.setAttribute('aria-current', 'page');
    if (opts.disabled) {
      // Attribute AND class AND aria. The attribute is what actually takes it
      // out of the tab order and blocks the click; the class is what the CSS
      // can see. The old code set only a class, so the button stayed
      // focusable and announced as enabled.
      b.disabled = true;
      b.classList.add('is-disabled');
      b.setAttribute('aria-disabled', 'true');
    }
    return b;
  }

  /**
   * @param {string|Element} container '#pagination' or the node itself
   * @param {{page:number, pages:number, onPage:function, radius?:number,
   *          hideWhenSingle?:boolean}} state
   */
  function mvcRenderPagination(container, state) {
    var root = (typeof container === 'string') ? document.querySelector(container) : container;
    if (!root) return;

    root.textContent = '';
    root.className = 'mvc-pager';
    root.setAttribute('role', 'navigation');
    root.setAttribute('aria-label', 'Pagination');

    var pages = Math.max(1, Number(state.pages) || 1);
    var page = Math.min(pages, Math.max(1, Number(state.page) || 1));
    var radius = state.radius == null ? 1 : state.radius;

    if (pages <= 1 && state.hideWhenSingle !== false) {
      root.hidden = true;
      return;
    }
    root.hidden = false;

    root.appendChild(button('‹', page - 1, {
      cls: 'mvc-pager__btn--nav', ariaLabel: 'Previous page', disabled: page === 1,
    }));

    windowOf(page, pages, radius).forEach(function (p) {
      if (p === ELLIPSIS) {
        var gap = el('span', 'mvc-pager__gap', ELLIPSIS);
        gap.setAttribute('aria-hidden', 'true');
        root.appendChild(gap);
        return;
      }
      root.appendChild(button(p, p, {
        cls: 'mvc-pager__btn--num' + (p === page ? ' is-current' : ''),
        ariaLabel: 'Page ' + p,
        current: p === page,
      }));
    });

    // Always emitted; CSS shows it only below 480px, where the number chips
    // are hidden. Rendering it unconditionally keeps the renderer free of any
    // viewport logic.
    var status = el('span', 'mvc-pager__status', page + ' / ' + pages);
    status.setAttribute('aria-hidden', 'true');
    root.appendChild(status);

    root.appendChild(button('›', page + 1, {
      cls: 'mvc-pager__btn--nav', ariaLabel: 'Next page', disabled: page === pages,
    }));

    // One delegated listener per render. textContent = '' above discards the
    // previous children, and assigning onclick replaces rather than stacks, so
    // listeners cannot accumulate across renders.
    root.onclick = function (e) {
      var btn = e.target.closest ? e.target.closest('.mvc-pager__btn') : null;
      if (!btn || btn.disabled || !root.contains(btn)) return;
      var next = Number(btn.getAttribute('data-page'));
      if (!next || next === page || next < 1 || next > pages) return;
      state.onPage(next);
    };
  }

  window.mvcRenderPagination = mvcRenderPagination;
})(window, document);
