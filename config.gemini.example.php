<?php
/**
 * Google Gemini — copy to config.gemini.php and set your API key.
 * Free tier: https://aistudio.google.com/apikey
 * Do not commit config.gemini.php.
 */

declare(strict_types=1);

return [
    // API key from Google AI Studio (free tier)
    'api_key' => '',

    // Free-tier models (2026): gemini-2.5-flash, gemini-3.6-flash
    'model' => 'gemini-2.5-flash',

    // Cap conversation history sent to the model (keeps free-tier usage low)
    'max_history_messages' => 24,
];
