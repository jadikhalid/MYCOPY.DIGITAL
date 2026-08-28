<?php
/**
 * Protocol intake — structured first-visit form + reset.
 */

declare(strict_types=1);

require_once __DIR__ . '/intake_schema.php';

const PROTOCOL_INTAKE_MAX_BYTES = 8_388_608;

function protocol_subject_data_dir(int $subjectId): string
{
    return __DIR__ . '/../data/protocol/subject_' . $subjectId;
}

/** @return array<string, mixed>|null */
function protocol_intake_get(int $subjectId): ?array
{
    $stmt = db()->prepare('SELECT * FROM protocol_intake WHERE subject_id = :sid LIMIT 1');
    $stmt->execute(['sid' => $subjectId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function protocol_intake_ensure_row(int $subjectId): void
{
    $existing = protocol_intake_get($subjectId);
    if ($existing) {
        return;
    }

    $now = gmdate('c');
    db()->prepare(
        'INSERT INTO protocol_intake (subject_id, updated_at) VALUES (:sid, :at)'
    )->execute(['sid' => $subjectId, 'at' => $now]);
}

/**
 * @param array<string, string> $fields
 * @return array{ok: bool, message?: string}
 */
function protocol_intake_save_fields(int $subjectId, array $fields): array
{
    protocol_intake_ensure_row($subjectId);

    $allowed = [];
    foreach (intake_field_definitions() as $def) {
        if ($def['type'] === 'text') {
            $allowed[$def['key']] = true;
        }
    }

    $sets = [];
    $params = ['sid' => $subjectId, 'at' => gmdate('c')];

    foreach ($fields as $key => $value) {
        if (!isset($allowed[$key])) {
            continue;
        }
        $sets[] = $key . ' = :' . $key;
        $params[$key] = trim($value);
    }

    if ($sets === []) {
        return ['ok' => true];
    }

    $sql = 'UPDATE protocol_intake SET ' . implode(', ', $sets) . ', updated_at = :at WHERE subject_id = :sid';
    db()->prepare($sql)->execute($params);

    return ['ok' => true];
}

/**
 * @return array{ok: bool, message?: string, slot?: string}
 */
function protocol_intake_save_photo(int $subjectId, string $slot, string $tmpPath): array
{
    if (!in_array($slot, intake_photo_slots(), true)) {
        return ['ok' => false, 'message' => 'Invalid photo slot.'];
    }

    if (!is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'message' => 'Invalid upload.'];
    }

    $size = filesize($tmpPath);
    if ($size === false || $size > PROTOCOL_INTAKE_MAX_BYTES) {
        return ['ok' => false, 'message' => 'Photo must be under 8 MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath) ?: '';
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '') {
        return ['ok' => false, 'message' => 'Use JPG, PNG, or WebP.'];
    }

    $dir = protocol_subject_data_dir($subjectId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Could not create storage folder.'];
    }

    $filename = $slot . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpPath, $dest) && !copy($tmpPath, $dest)) {
        return ['ok' => false, 'message' => 'Could not save photo.'];
    }

    $relative = 'data/protocol/subject_' . $subjectId . '/' . $filename;
    protocol_intake_ensure_row($subjectId);
    db()->prepare(
        'UPDATE protocol_intake SET ' . $slot . ' = :path, updated_at = :at WHERE subject_id = :sid'
    )->execute(['path' => $relative, 'at' => gmdate('c'), 'sid' => $subjectId]);

    // Legacy face reference on subject row (used by older capture code)
    if ($slot === 'photo_face') {
        db()->prepare(
            'UPDATE protocol_subjects SET capture_photo = :path, updated_at = :at WHERE id = :sid'
        )->execute(['path' => $relative, 'at' => gmdate('c'), 'sid' => $subjectId]);
    }

    return ['ok' => true, 'slot' => $slot];
}

function protocol_intake_photo_absolute_path(int $subjectId, string $slot): ?string
{
    $row = protocol_intake_get($subjectId);
    if (!$row || empty($row[$slot])) {
        return null;
    }

    $path = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, (string) $row[$slot]);

    return is_file($path) ? $path : null;
}

/**
 * @return array{ok: bool, message?: string}
 */
function protocol_intake_validate_complete(int $subjectId): array
{
    $row = protocol_intake_get($subjectId);
    if (!$row) {
        return ['ok' => false, 'message' => 'Intake form not started.'];
    }

    $missing = [];
    foreach (intake_field_definitions() as $def) {
        if (!$def['required']) {
            continue;
        }
        if ($def['type'] === 'photo') {
            if (protocol_intake_photo_absolute_path($subjectId, $def['key']) === null) {
                $missing[] = $def['label'];
            }
            continue;
        }
        $val = trim((string) ($row[$def['key']] ?? ''));
        if ($val === '') {
            $missing[] = $def['label'];
        }
    }

    if ($missing !== []) {
        return ['ok' => false, 'message' => 'Missing: ' . implode(', ', $missing) . '.'];
    }

    return ['ok' => true];
}

/**
 * @return array{ok: bool, message?: string, phase?: string}
 */
function protocol_intake_complete(int $subjectId): array
{
    $check = protocol_intake_validate_complete($subjectId);
    if (!$check['ok']) {
        return $check;
    }

    $now = gmdate('c');
    db()->prepare(
        'UPDATE protocol_intake SET completed_at = :at, updated_at = :at WHERE subject_id = :sid'
    )->execute(['at' => $now, 'sid' => $subjectId]);

    db()->prepare(
        'UPDATE protocol_subjects
         SET current_phase = \'train\', intake_completed_at = :at, updated_at = :at
         WHERE id = :sid'
    )->execute(['at' => $now, 'sid' => $subjectId]);

    // Seed profile fields from intake for the chat agent
    $row = protocol_intake_get($subjectId);
    if ($row) {
        require_once __DIR__ . '/../profile/Profiler.php';
        profile_upsert_field($subjectId, 'identity', 'full_name', trim(
            ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')
        ), 1.0);
        profile_upsert_field($subjectId, 'identity', 'preferred_name', (string) ($row['first_name'] ?? ''), 1.0);
        profile_upsert_field($subjectId, 'identity', 'age_range', (string) ($row['age'] ?? ''), 1.0);
    }

    return ['ok' => true, 'phase' => 'train', 'message' => 'Intake complete. Train phase unlocked.'];
}

/**
 * Wipe all protocol progress for a subject (founder reset / retest).
 */
function protocol_reset_subject(int $subjectId): void
{
    $pdo = db();

    $pdo->prepare('DELETE FROM protocol_messages WHERE subject_id = :sid')->execute(['sid' => $subjectId]);
    $pdo->prepare('DELETE FROM protocol_profile_fields WHERE subject_id = :sid')->execute(['sid' => $subjectId]);
    $pdo->prepare('DELETE FROM protocol_intake WHERE subject_id = :sid')->execute(['sid' => $subjectId]);

    $now = gmdate('c');
    $pdo->prepare(
        'UPDATE protocol_subjects
         SET current_phase = \'intake\',
             capture_photo = NULL,
             capture_confirmed_at = NULL,
             intake_completed_at = NULL,
             updated_at = :at
         WHERE id = :sid'
    )->execute(['at' => $now, 'sid' => $subjectId]);

    $dir = protocol_subject_data_dir($subjectId);
    if (is_dir($dir)) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    // Legacy founder folder
    $legacy = __DIR__ . '/../data/protocol/founder';
    if (is_dir($legacy)) {
        foreach (glob($legacy . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

function protocol_reset_founder(): void
{
    ensure_founder_protocol();
    $row = fetch_founder_protocol_row();
    if ($row) {
        protocol_reset_subject((int) $row['id']);
    }
}
