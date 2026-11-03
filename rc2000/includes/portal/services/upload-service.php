<?php
/**
 * includes/services/upload-service.php
 * Safe image uploads for gallery, story covers and announcement images.
 *
 * What is checked (the file name and the browser-supplied type are NOT trusted):
 *   - the upload really came through HTTP (is_uploaded_file)
 *   - size is between 1 byte and 5 MB
 *   - the REAL content type (read from the file's bytes) is JPEG, PNG or WebP
 *   - getimagesize() can read it as an image of the same type
 *   - width and height are 1..8000 pixels
 * The file is saved under a random name with an extension chosen from the
 * detected type, inside uploads/<folder>/ (where PHP execution is blocked).
 */

const UPLOAD_MAX_BYTES     = 5 * 1024 * 1024;
const UPLOAD_MAX_DIMENSION = 8000;
const UPLOAD_FOLDERS       = ['gallery', 'stories', 'announcements'];
const UPLOAD_MIME_EXT      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** Did the user pick a file in this form field? */
function upload_provided($file): bool
{
    return is_array($file) && isset($file['error']) && !is_array($file['error'])
        && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/**
 * Check the CONTENT of a file on disk. Returns ['ok'=>true,'mime'=>..,'ext'=>..]
 * or ['ok'=>false,'message'=>..]. Separate from saving so it can be tested alone.
 */
function validate_image_file(string $path, int $size): array
{
    $fail = fn(string $m) => ['ok' => false, 'message' => $m];

    if ($size < 1) {
        return $fail('The uploaded file is empty.');
    }
    if ($size > UPLOAD_MAX_BYTES) {
        return $fail('The image is too large. The maximum size is 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!isset(UPLOAD_MIME_EXT[$mime])) {
        return $fail('Only JPG, PNG or WebP images are allowed.');
    }
    $info = @getimagesize($path);
    if (!$info || ($info['mime'] ?? '') !== $mime) {
        return $fail('The file is not a valid image.');
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > UPLOAD_MAX_DIMENSION || $info[1] > UPLOAD_MAX_DIMENSION) {
        return $fail('The image dimensions are not supported (maximum ' . UPLOAD_MAX_DIMENSION . ' x ' . UPLOAD_MAX_DIMENSION . ' pixels).');
    }
    return ['ok' => true, 'mime' => $mime, 'ext' => UPLOAD_MIME_EXT[$mime]];
}

/**
 * Validate and store one uploaded image ($_FILES['field']) in uploads/<folder>/.
 * Returns ['ok'=>true,'path'=>'uploads/gallery/abc.jpg','original_name','mime','size']
 * or ['ok'=>false,'message'=>..].
 */
function save_uploaded_image($file, string $folder): array
{
    $fail = fn(string $m) => ['ok' => false, 'message' => $m];

    if (!in_array($folder, UPLOAD_FOLDERS, true)) {
        return $fail('Invalid upload folder.');
    }
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error'])) {
        return $fail('No file was uploaded.');
    }

    switch ((int) $file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $fail('The image is too large. The maximum size is 5 MB.');
        case UPLOAD_ERR_PARTIAL:
            return $fail('The upload was interrupted. Please try again.');
        default:
            return $fail('The image could not be uploaded. Please try again.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('The image could not be uploaded. Please try again.');
    }

    $check = validate_image_file($file['tmp_name'], (int) $file['size']);
    if (!$check['ok']) {
        return $check;
    }

    // Public images are served through the root .htaccess rewrite to
    // public/uploads/, so write files into that protected public directory.
    $dir = ROOT_PATH . '/public/uploads/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return $fail('The uploads folder is missing and could not be created.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . $check['ext'];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return $fail('The image could not be saved. Check that the uploads folder is writable.');
    }
    @chmod($dir . '/' . $name, 0644);

    $original = preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) ($file['name'] ?? '')));
    return [
        'ok'            => true,
        'path'          => 'uploads/' . $folder . '/' . $name,
        'original_name' => mb_substr($original, 0, 255),
        'mime'          => $check['mime'],
        'size'          => (int) $file['size'],
    ];
}

/** Delete a stored upload by its relative path. Only paths we generated are accepted. */
function delete_uploaded_file(?string $relPath): void
{
    if (!$relPath || !preg_match('#^uploads/(gallery|stories|announcements)/[A-Za-z0-9_-][A-Za-z0-9._-]*$#', $relPath)) {
        return;
    }
    $full = ROOT_PATH . '/public/' . $relPath;
    if (is_file($full)) {
        @unlink($full);
    }
}
