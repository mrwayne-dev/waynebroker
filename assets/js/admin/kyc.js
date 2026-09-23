/* =====================================================================
 * FILE: /assets/js/admin/kyc.js
 * Admin identity-verification queue.
 *
 * Documents are never linked directly - uploads/kyc is denied by its own
 * .htaccess. Every image src points at api/admin/kyc_file.php, which
 * re-checks the admin session before it streams a byte.
 * ===================================================================== */
(function () {
  'use strict';

  var rowsEl = document.getElementById('kyc-rows');
  if (!rowsEl) return;

  var state = { status: 'pending', page: 1, perPage: 15 };
  var modalEl = document.getElementById('kyc-modal');
  var modal = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function toast(m, k) {
    if (typeof showToast === 'function') showToast(m, k || 'error');
  }
  function post(payload) {
    return fetchApi('/api/admin/kyc.php', payload);
  }

  var DOC_LABEL = {
    passport: 'Passport',
    national_id: 'National ID',
    drivers_license: "Driver's licence",
  };
  var BADGE = {
    pending:  ['Pending',  'mvc-kyc-badge--pending'],
    approved: ['Approved', 'mvc-kyc-badge--approved'],
    rejected: ['Rejected', 'mvc-kyc-badge--rejected'],
  };

  function fmtDate(s) {
    if (!s) return '—';
    var d = new Date(s.replace(' ', 'T'));
    return isNaN(d) ? esc(s) : d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
  }

  /* --- Queue ------------------------------------------------------- */
  async function load() {
    rowsEl.innerHTML = '<tr><td colspan="6" class="mvc-empty">Loading&hellip;</td></tr>';
    var res = await post({ action: 'list', status: state.status, page: state.page, per_page: state.perPage });

    if (res.status !== 'success') {
      rowsEl.innerHTML = '<tr><td colspan="6" class="mvc-empty">' + esc(res.message || 'Could not load submissions.') + '</td></tr>';
      return;
    }
    var d = res.data || {};
    var rows = d.rows || [];

    if (!rows.length) {
      rowsEl.innerHTML = '<tr><td colspan="6" class="mvc-empty">Nothing to review here.</td></tr>';
    } else {
      rowsEl.innerHTML = rows.map(function (r) {
        var b = BADGE[r.status] || BADGE.pending;
        return '<tr>' +
          '<td><div class="f14-bold">' + esc(r.account_name || '—') + '</div>' +
              '<div class="f12-regular text-GrayDark">' + esc(r.email) + '</div></td>' +
          '<td>' + esc(DOC_LABEL[r.id_type] || r.id_type) + '</td>' +
          '<td>' + esc(r.country || '—') + '</td>' +
          '<td>' + fmtDate(r.created_at) + '</td>' +
          '<td><span class="mvc-kyc-badge ' + b[1] + '">' + b[0] + '</span></td>' +
          '<td class="text-end"><button class="tf-button bg-Accent f12-bold" data-kyc-open="' + Number(r.id) + '">' +
            (r.status === 'pending' ? 'Review' : 'View') + '</button></td>' +
        '</tr>';
      }).join('');
    }

    if (window.mvcRenderPagination) {
      window.mvcRenderPagination('#kyc-pagination', {
        page: d.page, pages: d.pages,
        onPage: function (p) { state.page = p; load(); },
      });
    }
    refreshCounts();
  }

  async function refreshCounts() {
    var res = await post({ action: 'counts' });
    if (res.status !== 'success') return;
    var el = document.getElementById('kyc-count-pending');
    if (el) {
      var n = res.data.pending || 0;
      el.textContent = n;
      el.setAttribute('data-zero', n === 0 ? '1' : '0');
    }
  }

  /* --- Filters ----------------------------------------------------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-kyc-filter]'), function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('[data-kyc-filter]').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      state.status = btn.getAttribute('data-kyc-filter');
      state.page = 1;
      load();
    });
  });

  /* --- Detail modal ------------------------------------------------ */
  function docTile(label, url) {
    if (!url) return '';
    // A PDF cannot be thumbnailed, and we do not know the type until it is
    // fetched - so render an <img> and swap to a link tile if it fails to
    // decode, which is exactly what a PDF response does.
    return '<div class="mvc-kyc-doc">' +
      '<div class="mvc-kyc-doc__label">' + esc(label) + '</div>' +
      '<img src="' + esc(url) + '" alt="' + esc(label) + '" ' +
        'onerror="this.outerHTML=\'<a class=&quot;mvc-kyc-doc__pdf&quot; target=&quot;_blank&quot; rel=&quot;noopener&quot; href=&quot;' + esc(url) + '&quot;><i class=&quot;ph ph-file-pdf&quot;></i>Open document</a>\'" ' +
        'onclick="window.open(this.src, \'_blank\', \'noopener\')">' +
    '</div>';
  }

  function metaRow(k, v) {
    return '<div class="mvc-kyc-meta__row"><span>' + esc(k) + '</span><strong>' + esc(v || '—') + '</strong></div>';
  }

  async function openDetail(id) {
    var body = document.getElementById('kyc-modal-body');
    body.innerHTML = '<div class="mvc-empty">Loading&hellip;</div>';
    if (!modal && window.bootstrap) modal = new bootstrap.Modal(modalEl);
    modal && modal.show();

    var res = await post({ action: 'detail', id: id });
    if (res.status !== 'success') {
      body.innerHTML = '<div class="mvc-empty">' + esc(res.message || 'Could not load submission.') + '</div>';
      return;
    }
    var s = res.data.submission;
    var pending = s.status === 'pending';

    body.innerHTML =
      '<div class="mvc-kyc-docs">' +
        docTile('Front of document', s.doc_front_url) +
        docTile('Back of document',  s.doc_back_url) +
        docTile('Selfie',            s.selfie_url) +
      '</div>' +
      '<div class="mvc-kyc-meta">' +
        metaRow('Name on document', s.full_name) +
        metaRow('Account name',     s.account_name) +
        metaRow('Email',            s.email) +
        metaRow('Document',         DOC_LABEL[s.id_type] || s.id_type) +
        metaRow('Document number',  s.id_number) +
        metaRow('Date of birth',    s.date_of_birth) +
        metaRow('Country of issue', s.country) +
        metaRow('Account country',  s.account_country) +
        metaRow('Phone',            s.phone) +
        metaRow('Submitted',        fmtDate(s.created_at)) +
        (pending ? '' : metaRow('Reviewed by', s.reviewer_name) + metaRow('Reviewed', fmtDate(s.reviewed_at))) +
        (s.reject_reason ? metaRow('Reason given', s.reject_reason) : '') +
      '</div>' +
      (pending
        ? '<div class="mvc-field mvc-field--textarea">' +
            '<div class="mvc-field__top">' +
              '<label class="mvc-field__label" for="kyc-reason">Reason</label>' +
              '<span class="mvc-field__hint">Required to reject &middot; shown to the member</span>' +
            '</div>' +
            '<div class="mvc-field__row">' +
              '<textarea id="kyc-reason" class="mvc-field__input" rows="2" maxlength="255" ' +
                'placeholder="e.g. The back of the card is cropped - all four corners must be visible."></textarea>' +
            '</div>' +
          '</div>' +
          '<div class="d-flex gap8 justify-end mt-16">' +
            '<button class="tf-button bg-Red f14-bold" data-kyc-decide="reject"  data-id="' + Number(id) + '">Reject</button>' +
            '<button class="tf-button bg-Primary f14-bold" data-kyc-decide="approve" data-id="' + Number(id) + '">Approve</button>' +
          '</div>'
        : '<div class="mvc-kyc-note"><i class="ph ph-check-circle"></i><span>This submission has already been reviewed.</span></div>');
  }

  /* --- Decisions --------------------------------------------------- */
  document.addEventListener('click', async function (e) {
    var open = e.target.closest('[data-kyc-open]');
    if (open) { openDetail(Number(open.getAttribute('data-kyc-open'))); return; }

    var decide = e.target.closest('[data-kyc-decide]');
    if (!decide) return;

    var decision = decide.getAttribute('data-kyc-decide');
    var id = Number(decide.getAttribute('data-id'));
    var reasonEl = document.getElementById('kyc-reason');
    var reason = reasonEl ? reasonEl.value.trim() : '';

    // Mirror the server rule client-side so the reviewer is told before the
    // round trip, not after.
    if (decision === 'reject' && !reason) {
      toast('Give a reason so the member knows what to fix.');
      reasonEl && reasonEl.focus();
      return;
    }
    if (decision === 'approve') {
      var ok = await mvcConfirm({
        title: 'Approve this verification?',
        body: 'Withdrawals open on this member\u2019s account immediately, and they are emailed to say they are verified.',
        confirmLabel: 'Approve',
      });
      if (!ok) return;
    }

    decide.disabled = true;
    var res = await post({ action: 'review', id: id, decision: decision, reason: reason });
    if (res.status === 'success') {
      toast(res.message, 'success');
      modal && modal.hide();
      load();
    } else {
      toast(res.message || 'Could not save the decision.');
      decide.disabled = false;
    }
  });

  load();
})();
