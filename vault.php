<?php
require __DIR__ . '/config.php';
require_auth();

$code = (string) ($_SESSION['mycopy_code'] ?? '—');
$since = (int) ($_SESSION['mycopy_at'] ?? time());
$stamp = date('Y-m-d H:i:s', $since);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Studio — <?= SITE_NAME ?>.DIGITAL</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500&display=swap" rel="stylesheet">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="stage stage--vault">
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
      <a class="brand" href="vault.php"><?= SITE_NAME ?><span>.DIGITAL</span></a>
      <div class="nav__status">Session active</div>
    </header>

    <main class="vault">
      <p class="vault__badge">Studio // accès accordé</p>
      <h1 class="vault__title">Votre modèle est prêt à apprendre.</h1>
      <p class="vault__text">
        Le code <strong style="color:var(--bone);font-weight:500"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></strong>
        ouvre ce studio. Déposez vos traces — messages, voix, décisions —
        et l’IA s’entraîne à devenir votre copie.
      </p>

      <div class="console" aria-live="polite">
        <div><span class="dim">$</span> mycopy status</div>
        <div class="ok">● studio ouvert</div>
        <div>auth …… <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></div>
        <div>since … <?= htmlspecialchars($stamp, ENT_QUOTES, 'UTF-8') ?></div>
        <div>node …… train-01.mycopy.digital</div>
        <div>state … IDLE — en attente de données</div>
        <div><span class="dim">$</span> <span class="cursor" aria-hidden="true"></span></div>
      </div>

      <div class="vault__actions">
        <form method="post" action="logout.php">
          <button class="btn-ghost" type="submit">Fermer la session</button>
        </form>
      </div>
    </main>

    <footer class="footer">
      <span><?= SITE_NAME ?>.DIGITAL — espace privé</span>
      <span>Ne partagez pas votre code</span>
    </footer>
  </div>
</body>
</html>
