<?php
// admin/logout.php - log out (POST + CSRF so other sites cannot log admins out)
require_once dirname(__DIR__, 2) . '/includes/portal/bootstrap.php';

if (is_post() && csrf_is_valid()) {
    if (!empty($_SESSION['admin_id'])) {
        log_activity('logout', 'admin', (int) $_SESSION['admin_id']);
    }
    admin_logout_session();
    start_secure_session();
    flash('success', 'You have been logged out.');
}
redirect('admin/login.php');
