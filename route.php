<?php
/**
 * Front controller for pretty URLs (Apache rewrite → route.php?r=slug).
 */

declare(strict_types=1);

require __DIR__ . '/routes.php';

$slug = strtolower(trim((string) ($_GET['r'] ?? ''), "/ \t\n\r\0\x0B"));
$path = route_script_path($slug);

if ($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

require $path;
