<?php
declare(strict_types=1);

/**
 * PostgreSQL (Supabase) connection.
 * Credentials come from config/db.local.php (not committed to Git),
 * or from RC2000_DB_* environment variables.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $local = [];
    $localFile = __DIR__ . '/db.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
    }

    $host = getenv('RC2000_DB_HOST') ?: ($local['host'] ?? '127.0.0.1');
    $port = getenv('RC2000_DB_PORT') ?: ($local['port'] ?? '5432');
    $name = getenv('RC2000_DB_NAME') ?: ($local['name'] ?? 'postgres');
    $user = getenv('RC2000_DB_USER') ?: ($local['user'] ?? 'Aycee03!');
    $pass = getenv('RC2000_DB_PASSWORD') ?: ($local['pass'] ?? '');
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}