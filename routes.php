<?php
/**
 * Pretty URL route map.
 *
 * To add a future page:
 * 1. Create the PHP file (e.g. about.php)
 * 2. Add one entry below: 'about' => 'about.php'
 * Apache (.htaccess) and router-dev.php pick it up automatically.
 */

declare(strict_types=1);

/** @var array<string, string> slug => PHP script in project root */
const ROUTES = [
    'home' => 'index.php',
    'waitlist' => 'attente.php',
    'studio' => 'vault.php',
];

/**
 * Public path for a named route (root-absolute).
 * Example: route_url('waitlist') => '/waitlist'
 */
function route_url(string $name, string $query = ''): string
{
    if (!isset(ROUTES[$name])) {
        return '/';
    }

    $path = '/' . $name;
    if ($query === '') {
        return $path;
    }

    return $path . (str_starts_with($query, '?') ? $query : '?' . $query);
}

function route_redirect(string $name, int $status = 302, string $query = ''): never
{
    header('Location: ' . route_url($name, $query), true, $status);
    exit;
}

/**
 * Resolve a slug to an absolute filesystem path, or null if unknown.
 */
function route_script_path(string $slug): ?string
{
    $slug = strtolower(trim($slug, "/ \t\n\r\0\x0B"));
    if ($slug === '' || !isset(ROUTES[$slug])) {
        return null;
    }

    $file = ROUTES[$slug];
    if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $file)) {
        return null;
    }

    $path = __DIR__ . DIRECTORY_SEPARATOR . $file;
    return is_file($path) ? $path : null;
}
