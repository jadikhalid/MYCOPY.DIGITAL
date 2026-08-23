<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/StripeClient.php';

header('Content-Type: application/json; charset=utf-8');

$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty payload']);
    exit;
}

$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$verified = stripe_verify_webhook($payload, $signature);

if (!$verified['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $verified['error'] ?? 'Invalid signature']);
    exit;
}

$event = $verified['event'] ?? [];
$type = (string) ($event['type'] ?? '');

if ($type !== 'checkout.session.completed') {
    echo json_encode(['ok' => true, 'ignored' => $type]);
    exit;
}

/** @var array<string, mixed> $session */
$session = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
$sessionId = (string) ($session['id'] ?? '');
$paymentStatus = (string) ($session['payment_status'] ?? '');
$waitlistId = (int) ($session['metadata']['waitlist_id'] ?? ($session['client_reference_id'] ?? 0));

if ($sessionId === '' || $waitlistId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing session metadata']);
    exit;
}

if ($paymentStatus !== '' && $paymentStatus !== 'paid') {
    echo json_encode(['ok' => true, 'ignored' => 'not_paid']);
    exit;
}

require_once __DIR__ . '/../db.php';
$result = fulfill_paid_reservation($waitlistId, $sessionId);

if (!$result['ok']) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $result['message']]);
    exit;
}

echo json_encode(['ok' => true, 'message' => $result['message']]);
