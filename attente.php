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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>File d’attente — <?= SITE_NAME ?>.DIGITAL</title>
  <meta name="description" content="Réservez votre place dans la file d’attente MYCOPY.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500&display=swap" rel="stylesheet">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="stylesheet" href="assets/style.css">
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
      <a class="nav__link" href="index.php">Accès</a>
    </header>

    <main class="hero">
      <div class="hero__copy">
        <p class="vault__badge">File d’attente</p>
        <h1 class="access-page__title">Réservez votre place</h1>
        <p class="hero__lead">
          Votre ticket est envoyé uniquement par e-mail après inscription.
        </p>

        <form class="access reserve" method="post" action="reserve.php" autocomplete="on">
          <label class="access__label" for="name">Nom</label>
          <input
            class="access__input access__input--full"
            type="text"
            id="name"
            name="name"
            placeholder="Prénom Nom"
            required
            maxlength="120"
            autofocus
            value="<?= htmlspecialchars((string) ($form['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          >
          <label class="access__label" for="email">E-mail</label>
          <input
            class="access__input access__input--full"
            type="email"
            id="email"
            name="email"
            placeholder="vous@exemple.com"
            required
            maxlength="180"
            value="<?= htmlspecialchars((string) ($form['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          >
          <div class="access__row access__row--end">
            <button class="access__btn" type="submit">Obtenir mon ticket</button>
          </div>
        </form>
      </div>
    </main>

    <footer class="footer">
      <span><?= SITE_NAME ?>.DIGITAL — <?= SITE_TAGLINE ?></span>
      <span>Inscription file d’attente</span>
    </footer>
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
</body>
</html>
