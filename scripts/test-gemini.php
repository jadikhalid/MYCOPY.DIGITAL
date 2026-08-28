<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../protocol.php';
require __DIR__ . '/../profile/Profiler.php';

ensure_founder_protocol();
$row = fetch_founder_protocol_row();
$id = (int) $row['id'];

echo "phase=" . ($row['current_phase'] ?? '?') . PHP_EOL;
echo "gemini_configured=" . (gemini_is_configured() ? 'yes' : 'no') . PHP_EOL;
echo "messages=" . profile_message_count($id) . PHP_EOL;

$test = gemini_generate_content(
    [['role' => 'user', 'parts' => [['text' => 'Say hi in JSON: {"reply":"hello"}']]]],
    'Reply with JSON only',
    ['responseMimeType' => 'application/json']
);
echo "gemini_test=" . json_encode($test, JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (($row['current_phase'] ?? '') === 'train') {
    $state = profile_chat_state($id);
    echo "chat_state=" . json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}
