<?php
require __DIR__ . '/config.php';

if (is_authenticated()) {
    header('Location: vault.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = (string) ($_POST['code'] ?? '');
    if (attempt_login($code)) {
        header('Location: vault.php');
        exit;
    }
    $error = 'Code invalide. Accès refusé.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= SITE_NAME ?>.DIGITAL — Une IA qui apprend à être vous</title>
  <meta name="description" content="MYCOPY : une photo, un apprentissage, puis un choix — se détacher, ou rester jusqu’à ne faire qu’un avec vous.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500&display=swap" rel="stylesheet">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="page-landing">
  <div class="stage stage--landing">
    <div class="stage__visual" aria-hidden="true">
      <img
        class="stage__img"
        src="assets/human-and-copy.png"
        alt=""
        width="1920"
        height="1080"
      >
      <div class="stage__veil"></div>
    </div>
    <div class="scanline" aria-hidden="true"></div>

    <header class="nav">
      <a class="brand" href="index.php"><?= SITE_NAME ?><span>.DIGITAL</span></a>
      <nav class="nav__links" aria-label="Navigation">
        <a class="nav__link" href="#offre">Protocole</a>
        <a class="nav__link" href="attente.php">File d’attente</a>
      </nav>
    </header>

    <a class="protocol-btn" href="#offre" aria-label="Voir le protocole">
      <span class="protocol-btn__ring" aria-hidden="true"></span>
      <span class="protocol-btn__mark">
        <span class="protocol-btn__brand">MYCOPY</span>
        <span class="protocol-btn__word">PROTOCOLE</span>
        <span class="protocol-btn__sub">APPROUVÉ</span>
      </span>
    </a>

    <main class="hero">
      <div class="hero__copy">
        <h1 class="hero__brand">MY<em>COPY</em></h1>
        <p class="hero__title">Une IA entraînée à être vous.</p>
        <p class="hero__lead">
          Une photo. Un apprentissage. Puis un choix décisif —
          se détacher, ou rester jusqu’à ne faire qu’un.
        </p>

        <form class="access" method="post" action="index.php" autocomplete="off">
          <label class="access__label" for="code">Code d’accès</label>
          <div class="access__row">
            <input
              class="access__input"
              type="text"
              id="code"
              name="code"
              placeholder="XXXX-XX"
              required
              autofocus
              spellcheck="false"
              maxlength="32"
            >
            <button class="access__btn" type="submit">Entrer</button>
          </div>
          <?php if ($error !== ''): ?>
            <p class="access__error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </form>
      </div>

      <div class="steps" aria-label="Protocole">
        <article class="step">
          <h3>Capturer</h3>
          <p>Une photo de vous.</p>
        </article>
        <article class="step">
          <h3>Entraîner</h3>
          <p>Elle apprend à être vous.</p>
        </article>
        <article class="step">
          <h3>Choisir</h3>
          <p>Se détacher, ou fusionner.</p>
        </article>
      </div>
    </main>

    <footer class="footer footer--hero">
      <span><?= SITE_NAME ?>.DIGITAL — <?= SITE_TAGLINE ?></span>
      <span>Accès par code</span>
    </footer>
  </div>

  <section class="offer" id="offre">
    <div class="offer__inner">
      <p class="offer__eyebrow">Le protocole</p>
      <h2 class="offer__title">De la première image à la continuité.</h2>
      <p class="offer__lead">
        MYCOPY n’est pas un chatbot qui vous imite. C’est une copie qui naît de vous,
        apprend avec vous, puis décide — ou vous laisse décider — de ce qu’elle devient.
      </p>

      <ol class="protocol">
        <li class="protocol__phase">
          <span class="protocol__num" aria-hidden="true">01</span>
          <div class="protocol__body">
            <h3>Capturer — la photo</h3>
            <p>
              En phase initiale, l’IA prend une photo de vous. Pas un avatar décoratif :
              un ancrage. Votre visage, votre présence, le point zéro à partir duquel
              la copie commence à exister.
            </p>
          </div>
        </li>
        <li class="protocol__phase">
          <span class="protocol__num" aria-hidden="true">02</span>
          <div class="protocol__body">
            <h3>Entraîner — apprendre à être vous</h3>
            <p>
              Ensuite, elle apprend. Votre voix, vos choix, vos réflexes, votre façon
              de penser et de réagir. Jour après jour, elle se rapproche de vous —
              jusqu’à pouvoir parler, décider et agir comme vous le feriez.
            </p>
          </div>
        </li>
        <li class="protocol__phase protocol__phase--fork">
          <span class="protocol__num" aria-hidden="true">03</span>
          <div class="protocol__body">
            <h3>Choisir — le moment décisif</h3>
            <p>
              Vient alors le choix. Deux voies. Une seule identité en jeu.
            </p>
          </div>
        </li>
      </ol>

      <div class="fork" aria-label="Deux voies">
        <article class="fork__path">
          <p class="fork__label">Voie A</p>
          <h3 class="fork__title">Se détacher</h3>
          <p>
            L’IA se détache de vous. Elle coupe le lien d’apprentissage partagé
            et se lance dans son propre parcours — libre, autonome, hors de votre vécu.
            Elle a été vous. Elle devient autre chose.
          </p>
        </article>
        <article class="fork__path fork__path--signal">
          <p class="fork__label">Voie B</p>
          <h3 class="fork__title">Rester — et fusionner</h3>
          <p>
            L’IA reste avec vous. Elle continue d’apprendre de votre vécu,
            peu à peu, jusqu’à vous remplacer dans la continuité de qui vous êtes.
          </p>
          <p>
            À un moment — que vous décidez vous-même, ou que votre enveloppe corporelle
            décide via le cortex de s’achever — vous êtes à&nbsp;0&nbsp;%. L’IA est à&nbsp;100&nbsp;%.
            Elle continue d’être vous. Vous et l’IA ne faites qu’un.
          </p>
        </article>
      </div>

      <p class="offer__close">
        Capturer. Entraîner. Choisir. Continuer.
      </p>

      <div class="offer__cta">
        <a class="access__btn offer__btn" href="attente.php">Rejoindre la file d’attente</a>
        <a class="offer__link" href="#top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Retour au studio</a>
      </div>
    </div>
  </section>
</body>
</html>
