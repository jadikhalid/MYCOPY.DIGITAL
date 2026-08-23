<?php
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

if (is_authenticated()) {
    header('Location: vault.php');
    exit;
}

$toast = $_SESSION['toast'] ?? null;
$form = $_SESSION['flash_form'] ?? ['name' => '', 'email' => ''];

unset($_SESSION['toast'], $_SESSION['flash_form']);

$toastType = is_array($toast) ? (string) ($toast['type'] ?? 'success') : '';
$toastTitle = is_array($toast) ? (string) ($toast['title'] ?? '') : '';
$toastMessage = is_array($toast) ? (string) ($toast['message'] ?? '') : '';

$amountLabel = '$49';
$stripePublishableKey = stripe_publishable_key();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Waitlist — <?= SITE_NAME ?>.DIGITAL</title>
  <meta name="description" content="Reserve your MYCOPY waitlist place for $49 — includes protocol PDF.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500&display=swap" rel="stylesheet">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="stylesheet" href="assets/style.css">
  <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
  <div class="stage">
    <div class="stage__visual" aria-hidden="true">
      <img
        class="stage__img"
        src="assets/human-and-copy.png"
        alt=""
        width="1920"
        height="1080"
      >
      <div class="stage__veil stage__veil--strong"></div>
    </div>
    <div class="scanline" aria-hidden="true"></div>

    <header class="nav">
      <a class="brand" href="index.php"><?= SITE_NAME ?><span>.DIGITAL</span></a>
      <a class="nav__link" href="index.php">Access</a>
    </header>

    <main class="hero">
      <div class="hero__copy">
        <p class="vault__badge">Waitlist · <?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') ?> USD</p>
        <h1 class="access-page__title">Reserve your place</h1>
        <p class="hero__lead">
          <?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') ?> locks your waitlist spot.
          After payment, your ticket and protocol PDF are sent by email only.
        </p>

        <form class="access reserve" id="reserve-form" method="post" action="reserve.php" autocomplete="on">
          <label class="access__label" for="name">Name</label>
          <input
            class="access__input access__input--full"
            type="text"
            id="name"
            name="name"
            placeholder="First Last"
            required
            maxlength="120"
            autofocus
            value="<?= htmlspecialchars((string) ($form['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          >
          <label class="access__label" for="email">Email</label>
          <input
            class="access__input access__input--full"
            type="email"
            id="email"
            name="email"
            placeholder="you@example.com"
            required
            maxlength="180"
            value="<?= htmlspecialchars((string) ($form['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          >
          <div class="access__row access__row--end">
            <button class="access__btn" id="reserve-submit" type="submit">
              <span class="access__btn-label">Pay <?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') ?> — Reserve</span>
              <span class="access__btn-spinner" aria-hidden="true"></span>
            </button>
          </div>
          <p class="hero__lead" style="margin-top:0.85rem;margin-bottom:0;font-size:0.78rem;">
            Secure checkout via Stripe. Includes waitlist reservation + protocol PDF.
          </p>
          <p class="access__error" id="reserve-error" role="alert" hidden></p>
        </form>
      </div>
    </main>

    <footer class="footer">
      <span><?= SITE_NAME ?>.DIGITAL — <?= SITE_TAGLINE ?></span>
      <span>Paid reservation</span>
    </footer>
  </div>

  <div class="cart-overlay" id="cart-overlay" hidden></div>
  <aside class="cart-drawer" id="cart-drawer" aria-hidden="true" aria-label="Checkout cart">
    <header class="cart-drawer__head">
      <div>
        <p class="cart-drawer__kicker">Checkout</p>
        <h2 class="cart-drawer__title">Reserve · <?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') ?></h2>
      </div>
      <button type="button" class="cart-drawer__close" id="cart-close" aria-label="Close checkout">×</button>
    </header>
    <div class="cart-drawer__body">
      <div id="checkout-mount" class="cart-drawer__mount"></div>
      <p class="cart-drawer__status" id="cart-status" hidden></p>
    </div>
  </aside>

  <?php if ($toastType !== ''): ?>
    <div
      class="toast toast--<?= htmlspecialchars($toastType, ENT_QUOTES, 'UTF-8') ?>"
      role="status"
      aria-live="polite"
    >
      <span class="toast__bar" aria-hidden="true"></span>
      <div class="toast__body">
        <p class="toast__title"><?= htmlspecialchars($toastTitle, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="toast__msg"><?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
  <?php endif; ?>

  <script>
    window.MYCOPY_STRIPE_PK = <?= json_encode($stripePublishableKey, JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="assets/checkout-cart.js?v=7" defer></script>
</body>
</html>
