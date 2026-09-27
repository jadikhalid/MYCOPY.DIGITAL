<?php
require __DIR__ . '/config.php';
require_auth();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/studio/capture.php';

$subject = studio_subject_for_session();
$complete = studio_subject_is_complete($subject);
$fields = $subject ? studio_capture_public_fields($subject) : [];
$step = $subject ? studio_capture_current_step($subject) : 1;

$ageLabel = '';
if ($complete && !empty($fields['birth_date'])) {
    $dob = DateTimeImmutable::createFromFormat('Y-m-d', $fields['birth_date']);
    if ($dob) {
        $ageLabel = (string) $dob->diff(new DateTimeImmutable('today'))->y;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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
  <link rel="stylesheet" href="assets/style.css?v=52">
</head>
<body class="page-studio-capture">
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
      <a class="brand" href="<?= htmlspecialchars(route_url('studio'), ENT_QUOTES, 'UTF-8') ?>"><?= SITE_NAME ?><span>.DIGITAL</span></a>
      <div class="nav__status">Session active</div>
    </header>

    <main class="vault vault--capture">
      <?php if (!$complete): ?>
        <p class="vault__badge">Phase 1 · Capture</p>
        <h1 class="vault__title">Please provide the following information.</h1>

        <form class="studio-capture" id="studio-capture-form" enctype="multipart/form-data" data-step="<?= (int) $step ?>">
          <div class="studio-capture__stage" id="studio-capture-stage">
          <div class="studio-capture__panel<?= $step === 1 ? ' is-active' : '' ?>" data-step-panel="1">
            <fieldset class="studio-capture__section">
              <legend class="studio-capture__legend">Your name</legend>
              <div class="studio-capture__grid">
                <label class="studio-capture__field">
                  <span class="studio-capture__label">First name</span>
                  <input type="text" name="first_name" id="capture-first_name" maxlength="80" autocomplete="given-name" value="<?= htmlspecialchars((string) ($fields['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="studio-capture__field">
                  <span class="studio-capture__label">Last name</span>
                  <input type="text" name="last_name" id="capture-last_name" maxlength="80" autocomplete="family-name" value="<?= htmlspecialchars((string) ($fields['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>
              </div>
            </fieldset>
          </div>

          <div class="studio-capture__panel<?= $step === 2 ? ' is-active' : '' ?>" data-step-panel="2">
            <fieldset class="studio-capture__section">
              <legend class="studio-capture__legend">Date of birth</legend>
              <label class="studio-capture__field studio-capture__field--full">
                <span class="studio-capture__label">Date of birth</span>
                <input type="date" name="birth_date" id="capture-birth_date" value="<?= htmlspecialchars((string) ($fields['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              </label>
            </fieldset>
          </div>

          <div class="studio-capture__panel<?= $step === 3 ? ' is-active' : '' ?>" data-step-panel="3">
            <fieldset class="studio-capture__section">
              <legend class="studio-capture__legend">Character</legend>
              <label class="studio-capture__field studio-capture__field--full">
                <span class="studio-capture__label">How would you describe your character?</span>
                <textarea name="character_text" id="capture-character_text" rows="5" maxlength="2000" placeholder="Tone, temperament, habits, how you relate to others…"><?= htmlspecialchars((string) ($fields['character_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              </label>
            </fieldset>
          </div>

          <div class="studio-capture__panel<?= $step === 4 ? ' is-active' : '' ?>" data-step-panel="4">
            <fieldset class="studio-capture__section">
              <legend class="studio-capture__legend">Reference photos</legend>
              <div class="studio-capture__photos">
                <?php foreach (studio_capture_field_definitions() as $def): ?>
                  <?php if ($def['section'] !== 'photos') continue; ?>
                  <?php $url = (string) ($fields[$def['key'] . '_url'] ?? ''); ?>
                  <div class="studio-capture__photo-slot">
                    <div class="studio-capture__photo-frame">
                      <img
                        id="preview-<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>"
                        class="studio-capture__photo-img"
                        alt=""
                        width="200"
                        height="200"
                        <?= $url !== '' ? 'src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' : 'hidden' ?>
                      >
                      <span class="studio-capture__photo-placeholder" <?= $url !== '' ? 'hidden' : '' ?>>No photo</span>
                    </div>
                    <label class="studio-capture__label" for="<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($def['label'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input
                      type="file"
                      id="<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>"
                      name="<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>"
                      accept="image/jpeg,image/png,image/webp"
                    >
                  </div>
                <?php endforeach; ?>
              </div>
            </fieldset>
          </div>

          <div class="studio-capture__panel<?= $step === 5 ? ' is-active' : '' ?>" data-step-panel="5">
            <div class="studio-capture__section">
              <p class="studio-capture__legend">Review & confirm</p>
              <dl class="studio-capture__recap" id="studio-capture-recap">
                <div>
                  <dt>Name</dt>
                  <dd data-recap="name"><?= htmlspecialchars(trim(($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div>
                  <dt>Date of birth</dt>
                  <dd data-recap="birth_date"><?= htmlspecialchars((string) ($fields['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div>
                  <dt>Character</dt>
                  <dd data-recap="character_text"><?= nl2br(htmlspecialchars((string) ($fields['character_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></dd>
                </div>
              </dl>
              <div class="studio-capture__photos studio-capture__photos--recap" id="studio-capture-recap-photos">
                <?php foreach (studio_capture_photo_slots() as $slot): ?>
                  <?php
                    $label = match ($slot) {
                        'photo_face' => 'Face',
                        'photo_profile_left' => 'Left',
                        'photo_profile_right' => 'Right',
                        default => $slot,
                    };
                    $url = (string) ($fields[$slot . '_url'] ?? '');
                  ?>
                  <div class="studio-capture__photo-slot">
                    <div class="studio-capture__photo-frame">
                      <?php if ($url !== ''): ?>
                        <img class="studio-capture__photo-img" data-recap-photo="<?= htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') ?>" src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" width="200" height="200">
                      <?php else: ?>
                        <img class="studio-capture__photo-img" data-recap-photo="<?= htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') ?>" alt="" width="200" height="200" hidden>
                        <span class="studio-capture__photo-placeholder">No photo</span>
                      <?php endif; ?>
                    </div>
                    <span class="studio-capture__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
              <label class="studio-capture__confirm">
                <input type="checkbox" name="confirm_lock" id="capture-confirm-lock" value="1">
                <span>I confirm this information is correct and ready to lock.</span>
              </label>
            </div>
          </div>
          </div>

        </form>
      <?php else: ?>
        <p class="vault__badge">Phase 1 · Capture complete</p>
        <h1 class="vault__title">Base locked.</h1>
        <p class="vault__text">
          <?= htmlspecialchars(trim(($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
          <?php if ($ageLabel !== ''): ?>
            · <?= htmlspecialchars($ageLabel, ENT_QUOTES, 'UTF-8') ?> years
          <?php endif; ?>
          — born <?= htmlspecialchars((string) ($fields['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.
        </p>

        <section class="studio-capture studio-capture--readonly" aria-label="Captured profile">
          <div class="studio-capture__section">
            <p class="studio-capture__legend">Character</p>
            <p class="studio-capture__readonly-text"><?= nl2br(htmlspecialchars((string) ($fields['character_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
          </div>

          <div class="studio-capture__section">
            <p class="studio-capture__legend">Reference photos</p>
            <div class="studio-capture__photos">
              <?php foreach (studio_capture_photo_slots() as $slot): ?>
                <?php
                  $label = match ($slot) {
                      'photo_face' => 'Face (front)',
                      'photo_profile_left' => 'Profile (left)',
                      'photo_profile_right' => 'Profile (right)',
                      default => $slot,
                  };
                  $url = (string) ($fields[$slot . '_url'] ?? '');
                ?>
                <div class="studio-capture__photo-slot">
                  <div class="studio-capture__photo-frame">
                    <?php if ($url !== ''): ?>
                      <img class="studio-capture__photo-img" src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" width="200" height="200">
                    <?php else: ?>
                      <span class="studio-capture__photo-placeholder">No photo</span>
                    <?php endif; ?>
                  </div>
                  <span class="studio-capture__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <p class="studio-capture__meta">
            Locked <?= htmlspecialchars((string) ($fields['completed_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> UTC
            · Phase 2 (Train) comes next.
          </p>
        </section>
      <?php endif; ?>

      <div class="vault__actions">
        <form method="post" action="logout.php" id="end-session-form">
          <button class="btn-ghost" type="submit">End session</button>
        </form>
        <?php if (!$complete): ?>
          <div class="studio-capture__actions" id="studio-capture-actions" data-step="<?= (int) $step ?>">
            <button class="btn-ghost" type="button" id="studio-capture-back" <?= $step <= 1 ? 'disabled' : '' ?>>Back</button>
            <button class="btn-ghost" type="button" id="studio-capture-reset" <?= $step >= STUDIO_CAPTURE_MAX_STEP ? 'hidden' : '' ?>>Reset</button>
            <button class="access__btn" type="button" id="studio-capture-continue" <?= $step >= STUDIO_CAPTURE_MAX_STEP ? 'hidden' : '' ?>>Continue</button>
            <button class="access__btn" type="button" id="studio-capture-complete" <?= $step < STUDIO_CAPTURE_MAX_STEP ? 'hidden' : '' ?> disabled>Confirm capture</button>
          </div>
        <?php endif; ?>
      </div>
    </main>

    <footer class="footer">
      <span><?= SITE_NAME ?>.DIGITAL — private space</span>
      <span>Do not share your code</span>
    </footer>
  </div>

  <div class="studio-modal" id="end-session-modal" role="dialog" aria-modal="true" aria-labelledby="end-session-title" hidden>
    <div class="studio-modal__backdrop" data-modal-close></div>
    <div class="studio-modal__panel">
      <p class="vault__badge">End session</p>
      <h2 class="studio-modal__title" id="end-session-title">Leave the studio?</h2>
      <p class="studio-modal__text">
        <?= $complete
          ? 'You can come back later with your access code.'
          : 'Your current answers will be saved. You can resume later with your access code.' ?>
      </p>
      <div class="studio-modal__actions">
        <button class="btn-ghost" type="button" data-modal-close>Cancel</button>
        <button class="access__btn" type="button" id="end-session-confirm"><?= $complete ? 'Quit' : 'Save &amp; quit' ?></button>
      </div>
    </div>
  </div>

  <?php if (!$complete): ?>
    <script src="assets/studio-capture.js?v=11" defer></script>
  <?php endif; ?>
  <script src="assets/studio-session.js?v=4" defer></script>
</body>
</html>
