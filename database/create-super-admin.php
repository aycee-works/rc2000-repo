<?php
/**
 * database/create-super-admin.php
 * Command-line script to create the SUPER ADMIN account (the one that manages administrators).
 * There is deliberately NO web page for this, and no password is stored in any file.
 *
 *   XAMPP Control Panel > Shell:   cd C:\xampp\htdocs\rc2000
 *                                  php database/create-super-admin.php
 *
 * It never changes or deletes existing accounts: if the email already exists it stops.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/portal/constants.php';
require_once __DIR__ . '/../config/portal/app.php';
require_once __DIR__ . '/../config/portal/database.php';

function ask(string $label, bool $hidden = false): string
{
    echo $label;
    $isWindows = stripos(PHP_OS, 'WIN') === 0;
    if ($hidden && !$isWindows) {
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        echo "\n";
        return $value;
    }
    return trim((string) fgets(STDIN));
}

try {
    $pdo = db();
    $existing = $pdo->query("SELECT email FROM admins WHERE role = 'super_admin' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    exit("Could not reach the database. Is PostgreSQL running and is your password set in config/local.php?\n");
}

echo "=== Reading Club 2000: create SUPER ADMIN ===\n";
if ($existing) {
    echo "Existing super admin account(s): " . implode(', ', $existing) . "\n";
    if (strtolower(ask('Create another super admin? (yes/no): ')) !== 'yes') {
        exit("Cancelled. Nothing was changed.\n");
    }
}

$name  = ask('Full name: ');
$email = mb_strtolower(ask('Email: '));
if (stripos(PHP_OS, 'WIN') === 0) {
    echo "(Note: on Windows the password is visible while typing. Clear the screen afterwards.)\n";
}
$password = ask('Password (min ' . MIN_PASSWORD_LENGTH . ' characters): ', true);

if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Please provide a name (up to 100 characters) and a valid email.\n");
}
if (strlen($password) < MIN_PASSWORD_LENGTH) {
    exit('Password must be at least ' . MIN_PASSWORD_LENGTH . " characters.\n");
}

try {
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, 'super_admin')");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Done. Created the super admin account for {$email}.\nLog in at /superadmin/login.php\n";
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        exit("An account with that email already exists. Nothing was changed.\n");
    }
    error_log($e->getMessage());
    exit("Could not create the account. Check the PHP error log.\n");
}


