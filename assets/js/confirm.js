/* =======================================================
   mvcConfirm — the one confirmation dialog
   -------------------------------------------------------
   Replaces window.confirm() across the admin panel and the member
   dashboard. The native dialog was wrong here for four reasons:

     - it is unstyled OS chrome dropped into a themed product, and it
       ignores dark mode entirely
     - it blocks the event loop, freezing timers and any in-flight render
     - it cannot show structure, so "Approve this member? Withdrawals will
       open on their account immediately." had to be one flat string with
       no way to weight the consequence
     - on a phone it is a system sheet the user has no reason to trust,
       arriving without the site's own visual context

   The admin panel already had hand-built dialogs for the deposit and
   withdrawal decisions (pages/admin/_partials/pending-modals.php), which
   is the right treatment - but one bespoke modal per action does not
   scale, and four other actions were left on the native call. This is the
   generic version: no markup to add to a page, no per-action ids.

       const ok = await mvcConfirm({
         title:        'Approve this member?',
         body:         'Withdrawals open immediately.',
         confirmLabel: 'Approve',
         danger:       false,
       });
       if (!ok) return;

   Deliberately dependency-free and self-injecting, so it works on any
   page that loads it without touching that page's PHP, and it reuses the
   existing .modal CSS so it inherits the theme for free.
   ======================================================= */
(function () {
  'use strict';

  var el = null;      // the dialog, built once on first use
  var active = null;  // { resolve, trigger } while open

  function build() {
    if (el) return el;

    el = document.createElement('div');
    el.className = 'modal mvc-confirm';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('aria-labelledby', 'mvc-confirm-title');
    // Above .modal's 9999: this often opens ON TOP of another dialog - the
    // KYC approval sits over the open review modal - and a confirmation that
    // renders behind the thing it is confirming is worse than no dialog.
    el.style.zIndex = '10000';

    el.innerHTML =
      '<div class="modal-overlay" data-mvc-confirm-cancel></div>' +
      '<div class="modal-content" tabindex="-1">' +
        '<div class="modal-header">' +
          '<div>' +
            '<h2 id="mvc-confirm-title"></h2>' +
            '<p class="modal-header__sub" id="mvc-confirm-sub" hidden></p>' +
          '</div>' +
          '<button type="button" class="modal-close button-close-modal" ' +
            'data-mvc-confirm-cancel aria-label="Close dialog">&times;</button>' +
        '</div>' +
        '<div class="modal-body">' +
          '<p id="mvc-confirm-body"></p>' +
          '<div class="modal-actions">' +
            '<button type="button" class="tf-button" id="mvc-confirm-cancel"></button>' +
            '<button type="button" class="modal-confirm-btn" id="mvc-confirm-ok"></button>' +
          '</div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(el);

    el.addEventListener('click', function (e) {
      if (e.target.closest('[data-mvc-confirm-cancel]')) finish(false);
    });
    el.querySelector('#mvc-confirm-cancel').addEventListener('click', function () { finish(false); });
    el.querySelector('#mvc-confirm-ok').addEventListener('click', function () { finish(true); });

    return el;
  }

  function onKey(e) {
    if (!active) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      // stopImmediatePropagation, not just preventDefault. This listener is on
      // document in the CAPTURE phase, and the dialog underneath is usually
      // Bootstrap's, which has its own Escape handler on document. Without
      // stopping the event it reached that handler too, so one Escape
      // dismissed the confirmation AND the review dialog behind it - throwing
      // the reviewer back to the list and losing their place.
      e.stopImmediatePropagation();
      finish(false);
      return;
    }

    // Keep Tab inside the dialog. Without this, tabbing walks onto the page
    // behind an aria-modal="true" overlay, which is both a focus trap failure
    // and a way to click the button you were asked to confirm.
    if (e.key !== 'Tab') return;
    var f = el.querySelectorAll('button:not([disabled])');
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function finish(result) {
    if (!active) return;
    var a = active;
    active = null;

    el.setAttribute('aria-hidden', 'true');
    el.classList.remove('is-open', 'open');
    document.removeEventListener('keydown', onKey, true);

    // Only release the scroll lock if this was not stacked over another open
    // dialog - otherwise dismissing the confirmation unlocks the page while
    // the dialog underneath is still up.
    //
    // Three dialects have to be checked, not one. The admin panel's own
    // helper sets .is-open, the member helper sets .open, and the KYC review
    // dialog this most often opens over is a BOOTSTRAP modal, which marks
    // itself .show and matches neither. Checking only the first two released
    // the scroll lock every time, on the one screen that stacks.
    if (!document.querySelector('.modal.is-open, .modal.open, .modal.show')) {
      document.body.style.overflow = '';
    }

    if (a.trigger && document.contains(a.trigger)) {
      try { a.trigger.focus(); } catch (err) { /* trigger went away with a re-render */ }
    }
    a.resolve(result);
  }

  /**
   * @param  {Object|string} opts  message, or { title, body, confirmLabel,
   *                               cancelLabel, danger }
   * @return {Promise<boolean>}    true when confirmed
   */
  function mvcConfirm(opts) {
    if (typeof opts === 'string') opts = { body: opts };
    opts = opts || {};

    build();

    // A second call while one is open resolves the first as cancelled rather
    // than leaving its promise dangling forever.
    if (active) finish(false);

    el.querySelector('#mvc-confirm-title').textContent = opts.title || 'Are you sure?';

    var sub = el.querySelector('#mvc-confirm-sub');
    if (opts.subtitle) { sub.textContent = opts.subtitle; sub.hidden = false; }
    else { sub.textContent = ''; sub.hidden = true; }

    var body = el.querySelector('#mvc-confirm-body');
    body.textContent = opts.body || '';
    body.hidden = !opts.body;

    var ok = el.querySelector('#mvc-confirm-ok');
    ok.textContent = opts.confirmLabel || 'Confirm';
    el.querySelector('#mvc-confirm-cancel').textContent = opts.cancelLabel || 'Cancel';

    el.classList.toggle('mvc-modal--danger', !!opts.danger);
    ok.classList.toggle('bg-Red', !!opts.danger);

    el.setAttribute('aria-hidden', 'false');
    el.classList.add('is-open', 'open');
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey, true);

    return new Promise(function (resolve) {
      active = { resolve: resolve, trigger: document.activeElement };
      // Destructive actions focus Cancel, so a stray Enter does not delete
      // anything; ordinary ones focus the confirm button.
      setTimeout(function () {
        var target = opts.danger
          ? el.querySelector('#mvc-confirm-cancel')
          : ok;
        try { target.focus(); } catch (err) { /* not focusable yet */ }
      }, 20);
    });
  }

  window.mvcConfirm = mvcConfirm;
})();
