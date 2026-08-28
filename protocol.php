<?php
/**
 * MYCOPY Protocol processing — founder / subject state.
 */

declare(strict_types=1);

const PROTOCOL_PHASES = ['intake', 'train', 'choose'];
const PROTOCOL_FOUNDER_REF = 'founder';
const PROTOCOL_PHOTO_DIR = __DIR__ . '/data/protocol/founder';
const PROTOCOL_PHOTO_MAX_BYTES = 8_388_608;

/**
 * @return array<string, mixed>|null
 */
function fetch_founder_protocol_row(): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM protocol_subjects WHERE subject_ref = :ref LIMIT 1'
    );
    $stmt->execute(['ref' => PROTOCOL_FOUNDER_REF]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * @return array<string, mixed>|null
 */
function get_founder_protocol(): ?array
{
    if (!is_founder_session()) {
        return null;
    }

    return fetch_founder_protocol_row();
}

function ensure_founder_protocol(): void
{
    $code = founder_studio_code();
    if ($code === '') {
        return;
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id FROM protocol_subjects WHERE subject_ref = :ref LIMIT 1'
    );
    $stmt->execute(['ref' => PROTOCOL_FOUNDER_REF]);
    $existing = $stmt->fetch();

    $now = gmdate('c');

    if (!$existing) {
        $pdo->prepare(
            'INSERT INTO protocol_subjects (
                subject_kind, subject_ref, studio_code, display_name,
                current_phase, created_at, updated_at
             ) VALUES (
                \'founder\', :ref, :code, :name, \'intake\', :now, :now
             )'
        )->execute([
            'ref' => PROTOCOL_FOUNDER_REF,
            'code' => $code,
            'name' => 'Founder',
            'now' => $now,
        ]);
    } else {
        $pdo->prepare(
            'UPDATE protocol_subjects SET studio_code = :code, updated_at = :now WHERE subject_ref = :ref'
        )->execute(['code' => $code, 'now' => $now, 'ref' => PROTOCOL_FOUNDER_REF]);
    }
}

function protocol_phase_label(string $phase): string
{
    return match ($phase) {
        'intake' => 'Intake',
        'train' => 'Train',
        'choose' => 'Choose',
        'capture' => 'Capture',
        default => ucfirst($phase),
    };
}

function protocol_photo_relative_path(): string
{
    return 'data/protocol/founder/capture.png';
}

function protocol_photo_absolute_path(): string
{
    return PROTOCOL_PHOTO_DIR . '/capture.png';
}

/**
 * @return array{ok: bool, message?: string}
 */
function protocol_set_capture_photo(string $sourcePath, bool $fromUpload = true): array
{
    if (!is_founder_session()) {
        return ['ok' => false, 'message' => 'Not authorized.'];
    }

    if (!is_file($sourcePath)) {
        return ['ok' => false, 'message' => 'Photo file missing.'];
    }

    if ($fromUpload) {
        $size = filesize($sourcePath);
        if ($size === false || $size > PROTOCOL_PHOTO_MAX_BYTES) {
            return ['ok' => false, 'message' => 'Photo must be under 8 MB.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($sourcePath) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['ok' => false, 'message' => 'Use JPG, PNG, or WebP.'];
        }
    }

    if (!is_dir(PROTOCOL_PHOTO_DIR) && !mkdir(PROTOCOL_PHOTO_DIR, 0755, true) && !is_dir(PROTOCOL_PHOTO_DIR)) {
        return ['ok' => false, 'message' => 'Could not create storage folder.'];
    }

    $dest = protocol_photo_absolute_path();

    if ($fromUpload) {
        if (!is_uploaded_file($sourcePath)) {
            return ['ok' => false, 'message' => 'Invalid upload.'];
        }
        if (!move_uploaded_file($sourcePath, $dest) && !copy($sourcePath, $dest)) {
            return ['ok' => false, 'message' => 'Could not save photo.'];
        }
    } elseif (!copy($sourcePath, $dest)) {
        return ['ok' => false, 'message' => 'Could not save photo.'];
    }

    $now = gmdate('c');
    $rel = protocol_photo_relative_path();
    db()->prepare(
        'UPDATE protocol_subjects
         SET capture_photo = :photo, updated_at = :now, capture_confirmed_at = NULL
         WHERE subject_ref = :ref'
    )->execute(['photo' => $rel, 'now' => $now, 'ref' => PROTOCOL_FOUNDER_REF]);

    return ['ok' => true];
}

/**
 * @return array{ok: bool, message?: string, phase?: string}
 */
function protocol_confirm_capture(): array
{
    if (!is_founder_session()) {
        return ['ok' => false, 'message' => 'Not authorized.'];
    }

    $row = get_founder_protocol();
    if (!$row || empty($row['capture_photo']) || !is_file(protocol_photo_absolute_path())) {
        return ['ok' => false, 'message' => 'Upload a capture photo first.'];
    }

    $now = gmdate('c');
    db()->prepare(
        'UPDATE protocol_subjects
         SET capture_confirmed_at = :now, current_phase = \'train\', updated_at = :now
         WHERE subject_ref = :ref'
    )->execute(['now' => $now, 'ref' => PROTOCOL_FOUNDER_REF]);

    return ['ok' => true, 'phase' => 'train', 'message' => 'Capture confirmed. Train phase unlocked.'];
}

function protocol_has_capture_photo(): bool
{
    $row = get_founder_protocol();
    if (!$row || empty($row['capture_photo'])) {
        return false;
    }

    return is_file(protocol_photo_absolute_path());
}
