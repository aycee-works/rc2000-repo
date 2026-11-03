<?php
// superadmin/admins/add.php - Add admin account
require_once dirname(__DIR__, 4) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 4) . '/includes/portal/services/admin-account-service.php';

try {
    require_superadmin_portal();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$old = ['name' => '', 'email' => '', 'status' => 'active', 'gender' => '', 'setup' => 'link'];
$errors = [];
$formError = null;

if (is_post()) {
    csrf_require();
    $old = [
        'name'   => post_str('name'),
        'email'  => post_str('email'),
        'status' => post_str('status'),
        'gender' => post_str('gender'),
        'setup'  => post_str('setup'),
    ];
    try {
        $r = create_admin_account(db(), (int) current_admin()['id'], $old);
        if ($r['ok']) {
            $_SESSION['sa_secret'] = $r['secret'];
            flash('success', 'Admin account created.');
            redirect('superadmin/admins/manage.php?id=' . (int) $r['id']);
        }
        $errors = $r['errors'] ?? [];
        $formError = $errors ? null : $r['message'];
    } catch (Throwable $e) {
        error_log('Create admin failed: ' . $e->getMessage());
        $formError = 'The account could not be created. Please try again.' . dev_detail($e);
    }
}
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';

$pageTitle = 'Add admin';
$activeNav = 'admins';
require dirname(__DIR__, 4) . '/includes/portal/superadmin-header.php';
?>
<nav class="sa-crumbs" aria-label="Breadcrumb"><a href="<?= e(url('superadmin/admins/index.php')) ?>">Admin accounts</a> &gt; Add admin</nav>
<header class="page-header">
    <h1 class="page-title">Add admin account</h1>
    <p class="page-subtitle">Create a new administrator who can manage the website.</p>
</header>

<?php if ($formError): ?><div class="flash flash-error" role="alert"><span><?= e($formError) ?></span></div><?php endif; ?>

<form method="post" action="<?= e(url('superadmin/admins/add.php')) ?>" class="sa-card" novalidate>
    <?= csrf_field() ?>
    <div class="sa-card-body">
        <h2 class="sa-card-title">Account details</h2>
        <div class="sa-form-grid">
            <div class="field">
                <label class="sa-label-b" for="name">Admin name</label>
                <input class="input" id="name" name="name" maxlength="100" placeholder="Full name" value="<?= e($old['name']) ?>" required autofocus>
                <?= $err('name') ?>
            </div>
            <div class="field">
                <label class="sa-label-b" for="role">Role</label>
                <input class="input input-readonly" id="role" value="Administrator" readonly tabindex="-1">
            </div>
            <div class="field">
                <label class="sa-label-b" for="email">Email address</label>
                <input class="input" type="email" id="email" name="email" maxlength="150" placeholder="@gmail.com" value="<?= e($old['email']) ?>" required>
                <?= $err('email') ?>
            </div>
            <div class="field">
                <label class="sa-label-b" for="status">Account status</label>
                <select class="select" id="status" name="status">
                    <?php foreach (ADMIN_STATUS_OPTIONS as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= $old['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('status') ?>
            </div>
            <fieldset class="field sa-radios">
                <legend class="sa-label-b">Password setup</legend>
                <label><input type="radio" name="setup" value="link"<?= $old['setup'] !== 'temp' ? ' checked' : '' ?>> Create a set-password link</label>
                <label><input type="radio" name="setup" value="temp"<?= $old['setup'] === 'temp' ? ' checked' : '' ?>> Set a temporary password</label>
                <?= $err('setup') ?>
            </fieldset>
            <div class="field">
                <label class="sa-label-b" for="gender">Gender</label>
                <select class="select" id="gender" name="gender">
                    <option value="">Select gender</option>
                    <?php foreach (ADMIN_GENDERS as $g): ?>
                        <option value="<?= e($g) ?>"<?= $old['gender'] === $g ? ' selected' : '' ?>><?= e($g) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('gender') ?>
            </div>
        </div>
        <p class="review-hint">Email is not set up for this system, so nothing is emailed. After creating the account you will see the link (or the temporary password) once, to give to the administrator yourself. A temporary password must be changed at first login.</p>
    </div>
    <div class="sa-card-foot">
        <a class="btn btn-light" href="<?= e(url('superadmin/admins/index.php')) ?>">Cancel</a>
        <button type="submit" class="btn btn-maroon">+ Create account</button>
    </div>
</form>
<?php require dirname(__DIR__, 4) . '/includes/portal/superadmin-footer.php'; ?>
