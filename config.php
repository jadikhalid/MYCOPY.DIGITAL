<?php
/**
 * MYCOPY.DIGITAL — minimal configuration
 */

declare(strict_types=1);

session_start();

const SITE_NAME = 'MYCOPY';
const SITE_TAGLINE = 'Capture. Train. Choose. Continue.';

/** Bootstrap studio codes (optional; DB-issued codes are primary) */
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
    if ($normalized === '') {
        return false;
    }

    if (in_array($normalized, ACCESS_CODES, true)) {
        $_SESSION['mycopy_auth'] = true;
        $_SESSION['mycopy_code'] = $normalized;
        $_SESSION['mycopy_at'] = time();
        $_SESSION['mycopy_waitlist_id'] = null;
        return true;
    }

    require_once __DIR__ . '/db.php';
    $access = find_studio_access($normalized);

    if ($access === null) {
        return false;
    }

    $_SESSION['mycopy_auth'] = true;
    $_SESSION['mycopy_code'] = (string) $access['studio_code'];
    $_SESSION['mycopy_at'] = time();
    $_SESSION['mycopy_waitlist_id'] = (int) $access['id'];

    return true;
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
