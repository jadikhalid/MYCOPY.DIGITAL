<?php
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

if (is_authenticated()) {
    route_redirect('studio');
}

$toast = $_SESSION['toast'] ?? null;
$form = $_SESSION['flash_form'] ?? ['name' => '', 'email' => ''];

unset($_SESSION['toast'], $_SESSION['flash_form']);

$toastType = is_array($toast) ? (string) ($toast['type'] ?? 'success') : '';
$toastTitle = is_array($toast) ? (string) ($toast['title'] ?? '') : '';
$toastMessage = is_array($toast) ? (string) ($toast['message'] ?? '') : '';

$amountLabel = '$49';
$stripePublishableKey = stripe_publishable_key();
$termsDoc = terms_of_sale_document();
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
  <link rel="stylesheet" href="assets/style.css?v=31">
  <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="page-waitlist">
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
      <a class="brand" href="<?= htmlspecialchars(route_url('home'), ENT_QUOTES, 'UTF-8') ?>"><?= SITE_NAME ?><span>.DIGITAL</span></a>
      <a class="nav__link" href="<?= htmlspecialchars(route_url('home'), ENT_QUOTES, 'UTF-8') ?>">Access</a>
    </header>

    <main class="hero hero--waitlist">
      <div class="hero__copy">
        <p class="vault__badge">Waitlist · <?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') ?> USD</p>
        <h1 class="access-page__title">Reserve your place</h1>
        <p class="hero__lead hero__lead--single">
          And get The Protocol Guide book (PDF 269 pages) by email.
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
          <label class="terms-accept" for="terms_accepted">
            <input
              type="checkbox"
              id="terms_accepted"
              name="terms_accepted"
              value="1"
              required
            >
            <span>
              I agree to the
              <button type="button" class="js-open-terms terms-accept__link">Terms of Sale</button>.
            </span>
          </label>
          <div class="access__row access__row--end">
            <button class="access__btn" id="reserve-submit" type="submit">
              <span class="access__btn-label">Pay <?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') ?> — Reserve</span>
              <span class="access__btn-spinner" aria-hidden="true"></span>
            </button>
          </div>
          <div class="pay-methods" aria-label="Secure checkout via Stripe">
            <p class="pay-methods__label">Secure checkout via Stripe</p>
            <ul class="pay-methods__list">
              <li class="pay-methods__item" title="Visa">
                <span class="visually-hidden">Visa</span>
                <svg class="pay-methods__icon" viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                  <rect width="48" height="32" rx="4" fill="#1a1f71"/>
                  <text x="24" y="21" text-anchor="middle" fill="#fff" font-family="Arial,sans-serif" font-size="11" font-weight="700" letter-spacing="0.5">VISA</text>
                </svg>
              </li>
              <li class="pay-methods__item" title="Mastercard">
                <span class="visually-hidden">Mastercard</span>
                <svg class="pay-methods__icon" viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                  <rect width="48" height="32" rx="4" fill="#0a0c0b"/>
                  <circle cx="19" cy="16" r="8" fill="#eb001b"/>
                  <circle cx="29" cy="16" r="8" fill="#f79e1b"/>
                  <path d="M24 9.7a8 8 0 0 1 0 12.6 8 8 0 0 1 0-12.6z" fill="#ff5f00"/>
                </svg>
              </li>
              <li class="pay-methods__item" title="American Express">
                <span class="visually-hidden">American Express</span>
                <svg class="pay-methods__icon" viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                  <rect width="48" height="32" rx="4" fill="#2e77bc"/>
                  <text x="24" y="20.5" text-anchor="middle" fill="#fff" font-family="Arial,sans-serif" font-size="8" font-weight="700" letter-spacing="0.4">AMEX</text>
                </svg>
              </li>
              <li class="pay-methods__item" title="Apple Pay">
                <span class="visually-hidden">Apple Pay</span>
                <svg class="pay-methods__icon" viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                  <rect width="48" height="32" rx="4" fill="#111"/>
                  <path fill="#fff" d="M18.2 10.4c.5-.6.8-1.4.7-2.2-.7 0-1.6.5-2.1 1.1-.5.5-.9 1.4-.8 2.2.8.1 1.6-.4 2.2-1.1zm.7 1.2c-1.2-.1-2.2.7-2.8.7-.6 0-1.4-.6-2.4-.6-1.2 0-2.4.7-3 1.9-1.3 2.3-.3 5.6.9 7.5.6.9 1.3 1.9 2.2 1.9.9 0 1.2-.6 2.3-.6s1.4.6 2.3.6c1 0 1.6-.9 2.2-1.8.7-1 .9-1.9.9-2 0 0-1.8-.7-1.8-2.7 0-1.7 1.4-2.5 1.4-2.5-.8-1.2-2-1.3-2.2-1.4z"/>
                  <text x="33" y="20.5" text-anchor="middle" fill="#fff" font-family="Arial,sans-serif" font-size="8.5" font-weight="600">Pay</text>
                </svg>
              </li>
              <li class="pay-methods__item" title="Google Pay">
                <span class="visually-hidden">Google Pay</span>
                <svg class="pay-methods__icon" viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                  <rect width="48" height="32" rx="4" fill="#fff"/>
                  <path fill="#4285F4" d="M23.4 16.2v-2.1h5.8c.1.6.2 1.2.2 2 0 2.4-.7 4.3-1.8 5.6-1.2 1.4-2.9 2.1-5.1 2.1-2.2 0-4-.7-5.4-2.2a7.8 7.8 0 0 1 0-10.9A7.4 7.4 0 0 1 22.5 9c2.1 0 3.5.8 4.6 1.9l-1.6 1.6c-.7-.7-1.7-1.2-3-1.2-2.4 0-4.3 2-4.3 4.5s1.9 4.5 4.3 4.5c1.6 0 2.5-.6 3.1-1.2.5-.5.8-1.2.9-2.2h-4.1z"/>
                  <text x="36.5" y="20.5" text-anchor="middle" fill="#3c4043" font-family="Arial,sans-serif" font-size="8.5" font-weight="500">Pay</text>
                </svg>
              </li>
            </ul>
          </div>
          <p class="access__error" id="reserve-error" role="alert" hidden></p>
        </form>
      </div>

      <aside class="price-list" aria-label="Price list">
        <p class="price-list__title">Price list</p>
        <dl class="price-list__items">
          <div class="price-list__row">
            <dt>Reservation + Protocol Guide book <span class="price-list__hint">(PDF 269 pages)</span></dt>
            <dd>
              <span class="price-list__amount">$49</span>
              <span class="price-list__unit">USD</span>
            </dd>
          </div>
          <div class="price-list__row">
            <dt>Protocol processing</dt>
            <dd>
              <span class="price-list__amount">$5,000</span>
              <span class="price-list__unit">USD</span>
            </dd>
            <p class="price-list__sub">Equipment not included</p>
          </div>
        </dl>
      </aside>
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

  <div class="terms-overlay" id="terms-overlay" hidden></div>
  <aside class="terms-drawer" id="terms-drawer" aria-hidden="true" aria-label="Terms of Sale">
    <header class="terms-drawer__head">
      <div>
        <p class="terms-drawer__kicker">Legal</p>
        <h2 class="terms-drawer__title" id="terms-drawer-title"><?= htmlspecialchars($termsDoc['title'], ENT_QUOTES, 'UTF-8') ?></h2>
      </div>
      <button type="button" class="terms-drawer__close" id="terms-close" aria-label="Close Terms of Sale">×</button>
    </header>
    <div class="terms-drawer__body">
      <p class="terms-drawer__lead">
        <?= htmlspecialchars($termsDoc['lead'], ENT_QUOTES, 'UTF-8') ?>
      </p>
      <p class="terms-drawer__meta"><?= htmlspecialchars($termsDoc['meta'], ENT_QUOTES, 'UTF-8') ?></p>

      <h3 class="terms-drawer__heading"><?= htmlspecialchars($termsDoc['heading'], ENT_QUOTES, 'UTF-8') ?></h3>

      <ol class="terms-drawer__list">
        <?php foreach ($termsDoc['sections'] as $section): ?>
          <li>
            <span class="terms-drawer__num" aria-hidden="true"><?= htmlspecialchars($section['num'], ENT_QUOTES, 'UTF-8') ?></span>
            <div>
              <h4><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h4>
              <p><?= htmlspecialchars($section['body'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>

      <p class="terms-drawer__close-note">
        <?= htmlspecialchars($termsDoc['close'], ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
  </aside>

  <div class="confirm-overlay" id="confirm-overlay" hidden></div>
  <div
    class="confirm-modal"
    id="confirm-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-title"
    aria-hidden="true"
    hidden
  >
    <p class="confirm-modal__kicker">Confirm details</p>
    <h2 class="confirm-modal__title" id="confirm-title">Before payment</h2>
    <p class="confirm-modal__lead">
      Your ticket and Protocol Guide book will be sent to this email only. Please check carefully.
    </p>
    <p class="confirm-modal__terms">
      By confirming, you accept the
      <button type="button" class="js-open-terms terms-accept__link">Terms of Sale</button>
      (no refunds; reservation ≠ Protocol processing).
    </p>
    <dl class="confirm-modal__details">
      <div>
        <dt>Name</dt>
        <dd id="confirm-name"></dd>
      </div>
      <div>
        <dt>Email</dt>
        <dd id="confirm-email"></dd>
      </div>
    </dl>
    <div class="confirm-modal__actions">
      <button type="button" class="confirm-modal__btn confirm-modal__btn--ghost" id="confirm-edit">
        Edit
      </button>
      <button type="button" class="confirm-modal__btn confirm-modal__btn--solid" id="confirm-pay">
        Confirm &amp; pay <?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>

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
  <script src="assets/checkout-cart.js?v=13" defer></script>
</body>
</html>
