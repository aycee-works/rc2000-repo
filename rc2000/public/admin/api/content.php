<?php
/**
 * admin/api/content.php  (admin-only)
 *   Method:   POST (multipart/form-data when an image is attached)
 *   Auth:     admin session + CSRF token (form field "csrf_token" or header X-CSRF-Token)
 *   Input:    action (required), one of:
 *     gallery_save        id (0 = new), title, caption, status, version, image (file; required for new)
 *     story_save          id (0 = new), type (story|article), title, body, author, status,
 *                         published_date (YYYY-MM-DD, optional), version, image (cover), remove_image (1)
 *     announcement_save   id (0 = new), title, body, status, published_date, version, image, remove_image
 *     trivia_save         id (0 = new), question, answer, explanation, status (draft|published), version
 *     set_status          entity (gallery|story|announcement|trivia), id, status
 *     delete              entity, id
 *   Response: {success, message, data:{id}}
 *   Errors:   401 not logged in, 404 not found, 405 wrong method, 409 edit conflict,
 *             413 upload too large, 419 bad CSRF token, 422 validation, 500 server error
 */
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/content-service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(false, 'Method not allowed.', null, 405);
}

try {
    require_admin_api();

    // If the upload is bigger than PHP's post_max_size, PHP discards everything (including the token)
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !$_POST && !$_FILES) {
        json_response(false, 'The upload is too large for the server. Use an image under 5 MB.', null, 413);
    }
    csrf_require_api();

    $pdo = db();
    $adminId = current_admin()['id'];
    $id = (int) post_str('id');
    $file = $_FILES['image'] ?? null;

    $in = [
        'title' => post_str('title'), 'caption' => post_str('caption'), 'body' => post_str('body'),
        'author' => post_str('author'), 'type' => post_str('type'), 'status' => post_str('status'),
        'published_date' => post_str('published_date'), 'version' => post_str('version'),
        'question' => post_str('question'), 'answer' => post_str('answer'), 'explanation' => post_str('explanation'),
    ];

    switch (post_str('action')) {
        case 'gallery_save':
            $result = save_gallery($pdo, $adminId, $id, $in, $file);
            break;
        case 'story_save':
            $result = save_post($pdo, $adminId, 'story', $id, $in, $file, post_str('remove_image') === '1');
            break;
        case 'announcement_save':
            $result = save_post($pdo, $adminId, 'announcement', $id, $in, $file, post_str('remove_image') === '1');
            break;
        case 'trivia_save':
            $result = save_trivia($pdo, $adminId, $id, $in);
            break;
        case 'set_status':
            $result = set_content_status($pdo, $adminId, post_str('entity'), $id, post_str('status'));
            break;
        case 'delete':
            $result = delete_content($pdo, post_str('entity'), $id);
            break;
        default:
            json_response(false, 'Unknown action.', null, 422);
    }

    if (!$result['ok']) {
        json_response(false, $result['message'], null, $result['code']);
    }

    flash('success', $result['message']);      // shown after the page reloads / redirects
    json_response(true, $result['message'], ['id' => $result['id']]);
} catch (Throwable $e) {
    error_log('API content error: ' . $e->getMessage());
    json_response(false, 'Could not save the changes. Please try again.', null, 500);
}
