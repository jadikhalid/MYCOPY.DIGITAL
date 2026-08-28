<?php
/**
 * Intake form field definitions (first studio visit).
 */

declare(strict_types=1);

/** @return list<array{key: string, label: string, type: string, required: bool, section: string}> */
function intake_field_definitions(): array
{
    return [
        ['section' => 'identity', 'key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'required' => true],
        ['section' => 'identity', 'key' => 'last_name', 'label' => 'Last name', 'type' => 'text', 'required' => true],
        ['section' => 'identity', 'key' => 'age', 'label' => 'Age', 'type' => 'text', 'required' => true],

        ['section' => 'parents', 'key' => 'father_first_name', 'label' => "Father's first name", 'type' => 'text', 'required' => true],
        ['section' => 'parents', 'key' => 'father_last_name', 'label' => "Father's last name", 'type' => 'text', 'required' => true],
        ['section' => 'parents', 'key' => 'mother_first_name', 'label' => "Mother's first name", 'type' => 'text', 'required' => true],
        ['section' => 'parents', 'key' => 'mother_last_name', 'label' => "Mother's last name", 'type' => 'text', 'required' => true],

        ['section' => 'photos', 'key' => 'photo_face', 'label' => 'Face (front)', 'type' => 'photo', 'required' => true],
        ['section' => 'photos', 'key' => 'photo_profile_left', 'label' => 'Profile (left)', 'type' => 'photo', 'required' => true],
        ['section' => 'photos', 'key' => 'photo_profile_right', 'label' => 'Profile (right)', 'type' => 'photo', 'required' => true],
    ];
}

/** @return list<string> */
function intake_photo_slots(): array
{
    return ['photo_face', 'photo_profile_left', 'photo_profile_right'];
}
