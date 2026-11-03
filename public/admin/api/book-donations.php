<?php
/**
 * admin/api/book-donations.php  (admin-only)
 *   Method:   POST
 *   Auth:     admin session + CSRF token (form field "csrf_token" or header X-CSRF-Token)
 *   Input:    id (int, required)
 *             status ('pending'|'approved'|'rejected', required)
 *             qty_received (whole number 0..qty_offered, or blank for "not confirmed yet")
 *             version (the record's updated_at when the page loaded; detects edit conflicts)
 *   Response: {success, message, data:{changed, record:{...}, summary:{...}}}
 *   Errors:   401 not logged in, 404 not found, 405 wrong method, 409 edit conflict,
 *             419 bad CSRF token, 422 validation, 500 server error
 */
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/book-donation-service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(false, 'Method not allowed.', null, 405);
}

try {
    require_admin_api();
    csrf_require_api();

    $id = (int) post_str('id');
    if ($id < 1) {
        json_response(false, 'Missing donation id.', null, 422);
    }

    $pdo = db();
    $admin = current_admin();
    $result = update_book_donation(
        $pdo, $id, $admin['id'],
        post_str('status'), post_str('qty_received'), post_str('version')
    );

    if (!$result['ok']) {
        json_response(false, $result['message'], null, $result['code']);
    }

    // Shown at the top of the page after it reloads
    if ($result['changed']) {
        flash('success', $result['message']);
    }

    $r = $result['record'];
    json_response(true, $result['message'], [
        'changed' => $result['changed'],
        'record'  => [
            'id'           => (int) $r['id'],
            'reference_no' => $r['reference_no'],
            'status'       => $r['status'],
            'qty_offered'  => (int) $r['qty_offered'],
            'qty_received' => $r['qty_received'] === null ? null : (int) $r['qty_received'],
            'updated_at'   => $r['updated_at'],
        ],
        'summary' => get_book_summary($pdo),
    ]);
} catch (Throwable $e) {
    error_log('API book-donations error: ' . $e->getMessage());
    json_response(false, 'Could not save the changes. Please try again.', null, 500);
}
