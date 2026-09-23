/* ============================================================
 * Maveren Capital - ADMIN SETTINGS
 * Platform limits on /admin.settings.
 *
 * Every field is one row of the settings key/value table, so adding
 * another limit means adding an input whose id is the setting key and
 * listing it in KEYS below. Nothing else here needs to change.
 * ============================================================ */
(function () {
  'use strict';

  const $form = $('#settings-form');
  if (!$form.length) return;

  const KEYS = [
    'withdrawal_min_amount',
    'withdrawal_max_amount',
    'deposit_min_amount',
    'deposit_max_amount',
  ];

  const LABELS = {
    withdrawal_min_amount: 'Minimum withdrawal',
    withdrawal_max_amount: 'Maximum withdrawal',
    deposit_min_amount: 'Minimum deposit',
    deposit_max_amount: 'Maximum deposit',
  };

  const $error = $('#settings-error');
  const $save  = $('#save-settings-btn');

  function money(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  // Describes the CURRENT saved state, so an admin can see what is in force
  // without reading it back out of an input they may already have edited.
  function showCurrent(key, value) {
    const n = Number(value || 0);
    $('[data-current="' + key + '"]').text(n > 0 ? 'Currently ' + money(n) : 'No limit');
  }

  async function load() {
    const res = await fetchApi('/api/admin/settings.php', { action: 'get' });
    if (res.status !== 'success') {
      showToast(res.message || 'Could not load settings.', 'error');
      return;
    }
    KEYS.forEach(function (k) {
      const n = Number(res.data[k] || 0);
      $('#' + k).val(n.toFixed(2));
      showCurrent(k, n);
    });
  }

  $form.on('submit', async function (e) {
    e.preventDefault();
    $error.text('');

    // Validated here as well as on the server. The server is the authority;
    // this is so a typo is answered immediately rather than after a round trip.
    const payload = { action: 'update' };
    for (const k of KEYS) {
      const raw = String($('#' + k).val() ?? '').trim();
      if (raw === '' || isNaN(Number(raw))) {
        $error.text(LABELS[k] + ': enter an amount as a number.');
        return;
      }
      if (Number(raw) < 0) {
        $error.text(LABELS[k] + ' cannot be negative.');
        return;
      }
      payload[k] = Number(raw);
    }

    // A minimum above a maximum would refuse every transaction of that kind
    // while each field looked individually fine.
    const pairs = [
      ['withdrawal', 'withdrawal_min_amount', 'withdrawal_max_amount'],
      ['deposit', 'deposit_min_amount', 'deposit_max_amount'],
    ];
    for (const [what, minKey, maxKey] of pairs) {
      if (payload[maxKey] > 0 && payload[minKey] > payload[maxKey]) {
        $error.text('The minimum ' + what + ' cannot be above the maximum. Nothing would be allowed through.');
        return;
      }
    }

    $save.prop('disabled', true);
    try {
      const res = await fetchApi('/api/admin/settings.php', payload);
      if (res.status === 'success') {
        showToast(res.message, 'success');
        KEYS.forEach(function (k) {
          const n = Number(res.data[k] || 0);
          $('#' + k).val(n.toFixed(2));
          showCurrent(k, n);
        });
      } else {
        $error.text(res.message || 'Could not save the settings.');
        showToast(res.message || 'Could not save the settings.', 'error');
      }
    } finally {
      $save.prop('disabled', false);
    }
  });

  $(load);
})();
