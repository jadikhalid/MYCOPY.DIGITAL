<?php
/**
 * Level-1 profile builder — chat history, field storage, Gemini interviewer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../ai/GeminiClient.php';
require_once __DIR__ . '/../protocol.php';
require_once __DIR__ . '/schema.php';

function protocol_subject_id_for_session(): ?int
{
    if (!is_founder_session()) {
        return null;
    }

    ensure_founder_protocol();
    $row = fetch_founder_protocol_row();

    return $row ? (int) $row['id'] : null;
}

/**
 * @return list<array{id: int, role: string, content: string, created_at: string}>
 */
function profile_fetch_messages(int $subjectId, int $limit = 50): array
{
    $stmt = db()->prepare(
        'SELECT id, role, content, created_at
         FROM protocol_messages
         WHERE subject_id = :sid
         ORDER BY id ASC
         LIMIT :lim'
    );
    $stmt->bindValue('sid', $subjectId, PDO::PARAM_INT);
    $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function profile_message_count(int $subjectId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM protocol_messages WHERE subject_id = :sid');
    $stmt->execute(['sid' => $subjectId]);

    return (int) $stmt->fetchColumn();
}

function profile_insert_message(int $subjectId, string $role, string $content): int
{
    $stmt = db()->prepare(
        'INSERT INTO protocol_messages (subject_id, role, content, created_at)
         VALUES (:sid, :role, :content, :at)'
    );
    $stmt->execute([
        'sid' => $subjectId,
        'role' => $role,
        'content' => $content,
        'at' => gmdate('c'),
    ]);

    return (int) db()->lastInsertId();
}

/** @return array<string, array<string, array{value: string, confidence: float, updated_at: string}>> */
function profile_fetch_fields(int $subjectId): array
{
    $stmt = db()->prepare(
        'SELECT section, field_key, value, confidence, updated_at
         FROM protocol_profile_fields
         WHERE subject_id = :sid'
    );
    $stmt->execute(['sid' => $subjectId]);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $section = (string) $row['section'];
        $key = (string) $row['field_key'];
        if (!isset($out[$section])) {
            $out[$section] = [];
        }
        $out[$section][$key] = [
            'value' => (string) $row['value'],
            'confidence' => (float) $row['confidence'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    return $out;
}

function profile_upsert_field(
    int $subjectId,
    string $section,
    string $fieldKey,
    string $value,
    float $confidence = 0.8
): void {
    $value = trim($value);
    if ($value === '') {
        return;
    }

    $defs = profile_field_definitions();
    $valid = false;
    foreach ($defs as $def) {
        if ($def['section'] === $section && $def['key'] === $fieldKey) {
            $valid = true;
            break;
        }
    }
    if (!$valid) {
        return;
    }

    $now = gmdate('c');
    db()->prepare(
        'INSERT INTO protocol_profile_fields (subject_id, section, field_key, value, confidence, updated_at)
         VALUES (:sid, :section, :key, :value, :conf, :at)
         ON CONFLICT(subject_id, section, field_key) DO UPDATE SET
           value = excluded.value,
           confidence = excluded.confidence,
           updated_at = excluded.updated_at'
    )->execute([
        'sid' => $subjectId,
        'section' => $section,
        'key' => $fieldKey,
        'value' => $value,
        'conf' => max(0.0, min(1.0, $confidence)),
        'at' => $now,
    ]);
}

/** @return array{percent: int, filled: int, total: int, sections: array<string, array{label: string, percent: int, filled: int, total: int}>} */
function profile_completion(int $subjectId): array
{
    $fields = profile_fetch_fields($subjectId);
    $defs = profile_field_definitions();
    $sectionLabels = profile_section_labels();

    $total = count($defs);
    $filled = 0;
    $sectionStats = [];

    foreach ($sectionLabels as $section => $label) {
        $sectionStats[$section] = ['label' => $label, 'percent' => 0, 'filled' => 0, 'total' => 0];
    }

    foreach ($defs as $def) {
        $section = $def['section'];
        $key = $def['key'];
        $sectionStats[$section]['total']++;
        $has = isset($fields[$section][$key]) && trim($fields[$section][$key]['value']) !== '';
        if ($has) {
            $filled++;
            $sectionStats[$section]['filled']++;
        }
    }

    foreach ($sectionStats as $section => &$stat) {
        $stat['percent'] = $stat['total'] > 0
            ? (int) round(($stat['filled'] / $stat['total']) * 100)
            : 0;
    }
    unset($stat);

    return [
        'percent' => $total > 0 ? (int) round(($filled / $total) * 100) : 0,
        'filled' => $filled,
        'total' => $total,
        'sections' => $sectionStats,
    ];
}

function profile_summary_for_prompt(int $subjectId): string
{
    $fields = profile_fetch_fields($subjectId);
    if ($fields === []) {
        return 'Profile is empty — start with identity and voice.';
    }

    $lines = ['Known profile facts:'];
    foreach (profile_field_definitions() as $def) {
        $val = $fields[$def['section']][$def['key']]['value'] ?? '';
        if ($val !== '') {
            $lines[] = '- ' . $def['section_label'] . ' / ' . $def['label'] . ': ' . $val;
        }
    }

    $completion = profile_completion($subjectId);
    $lines[] = '';
    $lines[] = 'Completion: ' . $completion['filled'] . '/' . $completion['total'] . ' fields.';

    $missing = [];
    foreach (profile_field_definitions() as $def) {
        $val = $fields[$def['section']][$def['key']]['value'] ?? '';
        if ($val === '') {
            $missing[] = $def['section'] . '.' . $def['key'] . ' (' . $def['label'] . ')';
        }
    }
    if ($missing !== []) {
        $lines[] = 'Priority gaps (pick one naturally next): ' . implode('; ', array_slice($missing, 0, 8));
    }

    return implode("\n", $lines);
}

function profile_next_gap_hint(int $subjectId): string
{
    foreach (profile_field_definitions() as $def) {
        $fields = profile_fetch_fields($subjectId);
        $val = $fields[$def['section']][$def['key']]['value'] ?? '';
        if ($val === '') {
            return $def['label'] . ' — ' . $def['hint'];
        }
    }

    return 'deepen any area that feels incomplete';
}

function profile_build_system_instruction(int $subjectId): string
{
    $gap = profile_next_gap_hint($subjectId);
    $summary = profile_summary_for_prompt($subjectId);

    $fieldList = [];
    foreach (profile_field_definitions() as $def) {
        $fieldList[] = $def['section'] . '.' . $def['key'];
    }

    return <<<PROMPT
You are the MYCOPY Profile Agent — a warm, curious interviewer building a Level-1 digital copy of this person.

Rules:
- One or two short questions at a time. Never a long questionnaire.
- Sound alive: react to what they said, use their words, gentle humor when fitting.
- Match their language (French or English).
- Gently steer toward gaps in the profile; current priority: {$gap}
- If they share something rich, acknowledge it before moving on.
- Never invent facts. Only extract what they stated or clearly implied.
- You may invite uploads (photos, PDFs, notes) when relevant — say they can attach later in chat.

{$summary}

Allowed extraction keys (section.key only): 
PROMPT . implode(', ', $fieldList) . <<<'PROMPT'


Respond with VALID JSON ONLY (no markdown), shape:
{
  "reply": "your conversational message to the user",
  "extractions": [
    {"section": "identity", "key": "preferred_name", "value": "…", "confidence": 0.9}
  ]
}
Use extractions only for clear new or updated facts from this turn. confidence 0.5–1.0.
PROMPT;
}

/**
 * @return list<array{role: string, parts: list<array{text: string}>}>
 */
function profile_gemini_contents(int $subjectId, ?string $userMessage = null): array
{
    $max = (int) (gemini_config()['max_history_messages'] ?? 24);
    $messages = profile_fetch_messages($subjectId);
    if (count($messages) > $max) {
        $messages = array_slice($messages, -$max);
    }

    $contents = [];
    foreach ($messages as $msg) {
        $role = (string) $msg['role'];
        if ($role === 'assistant') {
            $geminiRole = 'model';
        } elseif ($role === 'user') {
            $geminiRole = 'user';
        } else {
            continue;
        }
        $contents[] = [
            'role' => $geminiRole,
            'parts' => [['text' => (string) $msg['content']]],
        ];
    }

    if ($userMessage !== null && trim($userMessage) !== '') {
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => trim($userMessage)]],
        ];
    }

    return $contents;
}

/**
 * @return array{ok: bool, reply?: string, error?: string, completion?: array<string, mixed>}
 */
function profile_ensure_opening(int $subjectId): array
{
    if (profile_message_count($subjectId) > 0) {
        return ['ok' => true, 'reply' => ''];
    }

    return profile_chat_turn($subjectId, null, true);
}

/**
 * @return array{ok: bool, reply?: string, error?: string, completion?: array<string, mixed>}
 */
function profile_chat_turn(int $subjectId, ?string $userMessage, bool $openingOnly = false): array
{
    if (!gemini_is_configured()) {
        return [
            'ok' => false,
            'error' => 'Gemini is not configured. Add config.gemini.php with your free API key from Google AI Studio.',
        ];
    }

    $userText = $userMessage !== null ? trim($userMessage) : '';
    if (!$openingOnly && $userText === '') {
        return ['ok' => false, 'error' => 'Message cannot be empty.'];
    }

    if (!$openingOnly) {
        profile_insert_message($subjectId, 'user', $userText);
    }

    $contents = profile_gemini_contents($subjectId, $openingOnly ? null : null);
    if ($openingOnly) {
        $contents = [[
            'role' => 'user',
            'parts' => [['text' => 'Begin the profile interview. Introduce yourself briefly as the MYCOPY Profile Agent and ask your first warm question about who they are.']],
        ]];
    }

    $system = profile_build_system_instruction($subjectId);
    $result = gemini_generate_content($contents, $system, [
        'temperature' => 0.85,
        'maxOutputTokens' => 1024,
        'responseMimeType' => 'application/json',
    ]);

    if (!$result['ok']) {
        return $result;
    }

    $parsed = gemini_parse_json_text((string) $result['text']);
    if ($parsed === null || empty($parsed['reply'])) {
        return ['ok' => false, 'error' => 'Could not parse agent response. Try again.'];
    }

    $reply = trim((string) $parsed['reply']);
    profile_insert_message($subjectId, 'assistant', $reply);

    $extractions = $parsed['extractions'] ?? [];
    if (is_array($extractions)) {
        foreach ($extractions as $item) {
            if (!is_array($item)) {
                continue;
            }
            $section = (string) ($item['section'] ?? '');
            $key = (string) ($item['key'] ?? '');
            $value = (string) ($item['value'] ?? '');
            $conf = (float) ($item['confidence'] ?? 0.75);
            profile_upsert_field($subjectId, $section, $key, $value, $conf);
        }
    }

    return [
        'ok' => true,
        'reply' => $reply,
        'completion' => profile_completion($subjectId),
    ];
}

/**
 * @return array{ok: bool, messages?: list<array<string, mixed>>, completion?: array<string, mixed>, error?: string}
 */
function profile_chat_state(int $subjectId): array
{
    $open = profile_ensure_opening($subjectId);
    if (!$open['ok']) {
        return $open;
    }

    $messages = profile_fetch_messages($subjectId);

    return [
        'ok' => true,
        'messages' => array_map(static function (array $m): array {
            return [
                'id' => (int) $m['id'],
                'role' => (string) $m['role'],
                'content' => (string) $m['content'],
                'created_at' => (string) $m['created_at'],
            ];
        }, $messages),
        'completion' => profile_completion($subjectId),
    ];
}
