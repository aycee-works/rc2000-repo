<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (empty($_SESSION['flash'][$key]) || !is_string($_SESSION['flash'][$key])) {
        return null;
    }
    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $message;
}

function redirect(string $path): void
{
    if (str_starts_with($path, '/') && !preg_match('#^https?://#i', $path)) {
        $path = app_url($path);
    }
    header('Location: ' . $path);
    exit;
}

function post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}
