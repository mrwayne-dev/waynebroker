/**
 * ============================================================
 * Contact form - public marketing site
 *
 * The form used to be a native POST to /contact/submit, a path that matched
 * no rewrite rule, so every submission rendered the 404 page and the message
 * was silently lost. It posts to /api/public/contact.php with fetch +
 * FormData now (multipart, because of the optional attachment).
 *
 * Standalone and dependency-free: the public pages load main.js and this
 * file, and nothing else. showToast lives in api.js, which contact.php does
 * not load, so status is reported inline.
 * ============================================================
 */
(function () {
  'use strict';

  const form = document.getElementById('contact-form');
  if (!form) return;

  const submitBtn = document.getElementById('contact-submit');
  const statusEl  = document.getElementById('contact-status');
  const fileInput = document.getElementById('attachment');

  const MAX_BYTES = 5 * 1024 * 1024;

  // Local, deliberately. mvcWithCsrf() lives in api.js and contact.php does not
  // load it (see the header note above) - calling it here would throw a
  // ReferenceError and break the only contact route the site has.
  function csrfHeaders(extra) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const token = meta ? meta.getAttribute('content') || '' : '';
    return token ? Object.assign({}, extra, { 'X-CSRF-Token': token }) : extra;
  }

  function setStatus(message, kind) {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.style.color =
      kind === 'error'   ? 'var(--status-danger)'  :
      kind === 'success' ? 'var(--status-success)' :
                           'var(--color-ink-muted)';
  }

  // Check the size client-side too. Without this an oversized file is uploaded
  // in full before the server can reject it, which on a slow connection is a
  // long wait for a "too large" message.
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      const f = fileInput.files && fileInput.files[0];
      if (f && f.size > MAX_BYTES) {
        setStatus('That attachment is larger than 5 MB. Please choose a smaller file.', 'error');
        fileInput.value = '';
      } else {
        setStatus('', null);
      }
    });
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    // The form carries `novalidate` so these messages render inline rather
    // than as browser bubbles, which the design system cannot style.
    if (!form.checkValidity()) {
      const firstInvalid = form.querySelector(':invalid');
      if (firstInvalid) firstInvalid.focus();
      setStatus('Please fill in every required field with a valid value.', 'error');
      return;
    }

    const f = fileInput && fileInput.files && fileInput.files[0];
    if (f && f.size > MAX_BYTES) {
      setStatus('That attachment is larger than 5 MB.', 'error');
      return;
    }

    const originalLabel = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending...';
    }
    setStatus('Sending your message...', null);

    try {
      const res = await fetch('/api/public/contact.php', {
        method: 'POST',
        body: new FormData(form),
        credentials: 'include',   // the token is session-bound, so the cookie must go too
        headers: csrfHeaders({ 'X-Requested-With': 'XMLHttpRequest' }),
      });

      // A 500 from PHP can arrive as HTML, so don't assume the body parses.
      let data;
      try {
        data = await res.json();
      } catch (parseErr) {
        throw new Error('Unexpected response from the server.');
      }

      if (data.status === 'success') {
        form.reset();
        setStatus(data.message || 'Thanks - your message is on its way.', 'success');
      } else {
        setStatus(data.message || 'Something went wrong. Please try again.', 'error');
      }
    } catch (err) {
      setStatus('We could not reach the server. Please check your connection and try again.', 'error');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalLabel;
      }
    }
  });
})();
