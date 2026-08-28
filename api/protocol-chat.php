<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../protocol.php';
require __DIR__ . '/../profile/Profiler.php';

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
if (($row['current_phase'] ?? '') !== 'train') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Profile chat is available in the Train phase.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $state = profile_chat_state($subjectId);
    if (!$state['ok']) {
        http_response_code(503);
    }
    echo json_encode($state);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$message = trim((string) ($body['message'] ?? ''));

$result = profile_chat_turn($subjectId, $message);
if (!$result['ok']) {
    http_response_code($result['error'] && str_contains((string) $result['error'], 'Gemini') ? 503 : 400);
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Chat failed.']);
    exit;
}

$messages = profile_fetch_messages($subjectId);

echo json_encode([
    'ok' => true,
    'reply' => $result['reply'],
    'messages' => array_map(static function (array $m): array {
        return [
            'id' => (int) $m['id'],
            'role' => (string) $m['role'],
            'content' => (string) $m['content'],
            'created_at' => (string) $m['created_at'],
        ];
    }, $messages),
    'completion' => $result['completion'] ?? profile_completion($subjectId),
]);
