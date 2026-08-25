<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (is_authenticated()) {
    echo json_encode(['ok' => false, 'error' => 'Already authenticated.', 'redirect' => 'vault.php']);
    exit;
}

$result = check_waitlist_email((string) ($_POST['email'] ?? ''));

if (empty($result['ok'])) {
    http_response_code(409);
}

echo json_encode($result);
