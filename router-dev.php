<?php
/**
 * Dev router for PHP built-in server:
 *   php -S localhost:8001 router-dev.php
 *
 * Mirrors .htaccess pretty-URL behaviour for local testing.
 */

declare(strict_types=1);

require __DIR__ . '/routes.php';

$uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$uri = rawurldecode($uri);

// Trailing slash → no slash (except root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $target = rtrim($uri, '/');
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . $target . ($qs !== '' ? '?' . $qs : ''), true, 301);
    return true;
}

// Legacy .php page → pretty URL
$legacy = [
    '/index.php' => 'home',
    '/attente.php' => 'waitlist',
    '/vault.php' => 'studio',
    '/access.php' => 'home',
];
if (isset($legacy[$uri])) {
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . route_url($legacy[$uri], $qs !== '' ? $qs : ''), true, 301);
    return true;
}

// Old French slug → home
if ($uri === '/accueil') {
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . route_url('home', $qs !== '' ? $qs : ''), true, 301);
    return true;
}

// Existing files (assets, api, admin, payments, …) — let the server handle them
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Root → home page
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

$slug = trim($uri, '/');
if (str_contains($slug, '/')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    return true;
}

$path = route_script_path($slug);
if ($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    return true;
}

require $path;
return true;
