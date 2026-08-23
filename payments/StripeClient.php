<?php
/**
 * Minimal Stripe REST client (Checkout + webhook verify). No Composer required.
 */

declare(strict_types=1);

function stripe_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../config.stripe.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/../config.stripe.example.php';
    }

    /** @var array $loaded */
    $loaded = require $path;
    $config = $loaded;

    return $config;
}

function stripe_is_configured(): bool
{
    $key = (string) (stripe_config()['secret_key'] ?? '');
    return $key !== '' && !str_starts_with($key, 'sk_test_...');
}

/**
 * @param array<string, mixed> $params
 * @return array{ok: bool, data?: array<string, mixed>, error?: string}
 */
function stripe_request(string $method, string $path, array $params = []): array
{
    $config = stripe_config();
    $secret = (string) ($config['secret_key'] ?? '');

    if ($secret === '' || str_starts_with($secret, 'sk_test_...')) {
        return ['ok' => false, 'error' => 'Stripe is not configured. Add config.stripe.php.'];
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $ch = curl_init($url);

    if ($ch === false) {
        return ['ok' => false, 'error' => 'Could not initialize HTTP client.'];
    }

    $headers = ['Authorization: Bearer ' . $secret];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($params !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $raw === false) {
        return ['ok' => false, 'error' => 'Stripe request failed: ' . $error];
    }

    /** @var array<string, mixed>|null $decoded */
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Invalid Stripe response.'];
    }

    if ($status >= 400) {
        $message = (string) ($decoded['error']['message'] ?? 'Stripe API error.');
        return ['ok' => false, 'error' => $message];
    }

    return ['ok' => true, 'data' => $decoded];
}

/**
 * @param array{name: string, email: string, waitlist_id: int, ticket: string} $customer
 * @return array{ok: bool, url?: string, session_id?: string, error?: string}
 */
function stripe_create_checkout_session(array $customer): array
{
    $config = stripe_config();
    $siteUrl = rtrim((string) ($config['site_url'] ?? ''), '/');
    if ($siteUrl === '') {
        return ['ok' => false, 'error' => 'Stripe site_url is missing.'];
    }

    $amount = (int) ($config['amount_cents'] ?? 4900);
    $currency = strtolower((string) ($config['currency'] ?? 'usd'));
    $productName = (string) ($config['product_name'] ?? 'MYCOPY Waitlist Reservation');
    $productDesc = (string) ($config['product_description'] ?? 'Waitlist place + protocol PDF');

    $result = stripe_request('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'success_url' => $siteUrl . '/payments/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $siteUrl . '/payments/cancel.php',
        'customer_email' => $customer['email'],
        'client_reference_id' => (string) $customer['waitlist_id'],
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][unit_amount]' => $amount,
        'line_items[0][price_data][product_data][name]' => $productName,
        'line_items[0][price_data][product_data][description]' => $productDesc,
        'metadata[waitlist_id]' => (string) $customer['waitlist_id'],
        'metadata[ticket]' => $customer['ticket'],
        'metadata[name]' => $customer['name'],
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Checkout creation failed.'];
    }

    $data = $result['data'] ?? [];
    $url = (string) ($data['url'] ?? '');
    $sessionId = (string) ($data['id'] ?? '');

    if ($url === '' || $sessionId === '') {
        return ['ok' => false, 'error' => 'Stripe did not return a checkout URL.'];
    }

    return ['ok' => true, 'url' => $url, 'session_id' => $sessionId];
}

/**
 * Verify Stripe webhook signature (v1).
 *
 * @return array{ok: bool, event?: array<string, mixed>, error?: string}
 */
function stripe_verify_webhook(string $payload, string $signatureHeader): array
{
    $secret = (string) (stripe_config()['webhook_secret'] ?? '');
    if ($secret === '' || str_starts_with($secret, 'whsec_...')) {
        return ['ok' => false, 'error' => 'Webhook secret is not configured.'];
    }

    $parts = [];
    foreach (explode(',', $signatureHeader) as $item) {
        [$k, $v] = array_pad(explode('=', trim($item), 2), 2, '');
        $parts[$k][] = $v;
    }

    $timestamp = (string) (($parts['t'][0] ?? ''));
    $signatures = $parts['v1'] ?? [];

    if ($timestamp === '' || $signatures === []) {
        return ['ok' => false, 'error' => 'Missing Stripe signature.'];
    }

    if (abs(time() - (int) $timestamp) > 300) {
        return ['ok' => false, 'error' => 'Stripe signature timestamp too old.'];
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    $valid = false;
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        return ['ok' => false, 'error' => 'Invalid Stripe signature.'];
    }

    /** @var array<string, mixed>|null $event */
    $event = json_decode($payload, true);
    if (!is_array($event)) {
        return ['ok' => false, 'error' => 'Invalid webhook payload.'];
    }

    return ['ok' => true, 'event' => $event];
}

function protocol_pdf_path(): string
{
    return __DIR__ . '/../assets/docs/mycopy-protocol.pdf';
}
