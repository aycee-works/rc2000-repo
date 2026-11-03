<?php
/**
 * database/reset-super-admin-password.php
 * Command-line script: set a new password for an existing SUPER ADMIN account.
 * Only super_admin accounts can be changed with it. Administrators are managed from the Super Admin portal.
 *
 *   php database/reset-super-admin-password.php
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
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
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
} catch (Throwable $e) {
    exit("Could not reach the database.\n");
}

echo "=== Reading Club 2000: reset SUPER ADMIN password ===\n";
$email = mb_strtolower(ask('Super admin email: '));
$stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND role = 'super_admin'");
$stmt->execute([$email]);
$id = $stmt->fetchColumn();
if (!$id) {
    exit("No super admin account with that email. Nothing was changed.\n");
}
if (stripos(PHP_OS, 'WIN') === 0) {
    echo "(Note: on Windows the password is visible while typing. Clear the screen afterwards.)\n";
}
$password = ask('New password (min ' . MIN_PASSWORD_LENGTH . ' characters): ', true);
if (strlen($password) < MIN_PASSWORD_LENGTH) {
    exit('Password must be at least ' . MIN_PASSWORD_LENGTH . " characters. Nothing was changed.\n");
}
$pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
$pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
echo "Done. The password was changed.\n";

