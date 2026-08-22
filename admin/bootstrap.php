<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';

function admin_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../config.admin.php';
    if (!is_file($path)) {
        $config = ['password_hash' => ''];
        return $config;
    }

    /** @var array $loaded */
    $loaded = require $path;
    $config = $loaded;

    return $config;
}

function admin_is_configured(): bool
{
    $hash = (string) (admin_config()['password_hash'] ?? '');
    return $hash !== '';
}

function admin_is_authenticated(): bool
{
    return !empty($_SESSION['mycopy_admin']);
}

function require_admin(): void
{
    if (!admin_is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['admin_csrf'];
}

function admin_verify_csrf(string $token): bool
{
    $expected = (string) ($_SESSION['admin_csrf'] ?? '');
    return $expected !== '' && hash_equals($expected, $token);
}

function attempt_admin_login(string $password): bool
{
    if (!admin_is_configured()) {
        return false;
    }

    $hash = (string) (admin_config()['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return false;
    }

    $_SESSION['mycopy_admin'] = true;
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));

    return true;
}

function admin_logout(): void
{
    unset($_SESSION['mycopy_admin'], $_SESSION['admin_csrf'], $_SESSION['admin_flash']);
}

function admin_flash_set(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type: string, message: string}|null */
function admin_flash_get(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function admin_format_date(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '—';
    }

    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }

    return gmdate('Y-m-d H:i', $ts) . ' UTC';
}
