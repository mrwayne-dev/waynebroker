<?php
$page_title = 'About Maveren Capital | Weekly and monthly investment plans';
$page_description = 'About Maveren Capital. Invest a lump sum, choose weekly or monthly payouts, and get your principal back in full at maturity.';
$page_path = '/about';
include __DIR__ . '/_partials/head.php';
?>
<body class="mvc-redesign" data-hero="dark">

<?php include __DIR__ . '/_partials/navbar.php'; ?>

<!-- =================== HERO =================== -->
<section class="hero-x hero-x--compact">
  <div class="hero-x__wash" data-parallax="0.1" aria-hidden="true"></div>
  <div class="container">
    <div class="hero-x__inner">
      <span class="kicker">About</span>
      <h1 class="hero-x__title">We make wealth-building <em>boring</em>.</h1>
      <p class="hero-x__lead">
        A published rate, a fixed schedule, and a statement that reconciles.
        Nothing here should surprise you.
      </p>
      <div class="hero-x__cta">
        <a href="/register" class="btn btn--primary">Open an account</a>
        <a href="#story" class="btn btn--ghost">Read our story</a>
      </div>
    </div>
  </div>
</section>


<!-- =================== TIMELINE =================== -->
<section class="section section--warm" id="story">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">Our story</span>
      <h2>Built to close the gap.</h2>
      <p>
        Retail investors were offered savings accounts and a few index funds.
        Institutions had fixed income, fractional equity and structured products
        compounding quietly for decades. Maveren exists to close that gap.
      </p>
    </div>

    <div class="timeline" data-reveal>
      <div class="timeline__item">
        <span class="timeline__when">2020</span>
        <h3 class="timeline__title">A complaint we kept hearing</h3>
        <p class="timeline__body">
          The founding team &mdash; alumni of major US banks, fintech start-ups and
          asset managers &mdash; kept hearing the same question: why are the only
          options my bank offers a 0.5% savings account and a CD?
        </p>
      </div>
      <div class="timeline__item">
        <span class="timeline__when">2021</span>
        <h3 class="timeline__title">One published rate</h3>
        <p class="timeline__body">
          Rather than build a shelf of products, we built one: a fixed
          percentage per payout period, quoted in full before you commit, with
          principal returned at maturity.
        </p>
      </div>
      <div class="timeline__item">
        <span class="timeline__when">2023</span>
        <h3 class="timeline__title">Segregation and the ledger</h3>
        <p class="timeline__body">
          Client funds moved into segregated accounts, reconciled on a schedule,
          and every movement on the platform earned its own reference.
        </p>
      </div>
      <div class="timeline__item">
        <span class="timeline__when">Today</span>
        <h3 class="timeline__title">Three plans, one wallet</h3>
        <p class="timeline__body">
          Two weekly rhythms and one monthly, from $250 upward, with the same
          reporting whatever the size of the position.
        </p>
      </div>
    </div>
  </div>
</section>


<!-- =================== VALUES =================== -->
<section class="section">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">What we hold to</span>
      <h2>Transparency, suitability, simplicity.</h2>
    </div>

    <div class="bento">
      <article class="bento__tile" data-reveal>
        <span class="bento__icon"><i class="ph ph-vault" aria-hidden="true"></i></span>
        <h3 class="bento__title">Protection</h3>
        <p class="bento__body">Client funds held in segregated accounts, separate from company operating funds, reconciled on a regular schedule.</p>
      </article>
      <article class="bento__tile" data-reveal data-reveal-delay="80">
        <span class="bento__icon"><i class="ph ph-eye" aria-hidden="true"></i></span>
        <h3 class="bento__title">Values</h3>
        <p class="bento__body">No hidden tiers and no jargon-walled disclosures. Every plan shows rate, risk and lock-up before you commit a dollar.</p>
      </article>
      <article class="bento__tile" data-reveal data-reveal-delay="160">
        <span class="bento__icon"><i class="ph ph-compass" aria-hidden="true"></i></span>
        <h3 class="bento__title">Vision</h3>
        <p class="bento__body">Make institutional-grade investing the default: compounding in the background, statements exporting in one click.</p>
      </article>
    </div>
  </div>
</section>


<!-- =================== POLICY — two-column document =================== -->
<section class="section section--warm" id="terms-of-use">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">Policy</span>
      <h2>Your data is yours. Your money is yours.</h2>
    </div>

    <div class="split-row" data-reveal>
      <div>
        <h3 class="split-row__title">Privacy</h3>
        <p class="split-row__body">
          Privacy is a design principle here, not a page. We operate under
          recognised US data-protection, operational-resilience and
          record-keeping standards. Your information is stored with end-to-end
          encryption and processed only on the lawful bases set out in our
          Privacy Notice. We never sell identifiable personal data, and internal
          analytics run on de-identified datasets.
        </p>
      </div>
      <div>
        <h3 class="split-row__title">Terms of use</h3>
        <p class="split-row__body">
          Opening an account means agreeing to our suitability process, fee
          schedule and the risk disclosures shown before each allocation. You
          may hold positions across the weekly and monthly plans, subject to
          per-plan minimums and fixed terms.
        </p>
        <p class="split-row__body">
          Past performance does not guarantee future returns and your capital is
          at risk. Accounts must be used only by the registered individual;
          misuse may result in suspension and referral to the relevant
          authorities.
        </p>
      </div>
    </div>
  </div>
</section>


<?php include __DIR__ . '/_partials/footer.php'; ?>

<script src="<?= mvc_asset('/assets/js/main.js') ?>" defer></script>
</body>
</html>
