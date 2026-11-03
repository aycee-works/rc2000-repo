<?php
/**
 * config/app.php
 * Central application settings. Environment-specific values (database login,
 * base URL, debug mode) can be overridden in config/local.php, which is NOT
 * committed to Git. Copy config/local.example.php to config/local.php.
 */

define('ROOT_PATH', dirname(__DIR__, 2));

// Defaults suitable for XAMPP on localhost
$config = [
    'app_env'    => 'development',          // 'development' or 'production'
    'base_url'   => '/rc2000',              // URL path of this project (no trailing slash)
    'timezone'   => 'Asia/Manila',
    'db_host'    => '127.0.0.1',
    'db_port'    => 5432,
    'db_name'    => getenv('RC2000_DB_NAME') ?: 'rc2000_db',
    'db_user'    => getenv('RC2000_DB_USER') ?: 'postgres',
    'db_pass'    => getenv('RC2000_DB_PASSWORD') ?: '',
    'db_tz'      => 'Asia/Manila',          // PostgreSQL time zone name; must match 'timezone'
    // Email: leave false until SMTP is configured. Inquiry responses are
    // saved either way; the UI says so when email is not configured.
    'mail_enabled' => false,
];

$localFile = __DIR__ . '/local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

define('APP_ENV', $config['app_env']);
define('BASE_URL', rtrim($config['base_url'], '/'));
define('MAIL_ENABLED', (bool) $config['mail_enabled']);

date_default_timezone_set($config['timezone']);

// Never show raw PHP errors to visitors in production; always log them.
ini_set('log_errors', '1');
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
error_reporting(E_ALL);

// Make the config available to config/database.php
$GLOBALS['APP_CONFIG'] = $config;
