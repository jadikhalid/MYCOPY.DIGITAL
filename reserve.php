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

$result = start_checkout_reservation(
    (string) ($_POST['name'] ?? ''),
    (string) ($_POST['email'] ?? '')
);

if (!empty($result['ok']) && !empty($result['checkout_url'])) {
    header('Location: ' . $result['checkout_url']);
    exit;
}

$_SESSION['toast'] = $result['toast'] ?? [
    'type' => 'error',
    'title' => 'Checkout failed',
    'message' => 'Could not start payment.',
];

if (!empty($result['keep_form'])) {
    $_SESSION['flash_form'] = [
        'name' => (string) ($_POST['name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
    ];
}

header('Location: attente.php');
exit;
