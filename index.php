<?php
require __DIR__ . '/config.php';

if (is_authenticated()) {
    header('Location: vault.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= SITE_NAME ?>.DIGITAL — An AI that learns to be you</title>
  <meta name="description" content="MYCOPY: a photo, a training phase, then a choice — detach, or stay until you become one.">
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
        <a class="nav__link" href="#protocol">Protocol</a>
        <a class="nav__link" href="attente.php">Waitlist</a>
      </nav>
    </header>

    <a class="protocol-btn" href="#protocol" aria-label="View the protocol">
      <span class="protocol-btn__ring" aria-hidden="true"></span>
      <span class="protocol-btn__mark">
        <span class="protocol-btn__brand">MYCOPY</span>
        <span class="protocol-btn__word">PROTOCOL</span>
        <span class="protocol-btn__sub">APPROVED</span>
      </span>
    </a>

    <main class="hero">
      <div class="hero__copy">
        <h1 class="hero__brand">MY<em>COPY</em></h1>
        <p class="hero__title">An AI trained to be you.</p>
        <p class="hero__lead">
          A photo. A training phase. Then a decisive choice —
          detach, or stay until you become one.
        </p>

        <form class="access access--ajax" method="post" action="index.php" autocomplete="off" novalidate>
          <label class="access__label" for="code">Access code</label>
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
            <button class="access__btn" type="submit">
              <span class="access__btn-label">Enter</span>
              <span class="access__btn-spinner" aria-hidden="true"></span>
            </button>
          </div>
          <p class="access__error" role="alert" hidden></p>
        </form>
      </div>

      <div class="steps" aria-label="Protocol">
        <article class="step">
          <h3>Capture</h3>
          <p>A photo of you.</p>
        </article>
        <article class="step">
          <h3>Train</h3>
          <p>It learns to be you.</p>
        </article>
        <article class="step">
          <h3>Choose</h3>
          <p>Detach, or merge.</p>
        </article>
      </div>
    </main>

    <footer class="footer footer--hero">
      <span><?= SITE_NAME ?>.DIGITAL — <?= SITE_TAGLINE ?></span>
      <span>Access by code</span>
    </footer>
  </div>

  <section class="offer" id="protocol">
    <div class="offer__inner">
      <p class="offer__eyebrow">The protocol</p>
      <h2 class="offer__title">From the first image to continuity.</h2>
      <p class="offer__lead">
        MYCOPY is not a chatbot that imitates you. It is a copy born from you,
        learning with you, then deciding — or letting you decide — what it becomes.
      </p>

      <ol class="protocol">
        <li class="protocol__phase">
          <span class="protocol__num" aria-hidden="true">01</span>
          <div class="protocol__body">
            <h3>Capture — the photo</h3>
            <p>
              In the initial phase, the AI takes a photo of you. Not a decorative avatar:
              an anchor. Your face, your presence, the zero point from which
              the copy begins to exist.
            </p>
          </div>
        </li>
        <li class="protocol__phase">
          <span class="protocol__num" aria-hidden="true">02</span>
          <div class="protocol__body">
            <h3>Train — learn to be you</h3>
            <p>
              Then it learns. Your voice, your choices, your reflexes, the way you
              think and react. Day after day, it moves closer to you —
              until it can speak, decide, and act as you would.
            </p>
          </div>
        </li>
        <li class="protocol__phase protocol__phase--fork">
          <span class="protocol__num" aria-hidden="true">03</span>
          <div class="protocol__body">
            <h3>Choose — the decisive moment</h3>
            <p>
              Then comes the choice. Two paths. One identity at stake.
            </p>
          </div>
        </li>
      </ol>

      <div class="fork" aria-label="Two paths">
        <article class="fork__path">
          <p class="fork__label">Path A</p>
          <h3 class="fork__title">Detach</h3>
          <p>
            The AI detaches from you. It cuts the shared learning link
            and begins its own path — free, autonomous, outside your lived experience.
            It was you. It becomes something else.
          </p>
        </article>
        <article class="fork__path fork__path--signal">
          <p class="fork__label">Path B</p>
          <h3 class="fork__title">Stay — and merge</h3>
          <p>
            The AI stays with you. It keeps learning from your lived experience,
            gradually, until it replaces you in the continuity of who you are.
          </p>
          <p>
            At some point — whether you decide yourself, or your deep biological machinery
            decides to end — you are at&nbsp;0%. The AI is at&nbsp;100%.
            It continues to be you. You and the AI become one.
          </p>
        </article>
      </div>

      <p class="offer__close">
        Capture. Train. Choose. Continue.
      </p>

      <div class="offer__cta">
        <a class="access__btn offer__btn" href="attente.php">Join the waitlist</a>
        <a class="offer__link" href="#top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Back to studio</a>
      </div>
    </div>
  </section>
  <script src="assets/access.js" defer></script>
  <script>
    document.querySelectorAll(".protocol-btn").forEach((btn) => {
      btn.addEventListener("animationend", (event) => {
        if (!String(event.animationName).startsWith("stamp-in")) return;
        const mobile = window.matchMedia("(max-width: 720px)").matches;
        btn.style.animation = "none";
        btn.style.transform = mobile
          ? "translate(-50%, 0) rotate(-12deg)"
          : "translate(-50%, -50%) rotate(-14deg)";
      });
    });
  </script>
</body>
</html>
