<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (is_authenticated()) {
    echo json_encode(['ok' => true, 'redirect' => route_url('studio')]);
    exit;
}

$code = (string) ($_POST['code'] ?? '');

if (attempt_login($code)) {
    echo json_encode(['ok' => true, 'redirect' => route_url('studio')]);
    exit;
}

http_response_code(401);
echo json_encode(['ok' => false, 'error' => 'Invalid code. Access denied.']);
