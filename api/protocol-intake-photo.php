<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../protocol.php';
require __DIR__ . '/../protocol/intake.php';

if (!is_authenticated() || !is_founder_session()) {
    http_response_code(403);
    exit;
}

$subjectId = protocol_subject_id_for_session();
if ($subjectId === null) {
    http_response_code(404);
    exit;
}

$slot = (string) ($_GET['slot'] ?? '');
if (!in_array($slot, intake_photo_slots(), true)) {
    http_response_code(400);
    exit;
}

$path = protocol_intake_photo_absolute_path($subjectId, $slot);
if ($path === null) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, no-store');
readfile($path);
