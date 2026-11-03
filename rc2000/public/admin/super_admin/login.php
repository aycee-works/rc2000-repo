<?php
// superadmin/login.php - Super Admin Portal login page
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';

$error = null;

try {
    if (superadmin_session_is_valid()) {
        redirect('superadmin/dashboard.php');
    }

    if (is_post()) {
        csrf_require();
        // Only accounts with the super_admin role can log in here
        $error = admin_login(post_str('email'), $_POST['password'] ?? '', 'super_admin');
        if ($error === null) {
            redirect('superadmin/dashboard.php');
        }
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();          // safe, generic message from db()
} catch (Throwable $e) {
    error_log('Super admin login error: ' . $e->getMessage());
    $error = 'Something went wrong. Please try again.';
}

$emailValue = is_post() ? post_str('email') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Portal | Reading Club 2000</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/superadmin.css')) ?>">
</head>
<body class="sa-login-body">
<div class="sa-login-page">
    <section class="sa-login-welcome">
        <h1>Welcome, Super Admin.</h1>
        <p>Unauthorized access prohibited.</p>
    </section>

    <section class="sa-login-panel">
        <div class="sa-login-box">
            <div class="sa-login-brand">
                <img src="<?= e(asset('images/logo.png')) ?>" alt="" width="22" height="22" onerror="this.style.display='none'">
                <span>Reading Club 2000</span>
            </div>
            <h2 class="sa-login-title">Super Admin Portal</h2>

            <?php if ($error): ?>
                <div class="flash flash-error" role="alert"><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <?php render_flash_messages(); ?>

            <form method="post" action="<?= e(url('superadmin/login.php')) ?>" autocomplete="on" novalidate>
                <?= csrf_field() ?>

                <label class="sa-label" for="email">Email</label>
                <input class="input sa-input" type="email" id="email" name="email" placeholder="name@gmail.com"
                       value="<?= e($emailValue) ?>" required autofocus>

                <label class="sa-label" for="password">Password</label>
                <input class="input sa-input" type="password" id="password" name="password" placeholder="Enter your password" required>

                <div class="sa-forgot"><a href="#" id="forgot-link">Forgot password?</a></div>
                <p class="login-notice" id="forgot-notice" hidden>
                    There is no one above the super administrator to reset this password from the website.
                    The system owner can reset it from the server with <code>php database/reset-super-admin-password.php</code>.
                </p>

                <button type="submit" class="btn btn-maroon btn-block">Log in</button>
                <p class="sa-footnote">Authorized personnel only.</p>
            </form>
        </div>
    </section>
</div>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
