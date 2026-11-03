<?php
/**
 * database/create-admin.php
 * Command-line setup script to create an administrator account.
 * There is deliberately NO web page for this.
 *
 * Run from the project folder (XAMPP on Windows):
 *     C:\xampp\php\php.exe database\create-admin.php
 * Or from XAMPP's Shell button:
 *     php database/create-admin.php
 *
 * The first account created becomes a super_admin.
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
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
} catch (Throwable $e) {
    exit("Could not reach the database. Did you run database/schema.sql in pgAdmin, and is your PostgreSQL password set in config/local.php?\n");
}

echo "=== Reading Club 2000: create administrator ===\n";
if ($count > 0) {
    echo "There are already {$count} administrator account(s).\n";
    if (strtolower(ask('Create another one? (yes/no): ')) !== 'yes') {
        exit("Cancelled.\n");
    }
}

$name = ask('Full name: ');
$email = mb_strtolower(ask('Email: '));
if (stripos(PHP_OS, 'WIN') === 0) {
    echo "(Note: on Windows the password is visible while typing. Clear the screen afterwards.)\n";
}
$password = ask('Password (min ' . MIN_PASSWORD_LENGTH . ' characters): ', true);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Please provide a name and a valid email.\n");
}
if (strlen($password) < MIN_PASSWORD_LENGTH) {
    exit('Password must be at least ' . MIN_PASSWORD_LENGTH . " characters.\n");
}

$role = $count === 0 ? 'super_admin' : 'admin';
if ($count > 0 && strtolower(ask('Make this a super admin? (yes/no): ')) === 'yes') {
    $role = 'super_admin';
}

try {
    $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    echo "Done. Created {$role} account for {$email}.\n";
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {      // PostgreSQL unique violation
        exit("An administrator with that email already exists.\n");
    }
    error_log($e->getMessage());
    exit("Could not create the account. Check the PHP error log.\n");
}

