<?php
$config = require __DIR__ . '/../config.gemini.php';
$key = $config['api_key'];
$models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-1.5-flash', 'gemini-3.6-flash'];

foreach ($models as $model) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model)
        . ':generateContent?key=' . rawurlencode($key);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => 'Say hi']]]],
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    $err = $data['error']['message'] ?? 'OK';
    if ($status < 400 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $err = 'OK: ' . substr((string) $data['candidates'][0]['content']['parts'][0]['text'], 0, 40);
    }
    echo $model . ' -> HTTP ' . $status . ' | ' . $err . PHP_EOL;
}
