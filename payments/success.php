<?php
require __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment successful — <?= SITE_NAME ?>.DIGITAL</title>
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
      <a class="nav__link" href="../index.php">Access</a>
    </header>

    <main class="hero">
      <div class="hero__copy">
        <p class="vault__badge">Payment confirmed</p>
        <h1 class="access-page__title">You’re on the list.</h1>
        <p class="hero__lead">
          Your ticket and protocol PDF are on their way by email.
          Check your inbox (and spam folder) in the next few minutes.
        </p>
        <div class="access__row">
          <a class="access__btn" href="../index.php">Back to studio access</a>
        </div>
      </div>
    </main>

    <footer class="footer">
      <span><?= SITE_NAME ?>.DIGITAL — <?= SITE_TAGLINE ?></span>
      <span>$49 reservation</span>
    </footer>
  </div>
</body>
</html>
