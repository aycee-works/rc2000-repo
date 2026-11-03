<?php
declare(strict_types=1);

function uploads_root(): string
{
    $dir = dirname(__DIR__) . '/public/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/** @return string|null Public path such as /uploads/donations/file.jpg */
function store_upload(string $field, string $subdir, array &$errors, bool $required = false): ?string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        if ($required) {
            $errors[] = 'Please attach a file.';
        }
        return null;
    }

    $file = $_FILES[$field];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            $errors[] = 'Please attach a file.';
        }
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'The file could not be uploaded. Please try a smaller image.';
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = 'The uploaded file was not valid.';
        return null;
    }

    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        $errors[] = 'Please keep images under 5 MB.';
        return null;
    }

    $info = @getimagesize($tmp);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        $errors[] = 'Please upload a JPG, PNG, WEBP, or GIF image.';
        return null;
    }

    $subdir = preg_replace('/[^a-z0-9_-]/i', '', $subdir) ?: 'files';
    $destDir = uploads_root() . '/' . $subdir;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0775, true);
    }

    $name = bin2hex(random_bytes(12)) . '.' . $map[$mime];
    $dest = $destDir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = 'The file could not be saved.';
        return null;
    }

    return '/uploads/' . $subdir . '/' . $name;
}
