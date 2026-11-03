<?php
/**
 * admin/api/monetary-donations.php  (admin-only)
 *   Method:   POST
 *   Auth:     admin session + CSRF token (form field "csrf_token" or header X-CSRF-Token)
 *   Input:    id (int, required)
 *             decision ('verified' | 'not_verified', required)
 *             verified_amount (required when verified; > 0, max 2 decimals)
 *             note (required when not_verified, or when the verified amount differs
 *                   from the reported amount; max 255 characters)
 *             version (the record's updated_at when the page loaded; detects edit conflicts)
 *   Response: {success, message, data:{changed, record:{...}, summary:{...}}}
 *   Errors:   401 not logged in, 404 not found, 405 wrong method, 409 edit conflict,
 *             419 bad CSRF token, 422 validation, 500 server error
 */
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/monetary-donation-service.php';

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
    $adminId = (int) ($admin['admin_id'] ?? $admin['id'] ?? 0);   // admins PK is admin_id
    $result = update_money_donation(
        $pdo, $id, $adminId > 0 ? $adminId : null,
        post_str('decision'), post_str('verified_amount'), post_str('note'), post_str('version')
    );

    if (!$result['ok']) {
        json_response(false, $result['message'], null, $result['code']);
    }

    if ($result['changed']) {
        flash('success', $result['message']);   // shown after the page reloads
    }

    $r = $result['record'];
    json_response(true, $result['message'], [
        'changed' => $result['changed'],
        'record'  => [
            'id'              => (int) $r['id'],
            'reference_no'    => $r['reference_no'],
            'status'          => $r['status'],
            'reported_amount' => (float) $r['reported_amount'],
            'verified_amount' => $r['verified_amount'] === null ? null : (float) $r['verified_amount'],
            'updated_at'      => $r['updated_at'],
        ],
        'summary' => get_money_summary($pdo),
    ]);
} catch (Throwable $e) {
    error_log('API monetary-donations error: ' . $e->getMessage());
    json_response(false, 'Could not save the changes. Please try again.', null, 500);
}