<?php
http_response_code(404);
$page_title = 'Not Found | Maveren Capital';
$page_description = 'The page you requested could not be found.';
$page_path = '/404';
include __DIR__ . '/_partials/head.php';
?>
<body class="mvc-redesign" data-hero="dark">

<?php include __DIR__ . '/_partials/navbar.php'; ?>

<section class="hero-x hero-x--compact">
  <div class="hero-x__wash" aria-hidden="true"></div>
  <div class="container">
    <div class="hero-x__inner">
      <span class="kicker">404</span>
      <h1 class="hero-x__title">Not <em>found</em>.</h1>
      <p class="hero-x__lead">
        The page you're looking for doesn't exist, or has moved. Head back to
        the homepage, or sign in to your dashboard.
      </p>
      <div class="hero-x__cta">
        <a href="/" class="btn btn--primary">Return home</a>
        <a href="/login" class="btn btn--ghost">Sign in</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/_partials/footer.php'; ?>

<script src="<?= mvc_asset('/assets/js/main.js') ?>" defer></script>
</body>
</html>
