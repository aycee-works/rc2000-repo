<?php
/**
 * includes/superadmin-header.php
 * Opens a Super Admin page: <head>, dark sidebar and main area.
 * Set BEFORE including: $pageTitle, $activeNav ('dashboard' | 'admins' | 'audit'), optional $extraCss / $extraJs.
 */
$pageTitle = $pageTitle ?? 'Super Admin';
$activeNav = $activeNav ?? '';
$extraCss  = $extraCss ?? [];
// Signed-in pages must not be shown from the browser cache after logging out (back button)
if (!headers_sent()) { header('Cache-Control: no-store'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-base" content="<?= e(BASE_URL) ?>">
    <title><?= e($pageTitle) ?> | Reading Club 2000 Super Admin</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/superadmin.css')) ?>">
<?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?= e(asset('css/' . $css)) ?>">
<?php endforeach; ?>
</head>
<body class="admin-body superadmin-body">
<div class="admin-shell">
    <?php require __DIR__ . '/superadmin-sidebar.php'; ?>
    <main class="admin-main">
        <?php render_flash_messages(); ?>
