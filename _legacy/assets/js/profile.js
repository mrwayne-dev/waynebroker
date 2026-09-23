/* =======================================================
   profile.js - Maveren Capital user profile
   Loads + updates personal details, password, and avatar.
   ======================================================= */

document.addEventListener('DOMContentLoaded', function () {
  const $ = (id) => document.getElementById(id);

  // ---- Load profile ----
  async function loadProfile() {
    const res = await fetchApi('/api/backend/profile.php', { action: 'get_profile' });
    if (res.status === 'success') {
      const d = res.data || {};
      if ($('pf-full-name')) $('pf-full-name').value = d.full_name || '';
      if ($('pf-email'))     $('pf-email').value     = d.email     || '';
      if ($('pf-phone'))     $('pf-phone').value     = d.phone     || '';
      if ($('pf-country'))   $('pf-country').value   = d.country   || '';
      if ($('pf-location'))  $('pf-location').value  = d.location  || '';
      if ($('pf-address'))   $('pf-address').value   = d.address   || '';
      if (d.profile_picture && $('avatar-preview')) $('avatar-preview').src = d.profile_picture;
    } else {
      showToast(res.message || 'Failed to load profile.', 'error');
    }
  }
  loadProfile();

  // ---- Save personal details ----
  $('profile-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('profile-save-btn');
    btn.disabled = true;
    const res = await fetchApi('/api/backend/profile.php', {
      action: 'update_profile',
      full_name: $('pf-full-name').value.trim(),
      email:     $('pf-email').value.trim(),
      phone:     $('pf-phone').value.trim(),
      country:   $('pf-country').value.trim(),
      location:  $('pf-location')?.value.trim() ?? '',
      address:   $('pf-address').value.trim(),
    });
    btn.disabled = false;
    if (res.status === 'success') {
      showToast('Profile updated successfully.', 'success');
      const nameEl = document.getElementById('topbar-username');
      if (nameEl && res.data?.full_name) nameEl.textContent = res.data.full_name;
    } else {
      showToast(res.message || 'Failed to update profile.', 'error');
    }
  });

  // ---- Change password ----
  const passwordForm = $('password-form');
  passwordForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('password-save-btn');
    btn.disabled = true;
    const res = await fetchApi('/api/backend/profile.php', {
      action: 'change_password',
      current_password: $('pf-current-password').value,
      new_password:     $('pf-new-password').value,
      confirm_password: $('pf-confirm-password').value,
    });
    btn.disabled = false;
    if (res.status === 'success') {
      showToast('Password updated successfully.', 'success');
      passwordForm.reset();
    } else {
      showToast(res.message || 'Failed to update password.', 'error');
    }
  });

  // ---- Avatar upload (multipart) ----
  const fileInput = $('avatar-input');
  const uploadBtn = $('avatar-upload');
  const preview   = $('avatar-preview');

  $('avatar-choose')?.addEventListener('click', () => fileInput?.click());

  fileInput?.addEventListener('change', () => {
    const file = fileInput.files?.[0];
    if (!file) { if (uploadBtn) uploadBtn.disabled = true; return; }
    // Must stay in step with MAX_AVATAR_BYTES in api/backend/upload_avatar.php
    // AND with upload_max_filesize in .htaccess / .user.ini - the PHP ini
    // limits sit under both checks and reject the request before either runs.
    if (file.size > 10 * 1024 * 1024) {
      showToast('Image must be 10 MB or smaller.', 'error');
      fileInput.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => { if (preview) preview.src = e.target.result; };
    reader.readAsDataURL(file);
    if (uploadBtn) uploadBtn.disabled = false;
  });

  $('avatar-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = fileInput?.files?.[0];
    if (!file) { showToast('Choose a photo first.', 'error'); return; }
    if (uploadBtn) uploadBtn.disabled = true;
    const loader = document.getElementById('loader');
    loader?.classList.remove('hidden');
    try {
      const fd = new FormData();
      fd.append('avatar', file);
      // multipart/form-data, so no Content-Type header is set by hand - the
      // browser must generate the boundary. The CSRF token rides in the header
      // rather than as a form field for the same reason.
      const r = await fetch('/api/backend/upload_avatar.php', {
        method: 'POST', body: fd, credentials: 'include',
        headers: mvcWithCsrf(),
      });
      const data = await r.json();
      if (data.status === 'success') {
        showToast('Profile photo updated.', 'success');
        const p = data.data?.profile_picture;
        if (p) {
          if (preview) preview.src = p;
          const topAv = document.getElementById('topbar-avatar');
          if (topAv) topAv.src = p;
        }
        if (fileInput) fileInput.value = '';
      } else {
        showToast(data.message || 'Upload failed.', 'error');
        if (uploadBtn) uploadBtn.disabled = false;
      }
    } catch (err) {
      console.error('Avatar upload error', err);
      showToast('Network error during upload.', 'error');
      if (uploadBtn) uploadBtn.disabled = false;
    } finally {
      loader?.classList.add('hidden');
    }
  });
});
