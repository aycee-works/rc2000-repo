<?php
/**
 * admin/api/inquiries.php  (admin-only)
 *   Method:   POST
 *   Auth:     admin session + CSRF token (form field "csrf_token" or header X-CSRF-Token)
 *   Input:    id (int, required)
 *             action ('save' | 'resolve' | 'close', required)
 *             status (required for 'save': the dropdown value)
 *             notes (resolution notes, optional, max 2000 chars)
 *             response (reply to the submitter, max 5000 chars; may be blank)
 *             version (the record's updated_at when the page loaded; detects edit conflicts)
 *   Rules:    'resolve' fails (422) unless a non-empty response is saved for the inquiry.
 *             'close' without any saved response needs a resolution note.
 *   Response: {success, message, data:{changed, record:{...}}}
 *   Errors:   401 not logged in, 404 not found, 405 wrong method, 409 edit conflict,
 *             419 bad CSRF token, 422 validation/rule, 500 server error
 */
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/inquiry-service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(false, 'Method not allowed.', null, 405);
}

try {
    require_admin_api();
    csrf_require_api();

    $id = (int) post_str('id');
    if ($id < 1) {
        json_response(false, 'Missing inquiry id.', null, 422);
    }

    $admin = current_admin();
    $result = apply_inquiry_action(db(), $id, $admin['id'], post_str('action'), [
        'status'   => post_str('status'),
        'notes'    => post_str('notes'),
        'response' => post_str('response'),
        'version'  => post_str('version'),
    ]);

    if (!$result['ok']) {
        json_response(false, $result['message'], null, $result['code']);
    }

    flash('success', $result['message']);    // shown after the page reloads

    $r = $result['record'];
    json_response(true, $result['message'], [
        'changed' => true,
        'record'  => [
            'id'           => (int) $r['id'],
            'reference_no' => $r['reference_no'],
            'status'       => $r['status'],
            'updated_at'   => $r['updated_at'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('API inquiries error: ' . $e->getMessage());
    json_response(false, 'Could not save the changes. Please try again.', null, 500);
}
