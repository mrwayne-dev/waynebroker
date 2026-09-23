/* =====================================================================
 * FILE: /assets/js/kyc.js
 * Member identity verification form.
 *
 * Multipart, so this does NOT go through fetchApi() - that JSON-encodes its
 * payload. It follows the same contract as the avatar upload in profile.js:
 * no hand-set Content-Type (the browser must generate the multipart
 * boundary) and the CSRF token in the header via mvcWithCsrf().
 * ===================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('kyc-form');
  if (!form) return;                     // approved / pending members get no form

  // Must stay in step with KYC_MAX_BYTES in api/backend/kyc.php AND with
  // upload_max_filesize in .htaccess / .user.ini - the PHP ini limits sit
  // under both checks and reject the body before either one runs.
  var MAX_BYTES = 10 * 1024 * 1024;

  var idType   = document.getElementById('kyc-id-type');
  var backWrap = document.getElementById('kyc-back-wrap');
  var submit   = document.getElementById('kyc-submit');

  function toast(msg, kind) {
    if (typeof showToast === 'function') showToast(msg, kind || 'error');
    else if (kind === 'error') alert(msg);
  }

  /* --- Back-of-document is required for everything except a passport ---- */
  function syncBackField() {
    var needsBack = idType.value === 'national_id' || idType.value === 'drivers_license';
    backWrap.hidden = !needsBack;
    var input = document.getElementById('kyc-doc-back');
    if (!needsBack) {
      // Clear it too: leaving a file staged on a hidden field would submit the
      // back of a licence alongside a passport.
      input.value = '';
      resetDrop(backWrap);
    }
  }
  idType.addEventListener('change', syncBackField);
  syncBackField();

  /* --- Drop zones ------------------------------------------------------- */
  function resetDrop(zone) {
    var img = zone.querySelector('.mvc-upload__preview');
    var empty = zone.querySelector('.mvc-upload__empty');
    var done = zone.querySelector('.mvc-upload__done');
    if (img) { img.hidden = true; img.removeAttribute('src'); }
    if (empty) empty.hidden = false;
    if (done) done.remove();
    zone.classList.remove('is-filled');
  }

  function tick(zone) {
    if (zone.querySelector('.mvc-upload__done')) return;
    var t = document.createElement('span');
    t.className = 'mvc-upload__done';
    t.innerHTML = '<i class="ph ph-check" aria-hidden="true"></i>';
    zone.appendChild(t);
  }

  function showPreview(zone, file) {
    var img = zone.querySelector('.mvc-upload__preview');
    var empty = zone.querySelector('.mvc-upload__empty');
    zone.classList.add('is-filled');
    tick(zone);

    if (file.type === 'application/pdf') {
      // No thumbnail for a PDF - name the file instead, so the member can still
      // see that the right one is attached.
      if (img) img.hidden = true;
      if (empty) {
        empty.hidden = false;
        empty.innerHTML =
          '<i class="ph ph-file-pdf mvc-upload__icon"></i>' +
          '<div class="mvc-upload__name">' + escapeHtml(file.name) + '</div>' +
          '<div class="mvc-upload__hint">Tap to replace</div>';
      }
      return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
      if (img) { img.src = e.target.result; img.hidden = false; }
      if (empty) empty.hidden = true;
    };
    reader.readAsDataURL(file);
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-kyc-drop]'), function (zone) {
    var input = zone.querySelector('input[type=file]');
    zone.addEventListener('click', function () { input.click(); });
    zone.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
    });
    zone.setAttribute('tabindex', '0');
    zone.setAttribute('role', 'button');

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) { resetDrop(zone); return; }
      if (file.size > MAX_BYTES) {
        toast('Each file must be 10 MB or smaller.');
        input.value = '';
        resetDrop(zone);
        return;
      }
      showPreview(zone, file);
    });
  });

  /* --- Submit ----------------------------------------------------------- */
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    var front  = document.getElementById('kyc-doc-front').files[0];
    var selfie = document.getElementById('kyc-selfie').files[0];
    var back   = document.getElementById('kyc-doc-back').files[0];
    var needsBack = idType.value === 'national_id' || idType.value === 'drivers_license';

    if (!idType.value)          return toast('Choose which document you are sending.');
    if (!front)                 return toast('Upload the front of your document.');
    if (needsBack && !back)     return toast('Upload the back of your document.');
    if (!selfie)                return toast('Upload a selfie holding your document.');

    var fd = new FormData(form);
    fd.append('action', 'submit');
    // FormData carries an empty file part for a hidden, unset input; drop it so
    // the server sees a genuinely absent field rather than a zero-byte upload.
    if (!needsBack || !back) fd.delete('doc_back');

    submit.disabled = true;
    var loader = document.getElementById('loader');
    if (loader) loader.classList.remove('hidden');

    try {
      var r = await fetch('/api/backend/kyc.php', {
        method: 'POST',
        body: fd,
        credentials: 'include',
        headers: mvcWithCsrf(),
      });
      var data = await r.json();

      if (data.status === 'success') {
        toast(data.message || 'Submitted for review.', 'success');
        // Reload rather than patching the DOM: the page renders its state
        // server-side, and the form must now disappear.
        setTimeout(function () { window.location.reload(); }, 1200);
      } else {
        toast(data.message || 'Could not submit your documents.');
        submit.disabled = false;
      }
    } catch (err) {
      console.error('KYC submit failed', err);
      toast('Network error. Please try again.');
      submit.disabled = false;
    } finally {
      if (loader) {
        loader.classList.add('fade-out');
        setTimeout(function () { loader.classList.add('hidden'); }, 300);
      }
    }
  });
})();
