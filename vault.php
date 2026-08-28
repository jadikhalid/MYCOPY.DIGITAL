<?php
require __DIR__ . '/config.php';
require_auth();

$code = (string) ($_SESSION['mycopy_code'] ?? '—');
$since = (int) ($_SESSION['mycopy_at'] ?? time());
$stamp = date('Y-m-d H:i:s', $since);
$isFounder = is_founder_session();

if ($isFounder) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/protocol.php';
    require_once __DIR__ . '/protocol/intake_schema.php';
    ensure_founder_protocol();
    $protocol = get_founder_protocol();
    $currentPhase = (string) ($protocol['current_phase'] ?? 'intake');
    if ($currentPhase === 'capture') {
        $currentPhase = 'intake';
    }
    $profileCompletion = null;
    $geminiConfigured = false;
    if ($currentPhase === 'train') {
        require_once __DIR__ . '/profile/Profiler.php';
        $geminiConfigured = gemini_is_configured();
        $sid = protocol_subject_id_for_session();
        if ($sid !== null) {
            $profileCompletion = profile_completion($sid);
        }
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
  <link rel="stylesheet" href="assets/style.css?v=36">
</head>
<body class="<?= $isFounder ? 'page-studio-founder' : '' ?>">
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
      <div class="nav__status"><?= $isFounder ? 'Founder · cobaye' : 'Session active' ?></div>
    </header>

    <?php if ($isFounder): ?>
      <?php
        $phaseIndex = array_search($currentPhase, PROTOCOL_PHASES, true);
        if ($phaseIndex === false) {
            $phaseIndex = 0;
            $currentPhase = 'intake';
        }
        $phaseHeadlines = [
            'intake' => [
                'title' => 'Before we begin.',
                'lead' => 'First visit — identity, family names, and reference photos. Nothing else unlocks until this is complete.',
            ],
            'train' => [
                'title' => 'Train your copy.',
                'lead' => 'Drop traces — messages, voice, decisions. The AI learns to be you.',
            ],
            'choose' => [
                'title' => 'Your choice.',
                'lead' => 'Detach, or stay until you become one.',
            ],
        ];
        $headline = $phaseHeadlines[$currentPhase] ?? $phaseHeadlines['intake'];
      ?>
      <main class="vault vault--protocol vault--phase-<?= htmlspecialchars($currentPhase, ENT_QUOTES, 'UTF-8') ?>">
        <nav class="protocol-stepper" aria-label="Protocol progress">
          <ol class="protocol-stepper__list">
            <?php foreach (PROTOCOL_PHASES as $i => $phase): ?>
              <?php
                $stepState = $phase === $currentPhase
                    ? 'current'
                    : ($i < $phaseIndex ? 'done' : 'upcoming');
              ?>
              <li class="protocol-stepper__step protocol-stepper__step--<?= $stepState ?>">
                <span class="protocol-stepper__num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
                <span class="protocol-stepper__label"><?= htmlspecialchars(protocol_phase_label($phase), ENT_QUOTES, 'UTF-8') ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </nav>

        <p class="vault__badge">Step <?= (int) $phaseIndex + 1 ?> of <?= count(PROTOCOL_PHASES) ?> · <span id="protocol-current-phase"><?= htmlspecialchars($currentPhase, ENT_QUOTES, 'UTF-8') ?></span></p>
        <h1 class="vault__title"><?= htmlspecialchars($headline['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="vault__text">
          <?= htmlspecialchars($headline['lead'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <section class="protocol-screen protocol-screen--<?= htmlspecialchars($currentPhase, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(protocol_phase_label($currentPhase), ENT_QUOTES, 'UTF-8') ?> phase">

          <?php if ($currentPhase === 'intake'): ?>
            <form class="protocol-intake" id="protocol-intake-form" enctype="multipart/form-data">
              <?php
                $sections = ['identity' => 'Identity', 'parents' => 'Parents', 'photos' => 'Reference photos'];
                foreach ($sections as $sectionKey => $sectionLabel):
              ?>
                <fieldset class="protocol-intake__section">
                  <legend class="protocol-intake__legend"><?= htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8') ?></legend>
                  <?php if ($sectionKey === 'photos'): ?>
                    <div class="protocol-intake__photos">
                      <?php foreach (intake_field_definitions() as $def): ?>
                        <?php if ($def['section'] !== 'photos') continue; ?>
                        <div class="protocol-intake__photo-slot">
                          <div class="protocol-intake__photo-frame">
                            <img
                              id="preview-<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>"
                              class="protocol-intake__photo-img"
                              alt=""
                              width="200"
                              height="200"
                              hidden
                            >
                            <span class="protocol-intake__photo-placeholder">No photo</span>
                          </div>
                          <label class="protocol-intake__label" for="<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>">
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
                  <?php else: ?>
                    <div class="protocol-intake__grid">
                      <?php foreach (intake_field_definitions() as $def): ?>
                        <?php if ($def['section'] !== $sectionKey || $def['type'] !== 'text') continue; ?>
                        <label class="protocol-intake__field">
                          <span class="protocol-intake__label"><?= htmlspecialchars($def['label'], ENT_QUOTES, 'UTF-8') ?></span>
                          <input
                            type="text"
                            name="<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>"
                            id="intake-<?= htmlspecialchars($def['key'], ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="off"
                            <?= $def['required'] ? 'required' : '' ?>
                          >
                        </label>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </fieldset>
              <?php endforeach; ?>

              <div class="protocol-intake__actions">
                <button class="access__btn protocol-intake__save" type="submit">Save progress</button>
                <button class="access__btn protocol-intake__complete" type="button" id="protocol-intake-complete">
                  Complete intake → Train
                </button>
              </div>
              <p class="protocol-intake__status" id="protocol-intake-status" hidden role="status"></p>
            </form>

          <?php elseif ($currentPhase === 'train'): ?>
            <div class="profile-studio" id="profile-studio">
              <?php if (!$geminiConfigured): ?>
                <p class="protocol-phase__soon protocol-phase__warn">
                  Add your free Gemini API key in <code>config.gemini.php</code>
                  (copy from <code>config.gemini.example.php</code>) — get one at
                  <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>.
                </p>
              <?php else: ?>
                <div class="profile-progress" aria-label="Profile completion">
                  <div class="profile-progress__head">
                    <span class="profile-progress__label">Profile map</span>
                    <span class="profile-progress__pct" id="profile-progress-pct"><?= (int) ($profileCompletion['percent'] ?? 0) ?>%</span>
                  </div>
                  <div class="profile-progress__bar">
                    <span class="profile-progress__fill" id="profile-progress-fill" style="width: <?= (int) ($profileCompletion['percent'] ?? 0) ?>%"></span>
                  </div>
                  <ul class="profile-progress__sections" id="profile-progress-sections">
                    <?php if (is_array($profileCompletion['sections'] ?? null)): ?>
                      <?php foreach ($profileCompletion['sections'] as $sec): ?>
                        <li><?= htmlspecialchars((string) $sec['label'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $sec['percent'] ?>%</li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                </div>

                <div class="profile-chat" id="profile-chat">
                  <div class="profile-chat__log" id="profile-chat-log" aria-live="polite" aria-label="Profile interview"></div>
                  <div class="profile-chat__typing" id="profile-chat-typing" hidden>
                    <span></span><span></span><span></span>
                  </div>
                  <form class="profile-chat__form" id="profile-chat-form">
                    <label class="visually-hidden" for="profile-chat-input">Your message</label>
                    <textarea
                      id="profile-chat-input"
                      rows="2"
                      placeholder="Reply to the agent…"
                      maxlength="4000"
                      required
                    ></textarea>
                    <button class="access__btn profile-chat__send" type="submit">Send</button>
                  </form>
                  <p class="profile-chat__status" id="profile-chat-status" hidden role="alert"></p>
                </div>
              <?php endif; ?>
            </div>

          <?php elseif ($currentPhase === 'choose'): ?>
            <div class="protocol-choose">
              <p class="protocol-choose__lead">This phase opens when training is complete.</p>
            </div>
          <?php endif; ?>

        </section>

        <div class="vault__actions">
          <form method="post" action="logout.php">
            <button class="btn-ghost" type="submit">End session</button>
          </form>
        </div>
      </main>
      <?php if (($currentPhase ?? '') === 'intake'): ?>
        <script src="assets/protocol-intake.js?v=1" defer></script>
      <?php endif; ?>
      <?php if (($currentPhase ?? '') === 'train' && !empty($geminiConfigured)): ?>
        <script src="assets/protocol-chat.js?v=2" defer></script>
      <?php endif; ?>
    <?php else: ?>
      <main class="vault">
        <p class="vault__badge">Studio // access granted</p>
        <h1 class="vault__title">Your model is ready to learn.</h1>
        <p class="vault__text">
          Code <strong style="color:var(--bone);font-weight:500"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></strong>
          opens this studio. Drop your traces — messages, voice, decisions —
          and the AI trains to become your copy.
        </p>

        <div class="console" aria-live="polite">
          <div><span class="dim">$</span> mycopy status</div>
          <div class="ok">● studio open</div>
          <div>auth …… <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></div>
          <div>since … <?= htmlspecialchars($stamp, ENT_QUOTES, 'UTF-8') ?></div>
          <div>node …… train-01.mycopy.digital</div>
          <div>state … IDLE — awaiting data</div>
          <div><span class="dim">$</span> <span class="cursor" aria-hidden="true"></span></div>
        </div>

        <div class="vault__actions">
          <form method="post" action="logout.php">
            <button class="btn-ghost" type="submit">End session</button>
          </form>
        </div>
      </main>
    <?php endif; ?>

    <footer class="footer">
      <span><?= SITE_NAME ?>.DIGITAL — private space</span>
      <span><?= $isFounder ? 'Founder protocol' : 'Do not share your code' ?></span>
    </footer>
  </div>
</body>
</html>
