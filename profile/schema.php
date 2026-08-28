<?php
/**
 * Level-1 digital copy — profile field schema (themes for the interviewer agent).
 */

declare(strict_types=1);

/**
 * @return list<array{
 *   section: string,
 *   section_label: string,
 *   key: string,
 *   label: string,
 *   hint: string
 * }>
 */
function profile_field_definitions(): array
{
    return [
        // 01 Identity
        ['section' => 'identity', 'section_label' => 'Identity', 'key' => 'full_name', 'label' => 'Full name', 'hint' => 'Legal or preferred full name'],
        ['section' => 'identity', 'section_label' => 'Identity', 'key' => 'preferred_name', 'label' => 'What to call you', 'hint' => 'Nickname or how you introduce yourself'],
        ['section' => 'identity', 'section_label' => 'Identity', 'key' => 'age_range', 'label' => 'Age / generation', 'hint' => 'Approximate age or generation, if shared'],
        ['section' => 'identity', 'section_label' => 'Identity', 'key' => 'languages', 'label' => 'Languages', 'hint' => 'Languages spoken and primary language'],
        ['section' => 'identity', 'section_label' => 'Identity', 'key' => 'location', 'label' => 'Where you live', 'hint' => 'City, country, or region'],

        // 02 Voice & style
        ['section' => 'voice', 'section_label' => 'Voice & style', 'key' => 'tone', 'label' => 'Tone', 'hint' => 'Warm, direct, ironic, calm…'],
        ['section' => 'voice', 'section_label' => 'Voice & style', 'key' => 'humor', 'label' => 'Humor', 'hint' => 'How you joke, or if you rarely joke'],
        ['section' => 'voice', 'section_label' => 'Voice & style', 'key' => 'formality', 'label' => 'Formality', 'hint' => 'Formal vs casual in writing and speech'],
        ['section' => 'voice', 'section_label' => 'Voice & style', 'key' => 'signature_phrases', 'label' => 'Signature phrases', 'hint' => 'Words or expressions you use often'],

        // 03 Values
        ['section' => 'values', 'section_label' => 'Values', 'key' => 'core_values', 'label' => 'Core values', 'hint' => 'What matters most to you'],
        ['section' => 'values', 'section_label' => 'Values', 'key' => 'non_negotiables', 'label' => 'Non-negotiables', 'hint' => 'Lines you will not cross'],
        ['section' => 'values', 'section_label' => 'Values', 'key' => 'beliefs', 'label' => 'Beliefs', 'hint' => 'Worldview, faith, philosophy — if shared'],

        // 04 History
        ['section' => 'history', 'section_label' => 'History', 'key' => 'origin_story', 'label' => 'Origin story', 'hint' => 'Where you come from, roots'],
        ['section' => 'history', 'section_label' => 'History', 'key' => 'formative_events', 'label' => 'Formative events', 'hint' => 'Moments that shaped you'],
        ['section' => 'history', 'section_label' => 'History', 'key' => 'turning_points', 'label' => 'Turning points', 'hint' => 'Pivots in career or life'],

        // 05 Relationships
        ['section' => 'relationships', 'section_label' => 'Relationships', 'key' => 'key_people', 'label' => 'Key people', 'hint' => 'Family, partner, close friends — names optional'],
        ['section' => 'relationships', 'section_label' => 'Relationships', 'key' => 'social_style', 'label' => 'Social style', 'hint' => 'Introvert/extrovert, how you connect'],

        // 06 Work
        ['section' => 'work', 'section_label' => 'Work', 'key' => 'profession', 'label' => 'Profession', 'hint' => 'What you do for work'],
        ['section' => 'work', 'section_label' => 'Work', 'key' => 'skills', 'label' => 'Skills', 'hint' => 'What you are good at'],
        ['section' => 'work', 'section_label' => 'Work', 'key' => 'ambitions', 'label' => 'Ambitions', 'hint' => 'Professional goals'],

        // 07 Daily life
        ['section' => 'daily', 'section_label' => 'Daily life', 'key' => 'routines', 'label' => 'Routines', 'hint' => 'Typical day or habits'],
        ['section' => 'daily', 'section_label' => 'Daily life', 'key' => 'hobbies', 'label' => 'Hobbies', 'hint' => 'What you do for pleasure'],
        ['section' => 'daily', 'section_label' => 'Daily life', 'key' => 'tastes', 'label' => 'Tastes', 'hint' => 'Food, music, media preferences'],

        // 08 Decisions
        ['section' => 'decisions', 'section_label' => 'Decisions', 'key' => 'decision_style', 'label' => 'Decision style', 'hint' => 'Fast vs deliberate, data vs gut'],
        ['section' => 'decisions', 'section_label' => 'Decisions', 'key' => 'risk_tolerance', 'label' => 'Risk tolerance', 'hint' => 'How you handle uncertainty'],

        // 09 Limits
        ['section' => 'limits', 'section_label' => 'Limits', 'key' => 'fears', 'label' => 'Fears', 'hint' => 'What you worry about — if shared'],
        ['section' => 'limits', 'section_label' => 'Limits', 'key' => 'boundaries', 'label' => 'Boundaries', 'hint' => 'Topics or behaviors you reject'],
        ['section' => 'limits', 'section_label' => 'Limits', 'key' => 'off_limits', 'label' => 'Off limits for the copy', 'hint' => 'What the digital copy must never say or do'],

        // 10 Future & copy
        ['section' => 'future', 'section_label' => 'Future & copy', 'key' => 'legacy', 'label' => 'Legacy', 'hint' => 'What you want to leave behind'],
        ['section' => 'future', 'section_label' => 'Future & copy', 'key' => 'goals', 'label' => 'Goals', 'hint' => 'Next 1–5 years'],
        ['section' => 'future', 'section_label' => 'Future & copy', 'key' => 'copy_autonomy', 'label' => 'Copy autonomy', 'hint' => 'What the copy may do alone vs with you'],
    ];
}

/** @return array<string, string> */
function profile_section_labels(): array
{
    $labels = [];
    foreach (profile_field_definitions() as $def) {
        $labels[$def['section']] = $def['section_label'];
    }

    return $labels;
}

/** @return array<string, array{label: string, hint: string}> */
function profile_fields_by_section(string $section): array
{
    $out = [];
    foreach (profile_field_definitions() as $def) {
        if ($def['section'] !== $section) {
            continue;
        }
        $out[$def['key']] = ['label' => $def['label'], 'hint' => $def['hint']];
    }

    return $out;
}
