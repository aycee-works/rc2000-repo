<?php
require_once __DIR__ . '/functions.php';
$title = isset($pageTitle) ? $pageTitle . ' | Reading Club 2000' : 'Reading Club 2000';
$bodyClass = trim('site ' . (string) ($pageClass ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="/rc2000/public/assets/css/style.css?v=12">
</head>
<body class="<?= e($bodyClass) ?>">
<?php require __DIR__ . '/navbar.php'; ?>
