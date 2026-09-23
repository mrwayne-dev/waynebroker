<?php
// ============================================================
// FOOTER partial
// Includes the navbar scroll-toggle JS so every page picks it up.
// ============================================================
?>
<footer class="footer">
  <div class="container">
    <div class="footer__top">
      <?php // Two-image swap, same as the navbar. The footer sits on
            // --color-bg-page, which is near-black in the dark theme - a single
            // hardcoded ink mark would be invisible there. ?>
      <a href="/" class="footer__brand" aria-label="Maveren Capital home">
        <?php // Not lazy: the hidden variant is the one the theme toggle swaps
              // in, and a lazy display:none image is never fetched at all - so
              // toggling showed an empty box until the request came back. ~13KB
              // for both marks. ?>
        <img class="footer__logo footer__logo--light" src="/assets/images/logo/mvc-mark-orange.png" width="128" height="128" alt="">
        <img class="footer__logo footer__logo--dark" src="/assets/images/logo/mvc-mark-ink.png" width="128" height="128" alt="">
        <span class="footer__wordmark">Maveren Capital</span>
      </a>
      <h2 style="margin: var(--space-2) 0;">Your wealth, simplified in one place.</h2>
      <p style="color: var(--color-ink-muted); max-width: 560px;">
        Open an account in minutes. Fund the wallet. Deploy across weekly and monthly plans, and let Maveren Capital handle the compounding, the reporting, and the protections.
      </p>
      <a href="/register" class="btn btn--primary">Start a conversation</a>
    </div>

    <nav class="footer__nav" aria-label="Footer">
      <a href="/plans">Plans</a><span class="footer__sep">·</span>
      <a href="/platform">Platform</a><span class="footer__sep">·</span>
      <a href="/solutions">Solutions</a><span class="footer__sep">·</span>
      <a href="/about">About</a><span class="footer__sep">·</span>
      <a href="/contact">Contact</a><span class="footer__sep">·</span>
      <a href="/login">Sign in</a>
    </nav>

    <?php // Social links removed. The copyright is the only child now, so
          // .footer__credits centres it rather than space-between. ?>
    <div class="footer__credits">
      <span>&copy; <?= date('Y') ?> Maveren Capital LLC. All rights reserved.</span>
    </div>
  </div>
</footer>

<?php // Smartsupp attribution. Was on index.php only; moved here so every
      // marketing page carries it, since the widget itself is loaded on all of
      // them. Renders only when JavaScript is off - with JS on, the chat
      // bubble carries the branding instead. ?>
<noscript>
  Powered by <a href="https://www.smartsupp.com" target="_blank" rel="noopener noreferrer">Smartsupp</a>
</noscript>

<?php // Navbar scroll toggle + mobile menu now live in assets/js/main.js. ?>
