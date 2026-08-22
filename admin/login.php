<?php
require __DIR__ . '/bootstrap.php';

if (admin_is_authenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$needsSetup = !admin_is_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$needsSetup) {
    $password = (string) ($_POST['password'] ?? '');
    if (attempt_admin_login($password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin login — <?= SITE_NAME ?>.DIGITAL</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="page-admin">
  <main class="admin-shell">
    <p class="admin-kicker">MYCOPY admin</p>
    <h1 class="admin-title">Waitlist control</h1>

    <?php if ($needsSetup): ?>
      <p class="admin-lead">
        Admin is not configured yet. Copy <code>config.admin.example.php</code> to
        <code>config.admin.php</code> and set a <code>password_hash</code>.
      </p>
      <pre class="admin-code">php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"</pre>
    <?php else: ?>
      <p class="admin-lead">Sign in to review the waitlist and send studio access codes.</p>

      <form class="access admin-form" method="post" action="login.php" autocomplete="off">
        <label class="access__label" for="password">Admin password</label>
        <input
          class="access__input access__input--full"
          type="password"
          id="password"
          name="password"
          required
          autofocus
        >
        <div class="access__row access__row--end">
          <button class="access__btn" type="submit">Sign in</button>
        </div>
        <?php if ($error !== ''): ?>
          <p class="access__error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </form>
    <?php endif; ?>

    <p class="admin-back"><a href="../index.php">← Back to site</a></p>
  </main>
</body>
</html>
