<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../protocol.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!is_authenticated() || !is_founder_session()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Founder access required.']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'confirm') {
    $result = protocol_confirm_capture();
    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['message'] ?? 'Could not confirm.']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'phase' => $result['phase'] ?? 'train',
        'message' => $result['message'] ?? 'Capture confirmed.',
    ]);
    exit;
}

if ($action !== 'upload' || !isset($_FILES['photo'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing photo upload.']);
    exit;
}

$file = $_FILES['photo'];
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Upload failed. Try again.']);
    exit;
}

$tmp = (string) ($file['tmp_name'] ?? '');
$result = protocol_set_capture_photo($tmp, true);

if (!$result['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $result['message'] ?? 'Could not save photo.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Photo updated.',
    'photo_url' => '/api/protocol-photo.php?t=' . time(),
]);
