<?php
$page_title = 'Investment plans | Weekly and monthly payouts | Maveren Capital';
$page_description = 'Maveren Capital investment plans. Invest a lump sum, choose weekly or monthly payouts, and get your principal back in full at maturity.';
$page_path = '/plans';
include __DIR__ . '/_partials/head.php';

// Rates in the hero come from the same table the shelf below reads, so the
// headline figure and the plan card can never disagree.
$__plans = [];
try {
    require_once __DIR__ . '/../../config/database.php';
    $__plans = getPDO()->query(
        "SELECT title, cadence, roi_percent, min_amount
           FROM plans WHERE status = 'active'
          ORDER BY cadence DESC, min_amount ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('plans.php: plans lookup failed: ' . $e->getMessage());
}
?>
<body class="mvc-redesign" data-hero="dark">

<?php include __DIR__ . '/_partials/navbar.php'; ?>

<!-- =================== HERO — rates above the fold =================== -->
<section class="hero-x hero-x--compact">
  <div class="hero-x__wash" data-parallax="0.1" aria-hidden="true"></div>

  <div class="container">
    <div class="hero-x__inner">
      <span class="kicker">Investment plans</span>
      <h1 class="hero-x__title">Invest once.<br>Get paid <em>on schedule</em>.</h1>
      <p class="hero-x__lead">
        Put a lump sum to work, pick whether you want paying every week or every
        month, and receive your principal back in full at the end of the term.
      </p>
      <div class="hero-x__cta">
        <a href="/register" class="btn btn--primary">Open an account</a>
        <a href="#plans" class="btn btn--ghost">See the plans</a>
      </div>
    </div>

    <?php if ($__plans): ?>
      <div class="rate-strip">
        <?php foreach ($__plans as $p): ?>
          <div class="rate-strip__item">
            <span class="rate-strip__name"><?= htmlspecialchars($p['title']) ?></span>
            <span class="rate-strip__rate"><?= number_format((float) $p['roi_percent'], 2) ?>%</span>
            <span class="rate-strip__note">
              per <?= $p['cadence'] === 'monthly' ? 'month' : 'week' ?> &middot;
              from $<?= number_format((float) $p['min_amount']) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>


<!-- =================== PROCESS RAIL =================== -->
<section class="section section--warm" id="how">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">How it works</span>
      <h2>Four steps, then it runs itself.</h2>
      <p>
        Every plan quotes a fixed percentage per payout period. That percentage
        is what lands in your wallet — there is no headline rate hiding a
        smaller real one.
      </p>
    </div>

    <div class="rail rail--h" data-reveal>
      <div class="rail__step">
        <span class="rail__n">1</span>
        <h3 class="rail__title">Fund your wallet</h3>
        <p class="rail__body">Deposit into your Maveren wallet. The balance sits there until you choose to deploy it.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">2</span>
        <h3 class="rail__title">Choose your rhythm</h3>
        <p class="rail__body">Weekly plans pay every seven days. Monthly plans pay on the same date each month, at a higher rate.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">3</span>
        <h3 class="rail__title">Collect every period</h3>
        <p class="rail__body">Each payout is credited automatically and is withdrawable straight away. You never wait for maturity to see a return.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">4</span>
        <h3 class="rail__title">Principal returned</h3>
        <p class="rail__body">At the end of the term your original capital is released back in full, on top of every payout already made.</p>
      </div>
    </div>
  </div>
</section>


<?php include __DIR__ . '/_partials/plans-section.php'; ?>


<!-- =================== WORKED EXAMPLE =================== -->
<section class="section section--warm">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">A worked example</span>
      <h2>$5,000 in Monthly Reserve.</h2>
      <p>The same arithmetic the dashboard runs, written out.</p>
    </div>

    <div class="figures" data-reveal>
      <div class="figures__item">
        <span class="figures__value">$5,000</span>
        <span class="figures__label">Invested once</span>
      </div>
      <div class="figures__item">
        <span class="figures__value">$300</span>
        <span class="figures__label">Paid to your wallet each month</span>
      </div>
      <div class="figures__item">
        <span class="figures__value">$3,600</span>
        <span class="figures__label">Total payouts over 12 months</span>
      </div>
      <div class="figures__item">
        <span class="figures__value figures__value--accent">72%</span>
        <span class="figures__label">Total return, principal returned in full</span>
      </div>
    </div>

    <p class="figures__note">
      At 6.00% per month over a 12-month term. Capital is at risk and rates are
      not guaranteed.
    </p>
  </div>
</section>


<?php include __DIR__ . '/_partials/footer.php'; ?>

<script src="<?= mvc_asset('/assets/js/main.js') ?>" defer></script>
</body>
</html>
