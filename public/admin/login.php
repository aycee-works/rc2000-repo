<?php
// admin/login.php - Admin Portal login page
require_once dirname(__DIR__, 2) . '/includes/portal/bootstrap.php';

$error = null;

try {
    // Already logged in? Go straight to the dashboard.
    if (admin_portal_session_is_valid()) {
        redirect('admin/dashboard.php');
    }

    if (is_post()) {
        csrf_require();
        $error = admin_login(post_str('email'), $_POST['password'] ?? '', 'admin');
        if ($error === null) {
            redirect('admin/dashboard.php');
        }
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();          // safe, generic message from db()
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    $error = 'Something went wrong. Please try again.';
}

$emailValue = is_post() ? post_str('email') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Portal | Reading Club 2000</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        <h1 class="login-title">Admin Portal</h1>

        <div class="login-card">
            <?php if ($error): ?>
                <div class="flash flash-error" role="alert"><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <?php render_flash_messages(); ?>

            <form method="post" action="<?= e(url('admin/login.php')) ?>" autocomplete="on" novalidate>
                <?= csrf_field() ?>

                <label class="field-label" for="email">Administrator email</label>
                <input class="input" type="email" id="email" name="email" placeholder="name@gmail.com"
                       value="<?= e($emailValue) ?>" required autofocus>

                <label class="field-label" for="password">Password</label>
                <input class="input" type="password" id="password" name="password" placeholder="Enter your password" required>

                <div class="login-forgot">
                    <a href="#" id="forgot-link">Forgot password?</a>
                </div>
                <p class="login-notice" id="forgot-notice" hidden>
                    Password resets are handled by the super administrator. Please contact them to reset your password.
                </p>

                <button type="submit" class="btn btn-dark btn-block">Log in</button>
                <p class="login-footnote">Authorized personnel only.</p>
            </form>
        </div>
    </section>
</div>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
