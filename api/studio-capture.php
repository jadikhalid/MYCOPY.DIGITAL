<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../studio/capture.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_authenticated()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Studio access required.']);
    exit;
}

$subject = studio_subject_for_session();
if ($subject === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Studio subject not found.']);
    exit;
}

$subjectId = (int) $subject['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fresh = studio_subject_get_by_code(studio_session_code()) ?? $subject;
    echo json_encode([
        'ok' => true,
        'fields' => studio_capture_public_fields($fresh),
        'step' => studio_capture_current_step($fresh),
        'max_step' => STUDIO_CAPTURE_MAX_STEP,
        'complete' => studio_subject_is_complete($fresh),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (studio_subject_is_complete($subject)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Capture is locked.']);
    exit;
}

$action = (string) ($_POST['action'] ?? 'continue');
$currentStep = studio_capture_current_step($subject);

if ($action === 'back') {
    $result = studio_capture_back($subjectId, $currentStep);
    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['message'] ?? 'Could not go back.']);
        exit;
    }
    $fresh = studio_subject_get_by_code(studio_session_code()) ?? $subject;
    echo json_encode([
        'ok' => true,
        'step' => studio_capture_current_step($fresh),
        'max_step' => STUDIO_CAPTURE_MAX_STEP,
        'fields' => studio_capture_public_fields($fresh),
        'complete' => false,
        'message' => 'Back.',
    ]);
    exit;
}

// For continue / complete: save posted fields + photos for current step
$textFields = [];
foreach (studio_capture_text_keys() as $key) {
    if (isset($_POST[$key])) {
        $textFields[$key] = (string) $_POST[$key];
    }
}

if ($action === 'continue') {
    $uploadedSlots = [];
    foreach (studio_capture_photo_slots() as $slot) {
        if (($_FILES[$slot]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadedSlots[] = $slot;
        }
    }
    $precheck = studio_capture_validate_posted_step($subjectId, $currentStep, $textFields, $uploadedSlots);
    if (!$precheck['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $precheck['message'] ?? 'Invalid input.']);
        exit;
    }
}

$save = studio_capture_save_fields($subjectId, $textFields);
if (!$save['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $save['message'] ?? 'Could not save.']);
    exit;
}

foreach (studio_capture_photo_slots() as $slot) {
    if (!isset($_FILES[$slot]) || !is_array($_FILES[$slot])) {
        continue;
    }
    if (($_FILES[$slot]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }
    $tmp = (string) ($_FILES[$slot]['tmp_name'] ?? '');
    $photo = studio_capture_save_photo($subjectId, $slot, $tmp);
    if (!$photo['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $photo['message'] ?? 'Photo upload failed.']);
        exit;
    }
}

if ($action === 'save') {
    $fresh = studio_subject_get_by_code(studio_session_code()) ?? $subject;
    echo json_encode([
        'ok' => true,
        'step' => studio_capture_current_step($fresh),
        'max_step' => STUDIO_CAPTURE_MAX_STEP,
        'fields' => studio_capture_public_fields($fresh),
        'complete' => false,
        'message' => 'Saved.',
    ]);
    exit;
}

if ($action === 'complete') {
    if ($currentStep !== STUDIO_CAPTURE_MAX_STEP) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Finish all steps before confirming.']);
        exit;
    }
    $confirmed = isset($_POST['confirm_lock']) && (string) $_POST['confirm_lock'] === '1';
    if (!$confirmed) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please confirm that the information is correct.']);
        exit;
    }
    $result = studio_capture_complete($subjectId);
    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['message'] ?? 'Could not complete capture.']);
        exit;
    }
    $fresh = studio_subject_get_by_code(studio_session_code()) ?? $subject;
    echo json_encode([
        'ok' => true,
        'complete' => true,
        'step' => STUDIO_CAPTURE_MAX_STEP,
        'max_step' => STUDIO_CAPTURE_MAX_STEP,
        'fields' => studio_capture_public_fields($fresh),
        'message' => $result['message'] ?? 'Capture complete.',
    ]);
    exit;
}

// continue (default)
$result = studio_capture_continue($subjectId, $currentStep);
if (!$result['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $result['message'] ?? 'Could not continue.']);
    exit;
}

$fresh = studio_subject_get_by_code(studio_session_code()) ?? $subject;
echo json_encode([
    'ok' => true,
    'complete' => false,
    'step' => studio_capture_current_step($fresh),
    'max_step' => STUDIO_CAPTURE_MAX_STEP,
    'fields' => studio_capture_public_fields($fresh),
    'message' => 'Saved.',
]);
