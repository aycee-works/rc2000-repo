<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    try {
        $stmt = db()->prepare(
            'SELECT admin_id, full_name, email, role, is_active
             FROM admins WHERE admin_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => (int) $_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        if (!$admin || empty($admin['is_active'])) {
            unset($_SESSION['admin_id']);
            return null;
        }
        return $admin;
    } catch (Throwable $error) {
        return null;
    }
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('/admin/login.php');
    }
    return $admin;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id']);
}
