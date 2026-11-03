<?php
/**
 * includes/flash-messages.php
 * One-time messages stored in the session (shown after a redirect).
 * flash('success', 'Saved.');  ...later...  render_flash_messages();
 */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash_messages(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $f) {
        $type = in_array($f['type'], ['success', 'error', 'info', 'warning'], true) ? $f['type'] : 'info';
        echo '<div class="flash flash-' . e($type) . '" role="alert">'
           . '<span>' . e($f['message']) . '</span>'
           . '<button type="button" class="flash-close" aria-label="Dismiss">&times;</button>'
           . '</div>';
    }
    unset($_SESSION['flash']);
}
