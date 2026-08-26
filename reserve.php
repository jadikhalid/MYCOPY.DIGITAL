<?php
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if (is_authenticated()) {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Already authenticated.', 'redirect' => route_url('studio')]);
        exit;
    }
    route_redirect('studio');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
        exit;
    }
    route_redirect('waitlist');
}

$uiMode = (string) ($_POST['ui_mode'] ?? 'embedded_page');
if ($uiMode !== 'hosted_page') {
    $uiMode = 'embedded_page';
}

$result = start_checkout_reservation(
    (string) ($_POST['name'] ?? ''),
    (string) ($_POST['email'] ?? ''),
    $uiMode,
    !empty($_POST['terms_accepted'])
);

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');

    if (!empty($result['ok']) && (!empty($result['client_secret']) || !empty($result['url']))) {
        echo json_encode([
            'ok' => true,
            'client_secret' => $result['client_secret'] ?? '',
            'url' => $result['url'] ?? '',
            'session_id' => $result['session_id'] ?? '',
            'publishable_key' => stripe_publishable_key(),
        ]);
        exit;
    }

    $toast = $result['toast'] ?? [
        'type' => 'error',
        'title' => 'Checkout failed',
        'message' => 'Could not start payment.',
    ];

    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => (string) ($toast['message'] ?? 'Could not start payment.'),
        'title' => (string) ($toast['title'] ?? 'Checkout failed'),
    ]);
    exit;
}

// Fallback: non-AJAX posts cannot mount Embedded Checkout
$_SESSION['toast'] = $result['toast'] ?? [
    'type' => 'error',
    'title' => 'Checkout failed',
    'message' => 'Please enable JavaScript to complete payment.',
];

if (!empty($result['keep_form'])) {
    $_SESSION['flash_form'] = [
        'name' => (string) ($_POST['name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
    ];
}

header('Location: ' . route_url('waitlist'));
exit;
