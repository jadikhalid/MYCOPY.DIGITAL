<?php
/**
 * Minimal Gemini REST client (no Composer). Google AI Studio free tier.
 */

declare(strict_types=1);

function gemini_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../config.gemini.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/../config.gemini.example.php';
    }

    /** @var array<string, mixed> $loaded */
    $loaded = require $path;
    $config = $loaded;

    return $config;
}

function gemini_is_configured(): bool
{
    $key = (string) (gemini_config()['api_key'] ?? '');

    return $key !== '';
}

/** @return list<string> */
function gemini_model_candidates(): array
{
    $config = gemini_config();
    $primary = trim((string) ($config['model'] ?? 'gemini-2.5-flash'));
    $fallbacks = ['gemini-2.5-flash', 'gemini-3.6-flash'];

    $models = [];
    if ($primary !== '') {
        $models[] = $primary;
    }
    foreach ($fallbacks as $model) {
        if (!in_array($model, $models, true)) {
            $models[] = $model;
        }
    }

    return $models;
}

/**
 * @param list<array{role: string, parts: list<array{text: string}>}> $contents
 * @param array<string, mixed> $generationConfig
 * @return array{ok: bool, text?: string, error?: string, model?: string}
 */
function gemini_generate_content(
    array $contents,
    string $systemInstruction = '',
    array $generationConfig = []
): array {
    $apiKey = (string) (gemini_config()['api_key'] ?? '');

    if ($apiKey === '') {
        return [
            'ok' => false,
            'error' => 'Gemini is not configured. Add your API key to config.gemini.php.',
        ];
    }

    $body = ['contents' => $contents];

    if ($systemInstruction !== '') {
        $body['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
    }

    if ($generationConfig !== []) {
        $body['generationConfig'] = $generationConfig;
    }

    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    $lastError = 'Gemini API error.';

    foreach (gemini_model_candidates() as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key=' . rawurlencode($apiKey);

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Could not initialize HTTP client.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 90,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            $lastError = 'Gemini request failed (network).';
            continue;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);

        if ($status >= 400) {
            $lastError = (string) ($decoded['error']['message'] ?? 'Gemini API error.');
            if ($status === 404 || str_contains(strtolower($lastError), 'no longer available')) {
                continue;
            }
            return ['ok' => false, 'error' => $lastError];
        }

        $text = '';
        $candidates = $decoded['candidates'] ?? [];
        if (is_array($candidates) && isset($candidates[0]['content']['parts'][0]['text'])) {
            $text = (string) $candidates[0]['content']['parts'][0]['text'];
        }

        if ($text === '') {
            $lastError = 'Empty response from Gemini.';
            continue;
        }

        return ['ok' => true, 'text' => $text, 'model' => $model];
    }

    return ['ok' => false, 'error' => $lastError];
}

/**
 * Strip markdown code fences from JSON model output.
 */
function gemini_parse_json_text(string $text): ?array
{
    $trimmed = trim($text);
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $trimmed, $m)) {
        $trimmed = trim($m[1]);
    }

    /** @var array<string, mixed>|null $data */
    $data = json_decode($trimmed, true);

    return is_array($data) ? $data : null;
}
