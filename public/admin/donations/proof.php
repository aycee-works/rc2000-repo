<?php
/**
 * admin/donations/proof.php?id=123
 * Shows the proof of payment for one monetary donation.
 * Proofs live in uploads/proofs/, which the web server blocks, so this
 * admin-only script is the ONLY way to view them.
 */
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';

try {
    require_admin();

    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT proof_path FROM monetary_donations WHERE id = ?');
    $stmt->execute([$id]);
    $path = $stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('Proof viewer error: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not load the proof of payment.');
}

$privateBase = realpath(ROOT_PATH . '/uploads/proofs');
$publicProofBase = realpath(ROOT_PATH . '/public/uploads/proofs');
$base = $privateBase;
$file = false;
if ($path && str_starts_with($path, '/uploads/proofs/')) {
    // Older public form uploads are stored under public/uploads, where .htaccess
    // blocks direct access. This authenticated endpoint can safely serve them.
    $base = $publicProofBase;
    $file = $base ? realpath($base . '/' . basename($path)) : false;
} elseif ($path) {
    $file = realpath(ROOT_PATH . '/' . $path);
}

// The file must exist AND be inside uploads/proofs (blocks "../" tricks)
if (!$base || !$file || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    http_response_code(404);
    exit('No proof of payment was found for this donation.');
}

// Decide the type from the file's real content, not its name
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('This file type cannot be displayed.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="proof-' . $id . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($file);
