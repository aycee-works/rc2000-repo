<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
echo 'pdo_pgsql: ' . (extension_loaded('pdo_pgsql') ? 'loaded' : 'NOT loaded') . '<br>';
require __DIR__ . '/../config/db.php';
try {
    $n = db()->query('SELECT COUNT(*) FROM content')->fetchColumn();
    echo "Connected. content rows: $n";
} catch (Throwable $e) {
    echo $e->getMessage();
}