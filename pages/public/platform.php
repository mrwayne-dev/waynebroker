<?php
$page_title = 'Platform | One wallet, every way to grow it | Maveren Capital';
$page_description = 'One investment product, one wallet, audit-grade reporting. The Maveren Capital platform is built on trust and secured by design.';
$page_path = '/platform';
include __DIR__ . '/_partials/head.php';
?>
<body class="mvc-redesign" data-hero="dark">

<?php include __DIR__ . '/_partials/navbar.php'; ?>

<!-- =================== HERO =================== -->
<section class="hero-x hero-x--compact">
  <div class="hero-x__wash" data-parallax="0.1" aria-hidden="true"></div>
  <div class="container">
    <div class="hero-x__inner">
      <span class="kicker">The platform</span>
      <h1 class="hero-x__title">One product,<br>done <em>properly</em>.</h1>
      <p class="hero-x__lead">
        One wallet, one statement, one schedule. Fund once, choose your payout
        rhythm, and let it run.
      </p>
      <div class="hero-x__cta">
        <a href="/register" class="btn btn--primary">Open an account</a>
        <a href="/plans" class="btn btn--ghost">See the plans</a>
      </div>
    </div>
  </div>
</section>


<!-- =================== SPLIT ROWS — the two rhythms =================== -->
<section class="section section--warm" id="products">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">How it works</span>
      <h2>There is one thing to decide.</h2>
      <p>
        We deliberately do not run a sprawling product shelf. Choose how often
        you want to be paid; everything after that is handled for you.
      </p>
    </div>

    <div class="split-row" data-reveal>
      <div>
        <span class="kicker">Weekly</span>
        <h3 class="split-row__title">A payout every seven days.</h3>
        <p class="split-row__body">
          The shortest way to turn capital into regular income, and the easiest
          to start with. Two weekly plans, over thirteen weeks or twenty-six.
        </p>
        <ul class="split-row__list">
          <li><i class="ph ph-check-circle" aria-hidden="true"></i> From $250, the lowest entry on the shelf</li>
          <li><i class="ph ph-check-circle" aria-hidden="true"></i> Credited every Friday, withdrawable immediately</li>
          <li><i class="ph ph-check-circle" aria-hidden="true"></i> Principal returned in full at maturity</li>
        </ul>
      </div>
      <div class="split-row__media">
        <div class="device">
          <div class="device__bar" aria-hidden="true">
            <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
            <span class="device__url">/dashboard.transactions</span>
          </div>
          <picture>
            <source type="image/avif" srcset="/assets/images/product/app-transactions.avif">
            <source type="image/webp" srcset="/assets/images/product/app-transactions.webp">
            <img src="/assets/images/product/app-transactions.webp" width="1120" height="524" loading="lazy"
                 alt="Transaction history showing weekly payouts credited on schedule, each with its own reference.">
          </picture>
        </div>
      </div>
    </div>

    <div class="split-row" data-reveal>
      <div>
        <span class="kicker">Monthly</span>
        <h3 class="split-row__title">A larger payout, same date each month.</h3>
        <p class="split-row__body">
          A full year of monthly income at the strongest rate we publish, in
          exchange for the longer commitment. Simple to forecast and easy to
          plan a year around.
        </p>
        <ul class="split-row__list">
          <li><i class="ph ph-check-circle" aria-hidden="true"></i> Our highest published rate per period</li>
          <li><i class="ph ph-check-circle" aria-hidden="true"></i> Lands on the same calendar day every month</li>
          <li><i class="ph ph-check-circle" aria-hidden="true"></i> Performance reported quarterly</li>
        </ul>
      </div>
      <div class="split-row__media">
        <div class="device">
          <div class="device__bar" aria-hidden="true">
            <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
            <span class="device__url">/dashboard.invest</span>
          </div>
          <picture>
            <source type="image/avif" srcset="/assets/images/product/app-invest.avif">
            <source type="image/webp" srcset="/assets/images/product/app-invest.webp">
            <img src="/assets/images/product/app-invest.webp" width="1120" height="293" loading="lazy"
                 alt="Invest page showing capital invested, return to date and the next payout date.">
          </picture>
        </div>
      </div>
    </div>
  </div>
</section>


<?php
$plans_eyebrow = 'The catalogue';
$plans_heading = 'Every plan we currently offer.';
include __DIR__ . '/_partials/plans-section.php';
?>


<!-- =================== TRUST ARCHITECTURE =================== -->
<section class="section section--warm">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">Trust architecture</span>
      <h2>Four layers under every product.</h2>
      <p>Each one audited, logged, and written in plain English.</p>
    </div>

    <div class="rail rail--h" data-reveal>
      <div class="rail__step">
        <span class="rail__n">1</span>
        <h3 class="rail__title">Safeguarding &amp; segregation</h3>
        <p class="rail__body">Client funds sit in segregated accounts, never co-mingled with company operating funds, and are reconciled on a regular schedule.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">2</span>
        <h3 class="rail__title">Suitability before allocation</h3>
        <p class="rail__body">Every plan surfaces its rate, risk band, lock-up and worst case before you commit a dollar. No hidden tiers and no jargon-walled fine print.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">3</span>
        <h3 class="rail__title">Audit-grade ledger</h3>
        <p class="rail__body">Every transaction carries a unique reference, and every reference reconciles. Six months of activity exports in one click.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">4</span>
        <h3 class="rail__title">Compliance beyond regulation</h3>
        <p class="rail__body">Data-protection controls, anti-money-laundering checks and internal safeguarding rules, with quarterly security reviews.</p>
      </div>
    </div>
  </div>
</section>


<!-- =================== CTA =================== -->
<section class="section">
  <div class="container">
    <div class="quote-xl" data-reveal>
      <p class="quote-xl__text">
        Institutional terms at <em>retail minimums</em>.
      </p>
      <p class="quote-xl__by" style="margin-bottom: var(--space-6);">
        Three-minute sign-up. Segregated from your first deposit.
      </p>
      <a href="/register" class="btn btn--primary">Open an account</a>
    </div>
  </div>
</section>


<?php include __DIR__ . '/_partials/footer.php'; ?>

<script src="<?= mvc_asset('/assets/js/main.js') ?>" defer></script>
</body>
</html>
