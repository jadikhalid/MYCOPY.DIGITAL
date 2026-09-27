<?php
/**
 * Studio Capture — field definitions (phase 1).
 */

declare(strict_types=1);

const STUDIO_CAPTURE_MAX_STEP = 5;

/** @return list<array{key: string, label: string, type: string, required: bool, section: string, max?: int}> */
function studio_capture_field_definitions(): array
{
    return [
        ['section' => 'identity', 'key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'required' => true, 'max' => 80],
        ['section' => 'identity', 'key' => 'last_name', 'label' => 'Last name', 'type' => 'text', 'required' => true, 'max' => 80],
        ['section' => 'identity', 'key' => 'birth_date', 'label' => 'Date of birth', 'type' => 'date', 'required' => true],

        ['section' => 'character', 'key' => 'character_text', 'label' => 'Character', 'type' => 'textarea', 'required' => true, 'max' => 2000],

        ['section' => 'photos', 'key' => 'photo_face', 'label' => 'Face (front)', 'type' => 'photo', 'required' => true],
        ['section' => 'photos', 'key' => 'photo_profile_left', 'label' => 'Profile (left)', 'type' => 'photo', 'required' => true],
        ['section' => 'photos', 'key' => 'photo_profile_right', 'label' => 'Profile (right)', 'type' => 'photo', 'required' => true],
    ];
}

/**
 * Wizard steps: 1–4 collect data, 5 = confirmation only.
 *
 * @return array<int, array{id: int, label: string, keys: list<string>}>
 */
function studio_capture_steps(): array
{
    return [
        1 => ['id' => 1, 'label' => 'Name', 'keys' => ['first_name', 'last_name']],
        2 => ['id' => 2, 'label' => 'Birth', 'keys' => ['birth_date']],
        3 => ['id' => 3, 'label' => 'Character', 'keys' => ['character_text']],
        4 => ['id' => 4, 'label' => 'Photos', 'keys' => ['photo_face', 'photo_profile_left', 'photo_profile_right']],
        5 => ['id' => 5, 'label' => 'Confirm', 'keys' => []],
    ];
}

/** @return list<string> */
function studio_capture_photo_slots(): array
{
    return ['photo_face', 'photo_profile_left', 'photo_profile_right'];
}

/** @return list<string> */
function studio_capture_text_keys(): array
{
    $keys = [];
    foreach (studio_capture_field_definitions() as $def) {
        if (in_array($def['type'], ['text', 'date', 'textarea'], true)) {
            $keys[] = $def['key'];
        }
    }

    return $keys;
}
