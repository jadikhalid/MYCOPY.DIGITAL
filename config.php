<?php
/**
 * MYCOPY.DIGITAL — minimal configuration
 */

declare(strict_types=1);

session_start();

const SITE_NAME = 'MYCOPY';
const SITE_TAGLINE = 'Capture. Train. Choose. Continue.';

/** Valid access codes (in production: store hashed) */
const ACCESS_CODES = [
    'NEURAL-01',
    'TRAIN-7',
    'MYCOPY',
];

function is_authenticated(): bool
{
    return !empty($_SESSION['mycopy_auth']);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        header('Location: index.php');
        exit;
    }
}

function attempt_login(string $code): bool
{
    $normalized = strtoupper(trim($code));
    if (in_array($normalized, ACCESS_CODES, true)) {
        $_SESSION['mycopy_auth'] = true;
        $_SESSION['mycopy_code'] = $normalized;
        $_SESSION['mycopy_at'] = time();
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
