<?php
// admin/set-password.php?token=... - an administrator sets a password from a link given by the Super Admin
require_once dirname(__DIR__, 2) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/portal/services/admin-account-service.php';

// The link contains a secret: never send it on to other sites (e.g. the font provider) as a referrer
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$token = is_post() ? (string) ($_POST['token'] ?? '') : (string) ($_GET['token'] ?? '');
$error = null;
$valid = false;

try {
    $pdo = db();
    $valid = acct_find_valid_token($pdo, $token) !== null;

    if ($valid && is_post()) {
        csrf_require();
        $r = set_password_with_token($pdo, $token, (string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''));
        if ($r['ok']) {
            flash('success', $r['message']);
            redirect('admin/login.php');
        }
        $error = $r['message'];
        $valid = acct_find_valid_token($pdo, $token) !== null;
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    error_log('Set password error: ' . $e->getMessage());
    $error = 'Something went wrong. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Set your password | Reading Club 2000</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/login.css')) ?>">
</head>
<body class="login-body">
<div class="login-page">
    <section class="login-brand">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="Reading Club 2000 logo" class="login-logo" onerror="this.style.display='none'">
        <p class="login-brand-name">Reading Club 2000</p>
    </section>
    <section class="login-panel">
        <h1 class="login-title">Set your password</h1>
        <div class="login-card">
            <?php if ($error): ?><div class="flash flash-error" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
            <?php if (!$valid): ?>
                <?php if (!$error): ?>
                <div class="flash flash-error" role="alert"><span>This link is invalid, has expired, or was already used. Ask the super administrator for a new one.</span></div>
                <?php endif; ?>
                <a class="btn btn-dark btn-block" href="<?= e(url('admin/login.php')) ?>">Go to login</a>
            <?php else: ?>
            <form method="post" action="<?= e(url('admin/set-password.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label class="field-label" for="password">New password</label>
                <input class="input" type="password" id="password" name="password" autocomplete="new-password" required autofocus>
                <label class="field-label" for="confirm">Confirm password</label>
                <input class="input" type="password" id="confirm" name="confirm" autocomplete="new-password" required>
                <p class="login-footnote" style="margin:-6px 0 14px;text-align:left">At least <?= (int) MIN_PASSWORD_LENGTH ?> characters, with a letter and a number.</p>
                <button type="submit" class="btn btn-dark btn-block">Save password</button>
            </form>
            <?php endif; ?>
        </div>
    </section>
</div>
</body>
</html>
