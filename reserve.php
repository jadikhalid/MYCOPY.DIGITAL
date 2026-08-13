<?php
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

if (is_authenticated()) {
    header('Location: vault.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: attente.php');
    exit;
}

$result = reserve_place(
    (string) ($_POST['name'] ?? ''),
    (string) ($_POST['email'] ?? '')
);

$_SESSION['toast'] = $result['toast'];

if (!empty($result['keep_form'])) {
    $_SESSION['flash_form'] = [
        'name' => (string) ($_POST['name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
    ];
}

header('Location: attente.php');
exit;
