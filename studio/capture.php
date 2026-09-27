<?php
/**
 * Studio Capture — subject CRUD + photo storage.
 */

declare(strict_types=1);

require_once __DIR__ . '/schema.php';

const STUDIO_PHOTO_MAX_BYTES = 8_388_608;

function studio_session_code(): string
{
    return strtoupper(trim((string) ($_SESSION['mycopy_code'] ?? '')));
}

function studio_session_waitlist_id(): ?int
{
    $id = $_SESSION['mycopy_waitlist_id'] ?? null;
    if ($id === null || $id === '') {
        return null;
    }

    return (int) $id;
}

function studio_subject_data_dir(int $subjectId): string
{
    return __DIR__ . '/../data/studio/subject_' . $subjectId;
}

/** @return array<string, mixed>|null */
function studio_subject_get_by_code(string $studioCode): ?array
{
    $code = strtoupper(trim($studioCode));
    if ($code === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM studio_subjects WHERE studio_code = :code LIMIT 1');
    $stmt->execute(['code' => $code]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return array<string, mixed>|null */
function studio_subject_for_session(): ?array
{
    if (!is_authenticated()) {
        return null;
    }

    $code = studio_session_code();
    if ($code === '') {
        return null;
    }

    return studio_subject_ensure($code, studio_session_waitlist_id());
}

/**
 * @return array<string, mixed>
 */
function studio_subject_ensure(string $studioCode, ?int $waitlistId = null): array
{
    $existing = studio_subject_get_by_code($studioCode);
    if ($existing) {
        if ($waitlistId !== null && empty($existing['waitlist_id'])) {
            db()->prepare(
                'UPDATE studio_subjects SET waitlist_id = :wid, updated_at = :at WHERE id = :id'
            )->execute([
                'wid' => $waitlistId,
                'at' => gmdate('c'),
                'id' => (int) $existing['id'],
            ]);
            $existing['waitlist_id'] = $waitlistId;
        }

        return $existing;
    }

    $now = gmdate('c');
    db()->prepare(
        'INSERT INTO studio_subjects (studio_code, waitlist_id, capture_step, created_at, updated_at)
         VALUES (:code, :wid, 1, :now, :now)'
    )->execute([
        'code' => strtoupper(trim($studioCode)),
        'wid' => $waitlistId,
        'now' => $now,
    ]);

    $row = studio_subject_get_by_code($studioCode);
    if (!$row) {
        throw new RuntimeException('Could not create studio subject.');
    }

    return $row;
}

function studio_subject_is_complete(?array $row): bool
{
    return $row !== null && !empty($row['completed_at']);
}

function studio_capture_current_step(array $row): int
{
    $step = (int) ($row['capture_step'] ?? 1);
    if ($step < 1) {
        return 1;
    }
    if ($step > STUDIO_CAPTURE_MAX_STEP) {
        return STUDIO_CAPTURE_MAX_STEP;
    }

    return $step;
}

/**
 * @return array{ok: bool, message?: string}
 */
function studio_capture_validate_step(int $subjectId, int $step): array
{
    $steps = studio_capture_steps();
    if (!isset($steps[$step])) {
        return ['ok' => false, 'message' => 'Invalid step.'];
    }

    // Confirmation screen has no field validation here
    if ($step === STUDIO_CAPTURE_MAX_STEP) {
        return studio_capture_validate_complete($subjectId);
    }

    $stmt = db()->prepare('SELECT * FROM studio_subjects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $subjectId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'message' => 'Subject not found.'];
    }

    $defsByKey = [];
    foreach (studio_capture_field_definitions() as $def) {
        $defsByKey[$def['key']] = $def;
    }

    $missing = [];
    foreach ($steps[$step]['keys'] as $key) {
        $def = $defsByKey[$key] ?? null;
        if ($def === null) {
            continue;
        }
        if ($def['type'] === 'photo') {
            if (studio_capture_photo_absolute_path($subjectId, $key) === null) {
                $missing[] = $def['label'];
            }
            continue;
        }
        $val = trim((string) ($row[$key] ?? ''));
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
 * Check submitted values for a step before anything is written.
 *
 * @param array<string, string> $textFields
 * @param list<string> $uploadedSlots
 * @return array{ok: bool, message?: string}
 */
function studio_capture_validate_posted_step(int $subjectId, int $step, array $textFields, array $uploadedSlots): array
{
    $steps = studio_capture_steps();
    if (!isset($steps[$step])) {
        return ['ok' => false, 'message' => 'Invalid step.'];
    }

    $defsByKey = [];
    foreach (studio_capture_field_definitions() as $def) {
        $defsByKey[$def['key']] = $def;
    }

    $missing = [];
    foreach ($steps[$step]['keys'] as $key) {
        $def = $defsByKey[$key] ?? null;
        if ($def === null || !$def['required']) {
            continue;
        }
        if ($def['type'] === 'photo') {
            if (!in_array($key, $uploadedSlots, true) && studio_capture_photo_absolute_path($subjectId, $key) === null) {
                $missing[] = $def['label'];
            }
            continue;
        }
        if (trim((string) ($textFields[$key] ?? '')) === '') {
            $missing[] = $def['label'];
        }
    }

    if ($missing !== []) {
        return ['ok' => false, 'message' => 'Missing: ' . implode(', ', $missing) . '.'];
    }

    return ['ok' => true];
}

/**
 * @return array{ok: bool, message?: string, step?: int}
 */
function studio_capture_set_step(int $subjectId, int $step): array
{
    if ($step < 1 || $step > STUDIO_CAPTURE_MAX_STEP) {
        return ['ok' => false, 'message' => 'Invalid step.'];
    }

    $stmt = db()->prepare('SELECT completed_at FROM studio_subjects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $subjectId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        return ['ok' => false, 'message' => 'Subject not found.'];
    }
    if (!empty($existing['completed_at'])) {
        return ['ok' => false, 'message' => 'Capture is locked.'];
    }

    db()->prepare(
        'UPDATE studio_subjects SET capture_step = :step, updated_at = :at WHERE id = :id'
    )->execute(['step' => $step, 'at' => gmdate('c'), 'id' => $subjectId]);

    return ['ok' => true, 'step' => $step];
}

/**
 * Save current step fields, validate, advance to next step.
 *
 * @return array{ok: bool, message?: string, step?: int}
 */
function studio_capture_continue(int $subjectId, int $fromStep): array
{
    $check = studio_capture_validate_step($subjectId, $fromStep);
    if (!$check['ok']) {
        return $check;
    }

    $next = min($fromStep + 1, STUDIO_CAPTURE_MAX_STEP);

    return studio_capture_set_step($subjectId, $next);
}

/**
 * @return array{ok: bool, message?: string, step?: int}
 */
function studio_capture_back(int $subjectId, int $fromStep): array
{
    $prev = max($fromStep - 1, 1);

    return studio_capture_set_step($subjectId, $prev);
}

/**
 * @param array<string, string> $fields
 * @return array{ok: bool, message?: string}
 */
function studio_capture_save_fields(int $subjectId, array $fields): array
{
    $row = db()->prepare('SELECT completed_at FROM studio_subjects WHERE id = :id LIMIT 1');
    $row->execute(['id' => $subjectId]);
    $existing = $row->fetch();
    if (!$existing) {
        return ['ok' => false, 'message' => 'Subject not found.'];
    }
    if (!empty($existing['completed_at'])) {
        return ['ok' => false, 'message' => 'Capture is locked. Fields cannot be changed.'];
    }

    $allowed = array_flip(studio_capture_text_keys());
    $sets = [];
    $params = ['id' => $subjectId, 'at' => gmdate('c')];

    foreach ($fields as $key => $value) {
        if (!isset($allowed[$key])) {
            continue;
        }
        $value = trim($value);
        if ($key === 'birth_date' && $value !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            $errors = DateTimeImmutable::getLastErrors();
            if (
                !$dt
                || ($errors['warning_count'] ?? 0) > 0
                || ($errors['error_count'] ?? 0) > 0
                || $dt->format('Y-m-d') !== $value
            ) {
                return ['ok' => false, 'message' => 'Invalid date of birth.'];
            }
            if ($dt > new DateTimeImmutable('today')) {
                return ['ok' => false, 'message' => 'Date of birth cannot be in the future.'];
            }
        }
        if ($key === 'character_text' && mb_strlen($value) > 2000) {
            return ['ok' => false, 'message' => 'Character text is too long (max 2000).'];
        }
        $sets[] = $key . ' = :' . $key;
        $params[$key] = $value;
    }

    if ($sets === []) {
        return ['ok' => true];
    }

    $sql = 'UPDATE studio_subjects SET ' . implode(', ', $sets) . ', updated_at = :at WHERE id = :id';
    db()->prepare($sql)->execute($params);

    return ['ok' => true];
}

/**
 * @return array{ok: bool, message?: string, slot?: string}
 */
function studio_capture_save_photo(int $subjectId, string $slot, string $tmpPath): array
{
    if (!in_array($slot, studio_capture_photo_slots(), true)) {
        return ['ok' => false, 'message' => 'Invalid photo slot.'];
    }

    $stmt = db()->prepare('SELECT completed_at FROM studio_subjects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $subjectId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        return ['ok' => false, 'message' => 'Subject not found.'];
    }
    if (!empty($existing['completed_at'])) {
        return ['ok' => false, 'message' => 'Capture is locked. Photos cannot be changed.'];
    }

    if (!is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'message' => 'Invalid upload.'];
    }

    $size = filesize($tmpPath);
    if ($size === false || $size > STUDIO_PHOTO_MAX_BYTES) {
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

    $dir = studio_subject_data_dir($subjectId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Could not create storage folder.'];
    }

    $filename = $slot . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpPath, $dest) && !copy($tmpPath, $dest)) {
        return ['ok' => false, 'message' => 'Could not save photo.'];
    }

    $relative = 'data/studio/subject_' . $subjectId . '/' . $filename;
    db()->prepare(
        'UPDATE studio_subjects SET ' . $slot . ' = :path, updated_at = :at WHERE id = :id'
    )->execute(['path' => $relative, 'at' => gmdate('c'), 'id' => $subjectId]);

    return ['ok' => true, 'slot' => $slot];
}

function studio_capture_photo_absolute_path(int $subjectId, string $slot): ?string
{
    if (!in_array($slot, studio_capture_photo_slots(), true)) {
        return null;
    }

    $stmt = db()->prepare('SELECT ' . $slot . ' AS path FROM studio_subjects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $subjectId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['path'])) {
        return null;
    }

    $path = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['path']);

    return is_file($path) ? $path : null;
}

/**
 * @return array{ok: bool, message?: string}
 */
function studio_capture_validate_complete(int $subjectId): array
{
    $stmt = db()->prepare('SELECT * FROM studio_subjects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $subjectId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'message' => 'Subject not found.'];
    }

    $missing = [];
    foreach (studio_capture_field_definitions() as $def) {
        if (!$def['required']) {
            continue;
        }
        if ($def['type'] === 'photo') {
            if (studio_capture_photo_absolute_path($subjectId, $def['key']) === null) {
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
 * @return array{ok: bool, message?: string}
 */
function studio_capture_complete(int $subjectId): array
{
    $check = studio_capture_validate_complete($subjectId);
    if (!$check['ok']) {
        return $check;
    }

    $stmt = db()->prepare('SELECT completed_at FROM studio_subjects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $subjectId]);
    $row = $stmt->fetch();
    if ($row && !empty($row['completed_at'])) {
        return ['ok' => true, 'message' => 'Capture already complete.'];
    }

    $now = gmdate('c');
    db()->prepare(
        'UPDATE studio_subjects SET completed_at = :at, capture_step = :step, updated_at = :at WHERE id = :id'
    )->execute(['at' => $now, 'step' => STUDIO_CAPTURE_MAX_STEP, 'id' => $subjectId]);

    return ['ok' => true, 'message' => 'Capture complete.'];
}

/**
 * @return array<string, string>
 */
function studio_capture_public_fields(array $row): array
{
    $out = [];
    foreach (studio_capture_text_keys() as $key) {
        $out[$key] = (string) ($row[$key] ?? '');
    }
    $subjectId = (int) ($row['id'] ?? 0);
    foreach (studio_capture_photo_slots() as $slot) {
        $out[$slot . '_url'] = $subjectId > 0 && studio_capture_photo_absolute_path($subjectId, $slot) !== null
            ? '/api/studio-photo.php?slot=' . rawurlencode($slot) . '&t=' . time()
            : '';
    }
    $out['completed'] = !empty($row['completed_at']) ? '1' : '0';
    $out['completed_at'] = (string) ($row['completed_at'] ?? '');
    $out['capture_step'] = (string) studio_capture_current_step($row);

    return $out;
}
