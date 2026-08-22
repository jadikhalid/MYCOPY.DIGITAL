<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$flash = admin_flash_get();
$entries = fetch_waitlist_entries();
$waiting = 0;
$approved = 0;

foreach ($entries as $entry) {
    if (($entry['status'] ?? '') === 'approved') {
        $approved++;
    } else {
        $waiting++;
    }
}
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
        <strong><?= count($entries) ?></strong>
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
              <th>Signed up</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <?php
                $status = (string) ($entry['status'] ?? 'waiting');
                $isApproved = $status === 'approved';
                $studioCode = (string) ($entry['studio_code'] ?? '');
              ?>
              <tr>
                <td><code><?= htmlspecialchars((string) $entry['ticket'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars((string) $entry['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $entry['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <span class="admin-badge admin-badge--<?= $isApproved ? 'ok' : 'wait' ?>">
                    <?= $isApproved ? 'approved' : 'waiting' ?>
                  </span>
                </td>
                <td>
                  <?php if ($studioCode !== ''): ?>
                    <code><?= htmlspecialchars($studioCode, ENT_QUOTES, 'UTF-8') ?></code>
                  <?php else: ?>
                    <span class="admin-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="admin-muted"><?= htmlspecialchars(admin_format_date((string) $entry['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
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
