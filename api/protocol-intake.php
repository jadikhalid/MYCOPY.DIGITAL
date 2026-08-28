<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../protocol.php';
require __DIR__ . '/../protocol/intake.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_authenticated() || !is_founder_session()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Founder studio access required.']);
    exit;
}

$subjectId = protocol_subject_id_for_session();
if ($subjectId === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Protocol subject not found.']);
    exit;
}

$row = fetch_founder_protocol_row();
if (($row['current_phase'] ?? '') !== 'intake') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Intake form is only available in the Intake phase.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    protocol_intake_ensure_row($subjectId);
    $intake = protocol_intake_get($subjectId) ?? [];
    $out = [];
    foreach (intake_field_definitions() as $def) {
        if ($def['type'] === 'text') {
            $out[$def['key']] = (string) ($intake[$def['key']] ?? '');
        }
    }
    foreach (intake_photo_slots() as $slot) {
        $out[$slot . '_url'] = protocol_intake_photo_absolute_path($subjectId, $slot)
            ? '/api/protocol-intake-photo.php?slot=' . rawurlencode($slot) . '&t=' . time()
            : '';
    }
    echo json_encode(['ok' => true, 'fields' => $out]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$action = (string) ($_POST['action'] ?? 'save');

$textFields = [];
foreach (intake_field_definitions() as $def) {
    if ($def['type'] === 'text' && isset($_POST[$def['key']])) {
        $textFields[$def['key']] = (string) $_POST[$def['key']];
    }
}
protocol_intake_save_fields($subjectId, $textFields);

foreach (intake_photo_slots() as $slot) {
    if (!isset($_FILES[$slot]) || !is_array($_FILES[$slot])) {
        continue;
    }
    if (($_FILES[$slot]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }
    $tmp = (string) ($_FILES[$slot]['tmp_name'] ?? '');
    protocol_intake_save_photo($subjectId, $slot, $tmp);
}

if ($action === 'complete') {
    $result = protocol_intake_complete($subjectId);
    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['message'] ?? 'Could not complete intake.']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'phase' => $result['phase'] ?? 'train',
        'message' => $result['message'] ?? 'Intake complete.',
    ]);
    exit;
}

$intake = protocol_intake_get($subjectId) ?? [];
$out = [];
foreach (intake_field_definitions() as $def) {
    if ($def['type'] === 'text') {
        $out[$def['key']] = (string) ($intake[$def['key']] ?? '');
    }
}
foreach (intake_photo_slots() as $slot) {
    $out[$slot . '_url'] = protocol_intake_photo_absolute_path($subjectId, $slot)
        ? '/api/protocol-intake-photo.php?slot=' . rawurlencode($slot) . '&t=' . time()
        : '';
}

echo json_encode(['ok' => true, 'fields' => $out, 'message' => 'Saved.']);
