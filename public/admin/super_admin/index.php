<?php
// superadmin/index.php - entry point: dashboard if logged in as super admin, otherwise the login page
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';

try {
    if (superadmin_session_is_valid()) {
        redirect('superadmin/dashboard.php');
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
}
redirect('superadmin/login.php');
