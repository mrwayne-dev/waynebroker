/* =====================================================================
 * FILE: /assets/js/dashboard-chart.js
 * Allocation doughnut on the member dashboard.
 *
 * Was an inline <script> in dashboard.php, which meant its SHA-256 was
 * pinned in the CSP and every edit to it silently blocked the chart until
 * the hash was regenerated. That trap has already cost this project once.
 * External is covered by 'self' and needs no hash at all.
 * ===================================================================== */
(function () {
  'use strict';

  // Weekly / Monthly / idle wallet cash. Read from the stylesheet so the
  // chart cannot drift from the palette the rest of the page uses.
  function token(name, fallback) {
    var v = getComputedStyle(document.body).getPropertyValue(name).trim();
    return v || fallback;
  }

  async function render() {
    var canvas = document.getElementById('cardUsageChart');
    if (!canvas || typeof Chart === 'undefined') return;

    var colours = [
      token('--Primary', '#FF5B04'),
      token('--mvc-teal', '#075056'),
      token('--Gray', '#8A9DA5'),
    ];

    try {
      var res = await fetch('/api/backend/card_usage.php', { credentials: 'include' });
      var result = await res.json();
      if (!result.success) throw new Error(result.message || 'card_usage failed');

      var d = result.percentages || {};
      var labels = ['Weekly', 'Monthly', 'Wallet'];
      var values = [d.weekly || 0, d.monthly || 0, d.wallet || 0];

      new Chart(canvas, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{ data: values, backgroundColor: colours, borderWidth: 0, cutout: '70%' }],
        },
        options: {
          responsive: false,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
        },
      });

      // The legend is ours rather than Chart.js's, so it can be styled with
      // the same tokens as the rest of the panel and stay translatable -
      // canvas text is invisible to the server-side translator.
      var legend = document.querySelector('.mvc-chart__legend');
      if (!legend) return;
      legend.innerHTML = labels.map(function (label, i) {
        return '<li><span class="mvc-chart__dot" style="background:' + colours[i] + '"></span>' +
               '<span>' + label + '</span> <strong>' + values[i] + '%</strong></li>';
      }).join('');
    } catch (err) {
      console.error('Allocation chart failed to load:', err);
      // Leave the seeded legend in place rather than blanking the panel.
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', render);
  } else {
    render();
  }
})();
