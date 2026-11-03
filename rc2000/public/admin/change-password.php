<?php
// admin/change-password.php - an administrator replaces the temporary password they were given
require_once dirname(__DIR__, 2) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/portal/services/admin-account-service.php';

try {
    require_admin();            // allowed here even while a temporary password is still active
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$error = null;
$admin = current_admin();
$mustChange = !empty($_SESSION['must_change_password']);

if (is_post()) {
    try {
        csrf_require();
        $r = change_own_password(db(), (int) $admin['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''), (string) ($_POST['confirm'] ?? ''));
        if ($r['ok']) {
            $_SESSION['must_change_password'] = false;
            flash('success', $r['message']);
            redirect('admin/dashboard.php');
        }
        $error = $r['message'];
    } catch (Throwable $e) {
        error_log('Change password error: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password | Reading Club 2000 Admin</title>
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
        <h1 class="login-title">Change your password</h1>
        <div class="login-card">
            <?php if ($mustChange): ?>
                <div class="flash flash-info"><span>You signed in with a temporary password. Choose a new one to continue.</span></div>
            <?php endif; ?>
            <?php if ($error): ?><div class="flash flash-error" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
            <form method="post" action="<?= e(url('admin/change-password.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <label class="field-label" for="current">Temporary password</label>
                <input class="input" type="password" id="current" name="current" autocomplete="current-password" required autofocus>
                <label class="field-label" for="new">New password</label>
                <input class="input" type="password" id="new" name="new" autocomplete="new-password" required>
                <label class="field-label" for="confirm">Confirm new password</label>
                <input class="input" type="password" id="confirm" name="confirm" autocomplete="new-password" required>
                <p class="login-footnote" style="margin:-6px 0 14px;text-align:left">At least <?= (int) MIN_PASSWORD_LENGTH ?> characters, with a letter and a number.</p>
                <button type="submit" class="btn btn-dark btn-block">Save new password</button>
            </form>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>" style="margin-top:12px;text-align:center">
                <?= csrf_field() ?>
                <button type="submit" class="link-button login-footnote">Log out</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
