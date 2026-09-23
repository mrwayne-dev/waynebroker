<?php
$page_title = 'Build Wealth on Autopilot | Maveren Capital';
$page_description = 'Invest a lump sum with Maveren Capital, choose weekly or monthly payouts, and get your principal back in full at maturity.';
$page_path = '/';
include __DIR__ . '/_partials/head.php';

// The comparison table below is built from the live `plans` table rather than
// hardcoded, so the marketing site cannot drift from what a member is actually
// offered in the dashboard. A DB failure must not take the home page down, so
// the section simply does not render.
$__plans = [];
try {
    require_once __DIR__ . '/../../config/database.php';
    $__plans = getPDO()->query(
        "SELECT title, cadence, roi_percent, duration_days, min_amount, risk
           FROM plans WHERE status = 'active'
          ORDER BY cadence DESC, min_amount ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('index.php: plans lookup failed: ' . $e->getMessage());
}
$__periods = static fn(array $p): int =>
    (int) floor((int) $p['duration_days'] / ($p['cadence'] === 'monthly' ? 30 : 7));
?>
<body class="mvc-redesign" data-hero="dark">

<?php include __DIR__ . '/_partials/navbar.php'; ?>

<!-- =================== HERO — product reveal =================== -->
<section class="hero-x" id="hero">
  <div class="hero-x__wash" data-parallax="0.12" aria-hidden="true"></div>

  <?php // The logo's mountain motif, drawn once at scale and almost invisible.
        // Ties the page to the mark without repeating the logo down the page. ?>
  <div class="hero-x__motif" data-parallax="0.05" aria-hidden="true">
    <svg viewBox="0 0 100 60" fill="currentColor" style="color: var(--color-accent);">
      <path d="M32 6 L50 38 L68 6 L82 6 L50 56 L18 6 Z"/>
      <path d="M20 33 L30 51 L10 51 Z"/>
      <path d="M80 33 L90 51 L70 51 Z"/>
    </svg>
  </div>

  <div class="container">
    <div class="hero-x__inner">
      <a class="pill" href="/plans">
        <span class="pill__tag">New</span>
        Three plans. One wallet. Payouts you can diary.
      </a>

      <h1 class="hero-x__title">Put your capital<br>on a <em>schedule</em>.</h1>

      <p class="hero-x__lead">
        Invest a lump sum, choose whether you want paying every week or every
        month, and watch it land. Your principal comes back in full at the end
        of the term.
      </p>

      <div class="hero-x__cta">
        <a href="/register" class="btn btn--primary">Open an account</a>
        <a href="/platform" class="btn btn--ghost">See how it works</a>
      </div>

      <p class="hero-x__trust">
        <span><i class="ph ph-vault" aria-hidden="true"></i> Segregated client funds</span>
        <span><i class="ph ph-receipt" aria-hidden="true"></i> Every payout referenced</span>
        <span><i class="ph ph-clock-countdown" aria-hidden="true"></i> From $250</span>
      </p>
    </div>

    <?php // The real member dashboard, cut by the section edge. This is the
          // most credible asset the product has and it replaced a 242 KB stock
          // photograph of a skyscraper. ?>
    <div class="hero-x__shot" data-reveal>
      <div class="device device--bleed">
        <div class="device__bar" aria-hidden="true">
          <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
          <span class="device__url">maverencapital.com/dashboard</span>
        </div>
        <picture>
          <source type="image/avif" srcset="/assets/images/product/app-dashboard.avif">
          <source type="image/webp" srcset="/assets/images/product/app-dashboard.webp">
          <img src="/assets/images/product/app-dashboard.webp" width="1400" height="612"
               alt="The Maveren Capital member dashboard, showing wallet balance, total earnings, amount invested and the date of the next payout."
               loading="eager" fetchpriority="high">
        </picture>
      </div>
    </div>
  </div>
</section>


<!-- =================== TRUST STRIP =================== -->
<section class="section" style="padding-top: var(--space-15); padding-bottom: var(--space-8);">
  <div class="container">
    <div class="marquee" aria-hidden="true">
      <div class="marquee__track">
        <span class="marquee__item"><i class="ph ph-vault"></i> Segregated client accounts</span>
        <span class="marquee__item"><i class="ph ph-lock-key"></i> AES-256 encryption</span>
        <span class="marquee__item"><i class="ph ph-scales"></i> Reconciled on a schedule</span>
        <span class="marquee__item"><i class="ph ph-shield-check"></i> Multi-factor authentication</span>
        <span class="marquee__item"><i class="ph ph-file-text"></i> Named Data Protection Officer</span>
        <span class="marquee__item"><i class="ph ph-hand-coins"></i> Principal returned in full</span>
      </div>
      <div class="marquee__track">
        <span class="marquee__item"><i class="ph ph-vault"></i> Segregated client accounts</span>
        <span class="marquee__item"><i class="ph ph-lock-key"></i> AES-256 encryption</span>
        <span class="marquee__item"><i class="ph ph-scales"></i> Reconciled on a schedule</span>
        <span class="marquee__item"><i class="ph ph-shield-check"></i> Multi-factor authentication</span>
        <span class="marquee__item"><i class="ph ph-file-text"></i> Named Data Protection Officer</span>
        <span class="marquee__item"><i class="ph ph-hand-coins"></i> Principal returned in full</span>
      </div>
    </div>
  </div>
</section>


<!-- =================== STICKY SPLIT — how the money moves =================== -->
<section class="section section--warm" id="smarter-investing">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">The platform</span>
      <h2>Three steps, then it runs itself.</h2>
      <p>
        There is no portfolio to manage and no market to time. You fund a
        wallet, pick a rhythm, and the schedule does the rest.
      </p>
    </div>

    <div class="sticky-split" data-sticky-split>
      <div class="sticky-split__steps">
        <div class="sticky-step" data-step="0">
          <span class="sticky-step__n">01</span>
          <h3 class="sticky-step__title">Fund your wallet</h3>
          <p class="sticky-step__body">
            Deposit in Bitcoin, USDT or Ether. Every transfer is confirmed
            on-chain and lands in one wallet with a reference you can quote.
          </p>
        </div>
        <div class="sticky-step" data-step="1">
          <span class="sticky-step__n">02</span>
          <h3 class="sticky-step__title">Choose your rhythm</h3>
          <p class="sticky-step__body">
            Weekly or monthly. The rate is fixed per payout period and shown in
            full before you commit — no headline figure hiding a smaller real one.
          </p>
        </div>
        <div class="sticky-step" data-step="2">
          <span class="sticky-step__n">03</span>
          <h3 class="sticky-step__title">Get paid on schedule</h3>
          <p class="sticky-step__body">
            Payouts are credited automatically and are withdrawable the moment
            they land. Your principal returns in full on the maturity date.
          </p>
        </div>
      </div>

      <div class="sticky-split__aside">
        <div style="position: relative;">
          <div data-shot>
            <div class="device">
              <div class="device__bar" aria-hidden="true">
                <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
                <span class="device__url">/dashboard.wallet</span>
              </div>
              <picture>
                <source type="image/avif" srcset="/assets/images/product/app-wallet.avif">
                <source type="image/webp" srcset="/assets/images/product/app-wallet.webp">
                <img src="/assets/images/product/app-wallet.webp" width="1120" height="524" loading="lazy"
                     alt="The wallet page, showing the balance and the published deposit addresses.">
              </picture>
            </div>
          </div>
          <div data-shot>
            <div class="device">
              <div class="device__bar" aria-hidden="true">
                <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
                <span class="device__url">/dashboard.invest</span>
              </div>
              <picture>
                <source type="image/avif" srcset="/assets/images/product/app-invest.avif">
                <source type="image/webp" srcset="/assets/images/product/app-invest.webp">
                <img src="/assets/images/product/app-invest.webp" width="1120" height="293" loading="lazy"
                     alt="The invest page, showing capital invested, return earned to date and the next payout date.">
              </picture>
            </div>
          </div>
          <div data-shot>
            <div class="device">
              <div class="device__bar" aria-hidden="true">
                <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
                <span class="device__url">/dashboard.transactions</span>
              </div>
              <picture>
                <source type="image/avif" srcset="/assets/images/product/app-transactions.avif">
                <source type="image/webp" srcset="/assets/images/product/app-transactions.webp">
                <img src="/assets/images/product/app-transactions.webp" width="1120" height="524" loading="lazy"
                     alt="The transactions page, listing every deposit, payout and withdrawal with its reference.">
              </picture>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- =================== BENTO — one wallet =================== -->
<section class="section">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">One account</span>
      <h2>One wallet. Every way to grow it.</h2>
      <p>
        Deposits, positions, payouts and withdrawals live in one place, on one
        statement, with one running balance.
      </p>
    </div>

    <div class="bento">
      <article class="bento__tile bento__tile--wide" data-reveal data-reveal-delay="0">
        <span class="bento__icon"><i class="ph ph-wallet" aria-hidden="true"></i></span>
        <h3 class="bento__title">Everything on one balance</h3>
        <p class="bento__body">
          No sub-accounts to reconcile and no transfers to chase. Fund once and
          every position draws from the same wallet.
        </p>
        <div class="bento__shot">
          <picture>
            <source type="image/avif" srcset="/assets/images/product/app-balances.avif">
            <source type="image/webp" srcset="/assets/images/product/app-balances.webp">
            <img src="/assets/images/product/app-balances.webp" width="1120" height="251" loading="lazy"
                 alt="Wallet overview showing main balance, total earnings, amount invested and next payout.">
          </picture>
        </div>
      </article>

      <article class="bento__tile" data-reveal data-reveal-delay="80">
        <span class="bento__icon"><i class="ph ph-chart-donut" aria-hidden="true"></i></span>
        <h3 class="bento__title">See the split</h3>
        <p class="bento__body">
          Weekly, monthly and uncommitted cash, as a share of everything you
          hold — updated as payouts land.
        </p>
        <div class="bento__shot">
          <picture>
            <source type="image/avif" srcset="/assets/images/product/app-allocation.avif">
            <source type="image/webp" srcset="/assets/images/product/app-allocation.webp">
            <img src="/assets/images/product/app-allocation.webp" width="640" height="310" loading="lazy"
                 alt="Allocation chart splitting the portfolio across weekly plans, monthly plans and wallet cash.">
          </picture>
        </div>
      </article>

      <article class="bento__tile" data-reveal data-reveal-delay="0">
        <span class="bento__icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></span>
        <h3 class="bento__title">Automated payouts</h3>
        <p class="bento__body">
          Credited on the same rhythm every period, without you opening the app.
        </p>
      </article>

      <article class="bento__tile" data-reveal data-reveal-delay="80">
        <span class="bento__icon"><i class="ph ph-hand-coins" aria-hidden="true"></i></span>
        <h3 class="bento__title">Principal returned</h3>
        <p class="bento__body">
          In full, on the maturity date, back to the same wallet it left.
        </p>
      </article>

      <article class="bento__tile" data-reveal data-reveal-delay="160">
        <span class="bento__icon"><i class="ph ph-vault" aria-hidden="true"></i></span>
        <h3 class="bento__title">Held separately</h3>
        <p class="bento__body">
          Client funds sit in segregated accounts, never mixed with company
          operating money.
        </p>
      </article>
    </div>
  </div>
</section>


<?php if ($__plans): ?>
<!-- =================== COMPARE — the three plans, from the database =================== -->
<section class="section section--warm">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">The shelf</span>
      <h2>Two rhythms. Three plans.</h2>
      <p>
        Every plan quotes a fixed percentage per payout period. That percentage
        is what lands in your wallet.
      </p>
    </div>

    <div class="compare-wrap" data-reveal>
      <table class="compare">
        <caption class="visually-hidden">Maveren Capital plans compared by payout rhythm, rate, term and minimum</caption>
        <thead>
          <tr>
            <th scope="col">Plan</th>
            <?php foreach ($__plans as $i => $p): ?>
              <th scope="col"<?= $i === 1 ? ' class="is-featured"' : '' ?>><?= htmlspecialchars($p['title']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row">Payout rhythm</th>
            <?php foreach ($__plans as $i => $p): ?>
              <td<?= $i === 1 ? ' class="is-featured"' : '' ?>><?= $p['cadence'] === 'monthly' ? 'Every month' : 'Every week' ?></td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <th scope="row">Rate per payout</th>
            <?php foreach ($__plans as $i => $p): ?>
              <td class="is-figure<?= $i === 1 ? ' is-featured' : '' ?>"><?= number_format((float) $p['roi_percent'], 2) ?>%</td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <th scope="row">Number of payouts</th>
            <?php foreach ($__plans as $i => $p): ?>
              <td class="is-figure<?= $i === 1 ? ' is-featured' : '' ?>"><?= $__periods($p) ?></td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <th scope="row">Term</th>
            <?php foreach ($__plans as $i => $p): ?>
              <td<?= $i === 1 ? ' class="is-featured"' : '' ?>><?= (int) $p['duration_days'] ?> days</td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <th scope="row">Minimum</th>
            <?php foreach ($__plans as $i => $p): ?>
              <td class="is-figure<?= $i === 1 ? ' is-featured' : '' ?>">$<?= number_format((float) $p['min_amount']) ?></td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <th scope="row">Risk band</th>
            <?php foreach ($__plans as $i => $p): ?>
              <td<?= $i === 1 ? ' class="is-featured"' : '' ?>><?= htmlspecialchars($p['risk']) ?></td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>

    <p style="margin-top: var(--space-6);">
      <a href="/plans" class="btn btn--primary">Compare every plan</a>
    </p>
  </div>
</section>
<?php endif; ?>


<!-- =================== PULL QUOTE — the vision =================== -->
<section class="section" id="vision">
  <div class="container">
    <div class="quote-xl" data-reveal>
      <p class="quote-xl__mark" aria-hidden="true">&ldquo;</p>
      <p class="quote-xl__text">
        For decades the best yields, the best terms and the cleanest reporting
        were reserved for people with seven-figure balances. We publish
        <em>one rate</em> and one schedule, and they are the same from $250
        upwards.
      </p>
      <p class="quote-xl__by">Why Maveren Capital exists</p>
    </div>
  </div>
</section>


<!-- =================== DATA BAND =================== -->
<section class="band">
  <div class="container">
    <div class="lede" style="margin-bottom: var(--space-15);" data-reveal>
      <span class="kicker">Our numbers</span>
      <h2 style="color: var(--color-ink-white);">Built for real capital.</h2>
      <p style="color: rgba(228,238,240,.7);">
        What members have built on the platform, and the record behind it.
      </p>
    </div>

    <div class="band__grid">
      <div class="band__item" data-reveal data-reveal-delay="0">
        <p class="band__figure"><span data-count-to="12000" data-count-suffix="+">12,000+</span></p>
        <p class="band__label">Investors actively allocating</p>
      </div>
      <div class="band__item" data-reveal data-reveal-delay="90">
        <p class="band__figure"><span data-count-to="6800" data-count-suffix="+">6,800+</span></p>
        <p class="band__label">Payouts settled on time</p>
      </div>
      <div class="band__item" data-reveal data-reveal-delay="180">
        <p class="band__figure"><span data-count-to="320">320</span></p>
        <p class="band__label">Days of clean audit trail</p>
      </div>
      <div class="band__item" data-reveal data-reveal-delay="270">
        <p class="band__figure"><em>$250</em></p>
        <p class="band__label">Minimum to open a position</p>
      </div>
    </div>
  </div>
</section>


<!-- =================== TABS — why Maveren =================== -->
<section class="section">
  <div class="container">
    <div class="lede" data-reveal>
      <span class="kicker">Why Maveren</span>
      <h2>No reinvention. Just less friction.</h2>
      <p>
        We remove the markup, the fragmentation and the fine print, and return
        the difference to the people putting in the capital.
      </p>
    </div>

    <?php // With JavaScript off no panel is hidden, so all four read as a
          // stacked list. The tab behaviour is added by motion.js on top. ?>
    <div data-tabs>
      <div class="tabs__list" role="tablist" aria-label="Why Maveren Capital">
        <button class="tabs__btn" role="tab" id="tab-pricing" aria-controls="panel-pricing" type="button">Honest pricing</button>
        <button class="tabs__btn" role="tab" id="tab-ledger"  aria-controls="panel-ledger"  type="button">Audit-grade ledger</button>
        <button class="tabs__btn" role="tab" id="tab-guard"   aria-controls="panel-guard"   type="button">Safeguarding</button>
        <button class="tabs__btn" role="tab" id="tab-access"  aria-controls="panel-access"  type="button">Accessible minimums</button>
      </div>

      <div class="tabs__panel" role="tabpanel" id="panel-pricing" aria-labelledby="tab-pricing">
        <div class="split-row">
          <div>
            <h3 class="split-row__title">The rate you read is the rate you get.</h3>
            <p class="split-row__body">
              One figure, quoted per payout period, shown before you commit.
              No spread games, no hidden margin, and no "headline rate"
              footnote you discover at maturity.
            </p>
            <ul class="split-row__list">
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Fixed percentage per period, not annualised</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Term, minimum and risk band published up front</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> No entry, exit or management fee</li>
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
                     alt="Capital invested, return earned to date, next payout amount and maturity date.">
              </picture>
            </div>
          </div>
        </div>
      </div>

      <div class="tabs__panel" role="tabpanel" id="panel-ledger" aria-labelledby="tab-ledger">
        <div class="split-row">
          <div>
            <h3 class="split-row__title">Every movement carries a reference.</h3>
            <p class="split-row__body">
              Deposits, payouts, withdrawals and principal releases each get
              their own reference, timestamped and exportable. Reconciling six
              months takes one click, not an email thread.
            </p>
            <ul class="split-row__list">
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Referenced, timestamped, immutable</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Filter by type, date or amount</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Exportable for your accountant</li>
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
                     alt="Transaction history listing deposits, payouts and withdrawals with references.">
              </picture>
            </div>
          </div>
        </div>
      </div>

      <div class="tabs__panel" role="tabpanel" id="panel-guard" aria-labelledby="tab-guard">
        <div class="split-row">
          <div>
            <h3 class="split-row__title">Client money is held separately.</h3>
            <p class="split-row__body">
              Funds sit in segregated accounts, never co-mingled with company
              operating money, and are reconciled on a regular schedule.
              Identity is verified before any withdrawal leaves the platform.
            </p>
            <ul class="split-row__list">
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Segregated client accounts</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> AES-256 encryption and multi-factor sign-in</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Identity verification before withdrawal</li>
            </ul>
          </div>
          <div class="split-row__media">
            <div class="device">
              <div class="device__bar" aria-hidden="true">
                <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
                <span class="device__url">/dashboard.wallet</span>
              </div>
              <picture>
                <source type="image/avif" srcset="/assets/images/product/app-wallet.avif">
                <source type="image/webp" srcset="/assets/images/product/app-wallet.webp">
                <img src="/assets/images/product/app-wallet.webp" width="1120" height="524" loading="lazy"
                     alt="The wallet page with balance and published deposit addresses.">
              </picture>
            </div>
          </div>
        </div>
      </div>

      <div class="tabs__panel" role="tabpanel" id="panel-access" aria-labelledby="tab-access">
        <div class="split-row">
          <div>
            <h3 class="split-row__title">Start at $250. Scale when you want to.</h3>
            <p class="split-row__body">
              The minimums that lock retail investors out of decent terms do
              not exist here. The entry plan opens at $250 and the same
              reporting applies whatever you allocate.
            </p>
            <ul class="split-row__list">
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Entry plan from $250</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Same statement and schedule at every size</li>
              <li><i class="ph ph-check-circle" aria-hidden="true"></i> Run more than one position at once</li>
            </ul>
          </div>
          <div class="split-row__media">
            <div class="device">
              <div class="device__bar" aria-hidden="true">
                <span class="device__dot"></span><span class="device__dot"></span><span class="device__dot"></span>
                <span class="device__url">/dashboard</span>
              </div>
              <picture>
                <source type="image/avif" srcset="/assets/images/product/app-balances.avif">
                <source type="image/webp" srcset="/assets/images/product/app-balances.webp">
                <img src="/assets/images/product/app-balances.webp" width="1120" height="251" loading="lazy"
                     alt="Wallet overview cards showing balance, earnings, invested capital and next payout.">
              </picture>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- =================== TESTIMONIAL =================== -->
<section class="section section--warm">
  <div class="container">
    <div class="quote-xl" data-reveal>
      <p class="quote-xl__mark" aria-hidden="true">&ldquo;</p>
      <p class="quote-xl__text">
        I'd been parking my salary surplus in a 0.5% saver for years. Opening a
        weekly plan took ten minutes, and now a payout lands every Friday with a
        maturity date I can see on the dashboard.
      </p>
      <p class="quote-xl__by">Sarah Johnson &middot; Weekly plan member</p>
    </div>
  </div>
</section>


<!-- =================== FAQ =================== -->
<section class="section">
  <div class="container">
    <div class="lede lede--center" data-reveal>
      <span class="kicker">FAQs</span>
      <h2>Frequently asked questions.</h2>
      <p>Quick answers to what members ask most often.</p>
    </div>

    <div class="accordion" data-reveal>
      <details class="accordion__item" open>
        <summary class="accordion__trigger">
          What is Maveren Capital?
          <span class="accordion__icon" aria-hidden="true"></span>
        </summary>
        <div class="accordion__body">
          Maveren Capital is an investment platform built around a single product: you invest a lump sum into a plan that pays a fixed percentage of your capital back to your wallet either weekly or monthly, and returns your principal in full at the end of the term. One wallet, one statement, one schedule.
        </div>
      </details>

      <details class="accordion__item">
        <summary class="accordion__trigger">
          Is my money safe with Maveren Capital?
          <span class="accordion__icon" aria-hidden="true"></span>
        </summary>
        <div class="accordion__body">
          Client funds are held in segregated accounts, never co-mingled with company operating funds, and reconciled on a regular schedule. Your capital is at risk: rates are not guaranteed, and we disclose the worst-case scenario before every allocation.
        </div>
      </details>

      <details class="accordion__item">
        <summary class="accordion__trigger">
          Is my personal data secure?
          <span class="accordion__icon" aria-hidden="true"></span>
        </summary>
        <div class="accordion__body">
          Yes. We use end-to-end AES-256 encryption, multi-factor authentication, and security controls reviewed quarterly. The platform follows recognised US data-protection practice, with a named Data Protection Officer and a transparent Privacy Notice.
        </div>
      </details>

      <details class="accordion__item">
        <summary class="accordion__trigger">
          What's the minimum to get started?
          <span class="accordion__icon" aria-hidden="true"></span>
        </summary>
        <div class="accordion__body">
          Minimums vary by plan. Maveren Weekly Core opens at $250, Weekly Prime at $1,000, and Monthly Reserve at $5,000. Every minimum is shown on the plan before you commit.
        </div>
      </details>

      <details class="accordion__item">
        <summary class="accordion__trigger">
          Can I withdraw my money any time?
          <span class="accordion__icon" aria-hidden="true"></span>
        </summary>
        <div class="accordion__body">
          Your wallet balance is available on demand, and every payout is withdrawable the moment it lands. Capital committed to a plan is locked for the term and released in full on the maturity date. Withdrawals require a verified identity.
        </div>
      </details>
    </div>
  </div>
</section>


<?php include __DIR__ . '/_partials/footer.php'; ?>

  <!-- Scripts -->
  <script src="<?= mvc_asset('/assets/js/main.js') ?>" defer></script>
  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Maveren Capital",
  "url": "https://maverencapital.com",
  "logo": "https://maverencapital.com/assets/favicon/android-chrome-512x512.png",
  "sameAs": [
    "https://www.linkedin.com/company/maverenholdings",
    "https://twitter.com/maverenholdings",
    "https://instagram.com/maverenholdings"
  ]
}
</script>

</body>
</html>
