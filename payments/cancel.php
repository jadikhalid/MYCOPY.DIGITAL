<?php
require __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment cancelled — <?= SITE_NAME ?>.DIGITAL</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="stage">
    <div class="stage__visual" aria-hidden="true">
      <img class="stage__img" src="../assets/human-and-copy.png" alt="" width="1920" height="1080">
      <div class="stage__veil stage__veil--strong"></div>
    </div>
    <div class="scanline" aria-hidden="true"></div>

    <header class="nav">
      <a class="brand" href="../index.php"><?= SITE_NAME ?><span>.DIGITAL</span></a>
      <a class="nav__link" href="../attente.php">Waitlist</a>
    </header>

    <main class="hero">
      <div class="hero__copy">
        <p class="vault__badge">Checkout cancelled</p>
        <h1 class="access-page__title">No charge made.</h1>
        <p class="hero__lead">
          Your waitlist reservation was not completed.
          You can restart checkout whenever you’re ready — $49 USD.
        </p>
        <div class="access__row">
          <a class="access__btn" href="../attente.php">Return to waitlist</a>
        </div>
      </div>
    </main>

    <footer class="footer">
      <span><?= SITE_NAME ?>.DIGITAL — <?= SITE_TAGLINE ?></span>
      <span>Payment cancelled</span>
    </footer>
  </div>
</body>
</html>
