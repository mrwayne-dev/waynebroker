<?php
/**
 * Public "plans we offer" section (live from DB).
 *
 * Usage:  <?php include __DIR__ . '/_partials/plans-section.php'; ?>
 *
 * Optional before including:
 *   $plan_cadence  = 'weekly' | 'monthly'   (omit to show both)
 *   $plans_heading / $plans_eyebrow         (override the copy)
 *
 * Renders nothing on any DB/query error so a marketing page never breaks.
 */

require_once __DIR__ . '/../../../config/database.php';

$plan_cadence = $plan_cadence ?? '';
if ($plan_cadence !== '' && !in_array($plan_cadence, ['weekly', 'monthly'], true)) {
    $plan_cadence = '';
}

if (!function_exists('mvc_fmt_term')) {
    function mvc_fmt_term($days) {
        $days = (int) $days;
        if ($days <= 0)        return 'Open-ended';
        if ($days % 365 === 0) { $y = $days / 365; return $y . ' year'  . ($y > 1 ? 's' : ''); }
        if ($days % 30  === 0) { $m = $days / 30;  return $m . ' month' . ($m > 1 ? 's' : ''); }
        if ($days % 7   === 0) { $w = $days / 7;   return $w . ' week'  . ($w > 1 ? 's' : ''); }
        return $days . ' days';
    }
}
if (!function_exists('mvc_money')) {
    function mvc_money($n) { return '$' . number_format((float) $n, 0); }
}
if (!function_exists('mvc_pct')) {
    function mvc_pct($n) {
        $s = rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        return $s . '%';
    }
}
// One payout period, in days. Mirrors api/backend/invest.php.
if (!function_exists('mvc_cadence_days')) {
    function mvc_cadence_days(string $cadence): int {
        return $cadence === 'monthly' ? 30 : 7;
    }
}

$__cards = [];

try {
    $__pdo = getPDO();

    if ($plan_cadence !== '') {
        $__stmt = $__pdo->prepare("SELECT title, cadence, roi_percent, duration_days, min_amount,
                                          max_amount, risk, summary, description, icon
                                   FROM plans WHERE status = 'active' AND cadence = ?
                                   ORDER BY min_amount ASC");
        $__stmt->execute([$plan_cadence]);
    } else {
        $__stmt = $__pdo->query("SELECT title, cadence, roi_percent, duration_days, min_amount,
                                        max_amount, risk, summary, description, icon
                                 FROM plans WHERE status = 'active'
                                 ORDER BY cadence DESC, min_amount ASC");
    }

    foreach ($__stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cadence = $r['cadence'];
        $payouts = (int) floor((int) $r['duration_days'] / mvc_cadence_days($cadence));
        $roi     = (float) $r['roi_percent'];

        $__cards[] = [
            'tier'     => ucfirst($cadence),
            'icon'     => $r['icon'] ?: 'ph-chart-line-up',
            // Headline is the per-period rate - that is what the member receives.
            'roi'      => mvc_pct($roi),
            'roi_note' => 'per ' . ($cadence === 'monthly' ? 'month' : 'week'),
            'name'     => $r['title'],
            'meta'     => [
                'Term'    => mvc_fmt_term($r['duration_days']),
                'Payouts' => $payouts . ' x ' . mvc_pct($roi),
                'Minimum' => mvc_money($r['min_amount']),
                'Risk'    => $r['risk'],
            ],
            'total'    => mvc_pct(round($roi * $payouts, 2)) . ' total return',
            'summary'  => $r['summary'] ?: $r['description'],
        ];
    }
} catch (Throwable $e) {
    error_log('plans-section: ' . $e->getMessage());
    return; // never break the marketing page
}

if (empty($__cards)) return;

$__eyebrow = $plans_eyebrow ?? 'Investment plans';
$__title   = $plans_heading ?? 'Pick your payout rhythm.';
?>
<section class="section section--warm" id="plans">
  <div class="container">
    <div class="lede lede--center" data-reveal>
      <span class="kicker"><?= htmlspecialchars($__eyebrow) ?></span>
      <h2><?= htmlspecialchars($__title) ?></h2>
      <p>
        Invest once. Every plan pays a fixed percentage of your capital back to
        your wallet on a set rhythm, and returns your principal in full at the
        end of the term.
      </p>
    </div>

    <div class="grid-3">
      <?php foreach ($__cards as $i => $c): ?>
        <article class="plan-card" data-reveal data-reveal-delay="<?= $i * 80 ?>">
          <span class="plan-card__tier">
            <i class="ph <?= htmlspecialchars($c['icon']) ?>"></i>
            <?= htmlspecialchars($c['tier']) ?>
          </span>
          <div class="plan-card__roi">
            <?= htmlspecialchars($c['roi']) ?>
            <span class="plan-card__roi-note"><?= htmlspecialchars($c['roi_note']) ?></span>
          </div>
          <div class="plan-card__name"><?= htmlspecialchars($c['name']) ?></div>
          <ul class="plan-card__meta">
            <?php foreach ($c['meta'] as $k => $v): if ($v === '' || $v === null) continue; ?>
              <li><span><?= htmlspecialchars($k) ?></span><strong><?= htmlspecialchars($v) ?></strong></li>
            <?php endforeach; ?>
          </ul>
          <div class="plan-card__total"><?= htmlspecialchars($c['total']) ?></div>
          <?php if (!empty($c['summary'])): ?>
            <p class="plan-card__summary"><?= htmlspecialchars($c['summary']) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
unset($__cards, $__pdo, $__stmt, $__eyebrow, $__title, $r, $c, $plan_cadence, $plans_heading, $plans_eyebrow);
?>
