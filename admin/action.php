<?php
require __DIR__ . '/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$csrf = (string) ($_POST['csrf'] ?? '');
if (!admin_verify_csrf($csrf)) {
    admin_flash_set('error', 'Invalid request. Please try again.');
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($id <= 0) {
    admin_flash_set('error', 'Invalid waitlist entry.');
    header('Location: index.php');
    exit;
}

if ($action === 'grant') {
    $result = grant_studio_access($id, false);
} elseif ($action === 'resend') {
    $result = grant_studio_access($id, true);
} else {
    admin_flash_set('error', 'Unknown action.');
    header('Location: index.php');
    exit;
}

admin_flash_set($result['ok'] ? 'success' : 'error', $result['message']);
header('Location: index.php');
exit;
