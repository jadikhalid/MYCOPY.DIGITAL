<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$flash = admin_flash_get();
$allEntries = fetch_waitlist_entries();

/** Waitlist = paid reservations only */
$entries = array_values(array_filter(
    $allEntries,
    static fn(array $entry): bool => ($entry['payment_status'] ?? '') === 'paid'
));

$waiting = 0;
$approved = 0;

foreach ($entries as $entry) {
    if (($entry['status'] ?? '') === 'approved') {
        $approved++;
    } else {
        $waiting++;
    }
}

$total = count($entries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — <?= SITE_NAME ?>.DIGITAL</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="page-admin">
  <main class="admin-shell admin-shell--wide">
    <header class="admin-header">
      <div>
        <p class="admin-kicker">MYCOPY admin</p>
        <h1 class="admin-title">Waitlist</h1>
      </div>
      <div class="admin-header__actions">
        <a class="admin-link" href="../index.php">Site</a>
        <form method="post" action="logout.php">
          <button class="btn-ghost" type="submit">Sign out</button>
        </form>
      </div>
    </header>

    <div class="admin-stats">
      <div class="admin-stat">
        <span class="admin-stat__label">Waiting</span>
        <strong><?= $waiting ?></strong>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__label">Approved</span>
        <strong><?= $approved ?></strong>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__label">Total</span>
        <strong><?= $total ?></strong>
      </div>
    </div>

    <?php if ($flash !== null): ?>
      <div class="admin-flash admin-flash--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($entries === []): ?>
      <p class="admin-empty">No waitlist signups yet.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Name</th>
              <th>Email</th>
              <th>Status</th>
              <th>Studio code</th>
              <th>Terms</th>
              <th>Signed up</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <?php
                $status = (string) ($entry['status'] ?? 'waiting');
                if ($status === 'pending_payment') {
                    $status = 'waiting';
                }
                $isApproved = $status === 'approved';
                $studioCode = (string) ($entry['studio_code'] ?? '');
                $termsAt = (string) ($entry['terms_accepted_at'] ?? '');
                $termsVersion = (string) ($entry['terms_version'] ?? '');
                $termsIp = (string) ($entry['terms_accepted_ip'] ?? '');
                $termsHash = (string) ($entry['terms_text_hash'] ?? '');
                $termsUa = (string) ($entry['terms_accepted_ua'] ?? '');
                $termsLang = (string) ($entry['terms_accepted_lang'] ?? '');
                $termsMethod = (string) ($entry['terms_accept_method'] ?? '');
                $termsRef = (string) ($entry['terms_accepted_referer'] ?? '');
                $termsArchive = (string) ($entry['terms_archive_path'] ?? '');
                $stripeSession = (string) ($entry['stripe_session_id'] ?? '');
                $termsSnapshot = (string) ($entry['terms_snapshot'] ?? '');
              ?>
              <tr>
                <td><code><?= htmlspecialchars((string) $entry['ticket'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars((string) $entry['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $entry['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <span class="admin-badge admin-badge--<?= $isApproved ? 'ok' : 'wait' ?>">
                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td>
                  <?php if ($studioCode !== ''): ?>
                    <code><?= htmlspecialchars($studioCode, ENT_QUOTES, 'UTF-8') ?></code>
                  <?php else: ?>
                    <span class="admin-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="admin-terms-cell">
                  <?php if ($termsAt !== ''): ?>
                    <details class="admin-terms">
                      <summary>
                        v<?= htmlspecialchars($termsVersion !== '' ? $termsVersion : '?', ENT_QUOTES, 'UTF-8') ?>
                        · <?= htmlspecialchars(admin_format_date($termsAt), ENT_QUOTES, 'UTF-8') ?>
                      </summary>
                      <dl class="admin-terms__dl">
                        <div><dt>IP</dt><dd><code><?= htmlspecialchars($termsIp !== '' ? $termsIp : '—', ENT_QUOTES, 'UTF-8') ?></code></dd></div>
                        <div><dt>Method</dt><dd><?= htmlspecialchars($termsMethod !== '' ? $termsMethod : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Hash</dt><dd><code class="admin-terms__hash"><?= htmlspecialchars($termsHash !== '' ? $termsHash : '—', ENT_QUOTES, 'UTF-8') ?></code></dd></div>
                        <div><dt>UA</dt><dd class="admin-terms__ua"><?= htmlspecialchars($termsUa !== '' ? $termsUa : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Lang</dt><dd><?= htmlspecialchars($termsLang !== '' ? $termsLang : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Referer</dt><dd class="admin-terms__ua"><?= htmlspecialchars($termsRef !== '' ? $termsRef : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Archive</dt><dd><code><?= htmlspecialchars($termsArchive !== '' ? $termsArchive : '—', ENT_QUOTES, 'UTF-8') ?></code></dd></div>
                        <div><dt>Stripe</dt><dd><code class="admin-terms__hash"><?= htmlspecialchars($stripeSession !== '' ? $stripeSession : '—', ENT_QUOTES, 'UTF-8') ?></code></dd></div>
                      </dl>
                      <?php if ($termsSnapshot !== ''): ?>
                        <pre class="admin-terms__snapshot"><?= htmlspecialchars($termsSnapshot, ENT_QUOTES, 'UTF-8') ?></pre>
                      <?php endif; ?>
                    </details>
                  <?php else: ?>
                    <span class="admin-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="admin-muted"><?= htmlspecialchars(admin_format_date((string) ($entry['paid_at'] ?: $entry['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <div class="admin-actions">
                    <?php if (!$isApproved): ?>
                      <form method="post" action="action.php" class="admin-inline-form">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                        <input type="hidden" name="action" value="grant">
                        <button class="access__btn admin-btn-sm" type="submit">Send code</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="action.php" class="admin-inline-form">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                        <input type="hidden" name="action" value="resend">
                        <button class="btn-ghost admin-btn-sm" type="submit">Resend</button>
                      </form>
                    <?php endif; ?>
                    <form
                      method="post"
                      action="action.php"
                      class="admin-inline-form"
                      onsubmit="return confirm('Delete this waitlist entry permanently?');"
                    >
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button class="btn-ghost admin-btn-sm admin-btn-danger" type="submit">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
