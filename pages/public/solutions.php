<?php
$page_title = 'Solutions | Tailored for every investor profile | Maveren Capital';
$page_description = 'Maveren Capital solutions for every kind of investor: savers, income builders, portfolio diversifiers, and institutional allocators.';
$page_path = '/solutions';
include __DIR__ . '/_partials/head.php';
?>
<body class="mvc-redesign" data-hero="dark">

<?php include __DIR__ . '/_partials/navbar.php'; ?>

<!-- =================== HERO =================== -->
<section class="hero-x hero-x--compact">
  <div class="hero-x__wash" data-parallax="0.1" aria-hidden="true"></div>
  <div class="container">
    <div class="hero-x__inner">
      <span class="kicker">Solutions</span>
      <h1 class="hero-x__title">Four ways in.<br>One <em>schedule</em>.</h1>
      <p class="hero-x__lead">
        Whether you are parking salary surplus, building income, or diversifying
        a portfolio, there is a Maveren path designed for it.
      </p>
      <div class="hero-x__cta">
        <a href="/register" class="btn btn--primary">Open an account</a>
        <a href="#profiles" class="btn btn--ghost">Find your profile</a>
      </div>
    </div>
  </div>
</section>


<!-- =================== BENTO — investor profiles =================== -->
<section class="section section--warm" id="profiles">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">Investor profiles</span>
      <h2>Find the starting point that sounds like you.</h2>
      <p>Four common situations, each mapping to a plan and a sensible next step.</p>
    </div>

    <div class="bento">
      <article class="bento__tile bento__tile--wide" data-reveal>
        <span class="bento__icon"><i class="ph ph-piggy-bank" aria-hidden="true"></i></span>
        <h3 class="bento__title">For everyday savers</h3>
        <p class="bento__body">
          Tired of a 0.5% high-street saver. Start with Weekly Core for a payout
          every seven days, then roll those payouts into a longer monthly
          position as the balance grows.
        </p>
        <div class="bento__shot">
          <picture>
            <source type="image/avif" srcset="/assets/images/product/app-balances.avif">
            <source type="image/webp" srcset="/assets/images/product/app-balances.webp">
            <img src="/assets/images/product/app-balances.webp" width="1120" height="251" loading="lazy"
                 alt="Wallet overview showing balance, earnings, invested capital and next payout.">
          </picture>
        </div>
      </article>

      <article class="bento__tile" data-reveal data-reveal-delay="80">
        <span class="bento__icon"><i class="ph ph-trend-up" aria-hidden="true"></i></span>
        <h3 class="bento__title">For income builders</h3>
        <p class="bento__body">
          You want distributions landing regularly. Weekly plans pay every seven
          days; the monthly plan pays a larger amount on the same date each month.
        </p>
      </article>

      <article class="bento__tile" data-reveal>
        <span class="bento__icon"><i class="ph ph-chart-pie-slice" aria-hidden="true"></i></span>
        <h3 class="bento__title">For portfolio diversifiers</h3>
        <p class="bento__body">
          You already hold a retirement account. Use Monthly Reserve as the
          anchor and a weekly position for capital you want moving more often.
        </p>
      </article>

      <article class="bento__tile bento__tile--wide" data-reveal data-reveal-delay="80">
        <span class="bento__icon"><i class="ph ph-buildings" aria-hidden="true"></i></span>
        <h3 class="bento__title">For institutional-style allocators</h3>
        <p class="bento__body">
          Higher minimums and longer horizons. Maveren Monthly Reserve runs for a
          full year at the strongest rate we publish, with performance reported
          quarterly and the same statement as every other member.
        </p>
      </article>
    </div>
  </div>
</section>


<!-- =================== PROCESS RAIL =================== -->
<section class="section">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">How it works</span>
      <h2>Three steps. No spreadsheets.</h2>
      <p>No quarterly statement buried three menus deep, either.</p>
    </div>

    <div class="rail rail--h" data-reveal>
      <div class="rail__step">
        <span class="rail__n">1</span>
        <h3 class="rail__title">Open your wallet</h3>
        <p class="rail__body">Three-minute sign-up, identity verified, and funds segregated from your first deposit.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">2</span>
        <h3 class="rail__title">Pick a plan</h3>
        <p class="rail__body">One position or several. Every plan shows its rate, risk band and lock-up before you commit a dollar.</p>
      </div>
      <div class="rail__step">
        <span class="rail__n">3</span>
        <h3 class="rail__title">Watch it run</h3>
        <p class="rail__body">Payouts land on schedule against an audit-grade ledger you can export in one click.</p>
      </div>
    </div>
  </div>
</section>


<!-- =================== CTA =================== -->
<section class="section section--warm">
  <div class="container">
    <div class="quote-xl" data-reveal>
      <p class="quote-xl__text">Start where you are. <em>From $250.</em></p>
      <p class="quote-xl__by" style="margin-bottom: var(--space-6);">
        Weekly Core from $250, Weekly Prime from $1,000, Monthly Reserve from
        $5,000. Every plan returns your principal in full at maturity.
      </p>
      <a href="/register" class="btn btn--primary">Open an account</a>
    </div>
  </div>
</section>


<?php include __DIR__ . '/_partials/footer.php'; ?>

<script src="<?= mvc_asset('/assets/js/main.js') ?>" defer></script>
</body>
</html>
