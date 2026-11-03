<?php
/**
 * admin/api/dashboard.php
 *   Method:   GET
 *   Access:   admin only (session required) - returns 401 JSON otherwise
 *   Input:    none
 *   Response: {success, message, data:{cards:{...}, pending_donations:[...], pending_inquiries:[...]}}
 *   Errors:   401 not logged in, 405 wrong method, 500 database problem
 */
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/dashboard-service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(false, 'Method not allowed.', null, 405);
}

try {
    require_admin_api();
    $pdo = db();
    json_response(true, 'OK', [
        'cards'             => dashboard_card_values(get_dashboard_stats($pdo)),
        'pending_donations' => get_pending_donations($pdo, 5),
        'pending_inquiries' => get_pending_inquiries($pdo, 5),
    ]);
} catch (Throwable $e) {
    error_log('API dashboard error: ' . $e->getMessage());
    json_response(false, 'Could not load dashboard data.', null, 500);
}
