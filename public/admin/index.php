<?php
// admin/index.php - entry point: send people to the dashboard (or login)
require_once dirname(__DIR__, 2) . '/includes/portal/bootstrap.php';

try {
    if (admin_portal_session_is_valid()) {
        redirect('admin/dashboard.php');
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
}
redirect('admin/login.php');
