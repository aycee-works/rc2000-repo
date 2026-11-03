<?php
/**
 * includes/csrf.php
 * CSRF protection. Every form that changes data must include csrf_field(),
 * and every POST handler must call csrf_require() (pages) or
 * csrf_require_api() (JSON endpoints).
 * AJAX requests send the token in the "X-CSRF-Token" header.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/** For normal pages: stop with a clear message if the token is bad. */
function csrf_require(): void
{
    if (!csrf_is_valid()) {
        http_response_code(419);
        exit('Your session form has expired or is invalid. Please go back, refresh the page, and try again.');
    }
}

/** For JSON endpoints. */
function csrf_require_api(): void
{
    if (!csrf_is_valid()) {
        json_response(false, 'Invalid or missing security token. Refresh the page and try again.', null, 419);
    }
}
