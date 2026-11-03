<?php
/**
 * includes/bootstrap.php
 * Every admin page and API starts with:
 *     require_once __DIR__ . '/../includes/bootstrap.php';   (path adjusted per folder)
 * It loads config, helpers and starts the session. It does NOT require login;
 * call require_admin() / require_admin_api() after it.
 */

require_once __DIR__ . '/../../config/portal/constants.php';
require_once __DIR__ . '/../../config/portal/app.php';
require_once __DIR__ . '/../../config/portal/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash-messages.php';
require_once __DIR__ . '/auth.php';

// Security headers for every admin response
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

start_secure_session();
