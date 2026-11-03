<?php
// superadmin/admins/manage.php?id=..&tab=edit|reset|danger - manage one administrator account
require_once dirname(__DIR__, 4) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 4) . '/includes/portal/services/admin-account-service.php';

try {
    require_superadmin_portal();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$id  = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$tab = in_array($_POST['tab'] ?? $_GET['tab'] ?? '', ['edit', 'reset', 'danger'], true) ? ($_POST['tab'] ?? $_GET['tab']) : 'edit';
$self = fn(string $t = 'edit') => 'superadmin/admins/manage.php?id=' . $id . '&tab=' . $t;

$errors = [];
$formError = null;
$old = null;

try {
    $pdo = db();
    $admin = $id > 0 ? get_admin_account($pdo, $id) : null;
    if (!$admin) {
        flash('error', 'That administrator account was not found. It may have been removed.');
        redirect('superadmin/admins/index.php');
    }

    if (is_post()) {
        csrf_require();
        $do = post_str('do');
        $superId = (int) current_admin()['id'];

        if ($do === 'edit') {
            $old = ['name' => post_str('name'), 'email' => post_str('email'), 'status' => post_str('status'), 'version' => post_str('version')];
            $r = update_admin_account($pdo, $superId, $id, $old);
            if ($r['ok']) { flash('success', $r['message']); redirect($self('edit')); }
            $errors = $r['errors'] ?? [];
            $formError = $errors ? null : $r['message'];
        } elseif ($do === 'reset') {
            $r = reset_admin_password($pdo, $superId, $id, isset($_POST['sign_out']));
            if ($r['ok']) { $_SESSION['sa_secret'] = $r['secret']; flash('success', $r['message']); redirect($self('reset')); }
            $formError = $r['message'];
            $tab = 'reset';
        } elseif ($do === 'deactivate' || $do === 'reactivate') {
            $r = set_admin_active($pdo, $superId, $id, $do === 'reactivate');
            flash($r['ok'] ? 'success' : 'error', $r['message']);
            redirect($self('danger'));
        } elseif ($do === 'remove') {
            $r = remove_admin_account($pdo, $superId, $id, post_str('confirm_email'));
            if ($r['ok']) { flash('success', $r['message']); redirect('superadmin/admins/index.php'); }
            $formError = $r['message'];
            $tab = 'danger';
        }
    }
} catch (Throwable $e) {
    error_log('Manage admin failed: ' . $e->getMessage());
    $admin = $admin ?? null;
    if (!$admin) {
        flash('error', 'The account could not be loaded. Please try again.');
        redirect('superadmin/admins/index.php');
    }
    $formError = 'Something went wrong. Please try again.' . dev_detail($e);
}

// One-time secret (set-password link / temporary password) for this account
$secret = null;
if (!empty($_SESSION['sa_secret']) && (int) $_SESSION['sa_secret']['admin_id'] === $id) {
    $secret = $_SESSION['sa_secret'];
    unset($_SESSION['sa_secret']);
}

$v = $old ?? ['name' => $admin['name'], 'email' => $admin['email'], 'status' => $admin['is_active'] ? 'active' : 'deactivated', 'version' => $admin['updated_at']];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$chip = $admin['is_active'] ? '<span class="acct-chip acct-active">Active</span>' : '<span class="acct-chip acct-deactivated">Deactivated</span>';

$pageTitle = 'Manage ' . $admin['name'];
$activeNav = 'admins';
$extraJs   = ['superadmin.js'];
require dirname(__DIR__, 4) . '/includes/portal/superadmin-header.php';
?>
<nav class="sa-crumbs" aria-label="Breadcrumb"><a href="<?= e(url('superadmin/admins/index.php')) ?>">Admin accounts</a> &gt; Manage</nav>

<section class="sa-card sa-account-head">
    <div>
        <h1 class="sa-account-title"><?= e($admin['name']) ?></h1>
        <p class="sa-account-sub"><?= e($admin['email']) ?> · Administrator · Created <?= e(date('M j, Y', strtotime($admin['created_at']))) ?></p>
    </div>
    <?= $chip ?>
</section>

<?php if ($secret): ?>
<section class="sa-secret" role="status">
    <?php if ($secret['type'] === 'link'): ?>
        <h2>Set-password link</h2>
        <p>Nothing was emailed. Copy this link now and send it to the administrator yourself. It works once and expires in <?= (int) ADMIN_TOKEN_HOURS ?> hours. It will not be shown again.</p>
    <?php else: ?>
        <h2>Temporary password</h2>
        <p>Give this password to the administrator. They must change it at first login. It will not be shown again.</p>
    <?php endif; ?>
    <div class="sa-secret-row">
        <input class="input" id="secret-value" value="<?= e($secret['value']) ?>" readonly>
        <button type="button" class="btn btn-dark" data-copy-target="secret-value">Copy</button>
    </div>
</section>
<?php endif; ?>

<?php if ($formError): ?><div class="flash flash-error" role="alert"><span><?= e($formError) ?></span></div><?php endif; ?>

<div class="sa-manage-grid">
    <nav class="sa-card sa-manage-nav" aria-label="Manage account">
        <p class="sa-manage-kicker">MANAGE ACCOUNT</p>
        <a href="<?= e(url($self('edit'))) ?>"   class="<?= $tab === 'edit'   ? 'is-active' : '' ?>">Edit details</a>
        <a href="<?= e(url($self('reset'))) ?>"  class="<?= $tab === 'reset'  ? 'is-active' : '' ?>">Reset password</a>
        <a href="<?= e(url($self('danger'))) ?>" class="<?= $tab === 'danger' ? 'is-active' : '' ?>">Deactivate or remove</a>
    </nav>

    <div>
    <?php if ($tab === 'edit'): ?>
        <form method="post" action="<?= e(url('superadmin/admins/manage.php')) ?>" class="sa-card" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="edit"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tab" value="edit">
            <input type="hidden" name="version" value="<?= e($v['version']) ?>">
            <div class="sa-card-body">
                <h2 class="sa-card-title">Edit details</h2>
                <p class="sa-card-sub">Update the administrator's name, email, and status.</p>
                <div class="sa-form-grid">
                    <div class="field"><label class="sa-label-b" for="name">Admin name</label>
                        <input class="input" id="name" name="name" maxlength="100" value="<?= e($v['name']) ?>" required><?= $err('name') ?></div>
                    <div class="field"><label class="sa-label-b" for="role">Role</label>
                        <input class="input input-readonly" id="role" value="Administrator" readonly tabindex="-1"></div>
                    <div class="field"><label class="sa-label-b" for="email">Email address</label>
                        <input class="input" type="email" id="email" name="email" maxlength="150" value="<?= e($v['email']) ?>" required><?= $err('email') ?></div>
                    <div class="field"><label class="sa-label-b" for="status">Account status</label>
                        <select class="select" id="status" name="status">
                            <?php foreach (ADMIN_STATUS_OPTIONS as $k => $label): ?>
                                <option value="<?= e($k) ?>"<?= $v['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select><?= $err('status') ?></div>
                </div>
            </div>
            <div class="sa-card-foot">
                <a class="btn btn-light" href="<?= e(url('superadmin/admins/index.php')) ?>">Cancel</a>
                <button type="submit" class="btn btn-maroon">Save changes</button>
            </div>
        </form>

    <?php elseif ($tab === 'reset'): ?>
        <form method="post" action="<?= e(url('superadmin/admins/manage.php')) ?>" class="sa-card">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="reset"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tab" value="reset">
            <div class="sa-card-body">
                <h2 class="sa-card-title">Reset password</h2>
                <p class="sa-card-sub">Create a link the administrator uses to set a new password.</p>
                <div class="sa-linkbox">Link is for<br><strong><?= e($admin['email']) ?></strong></div>
                <label class="sa-check"><input type="checkbox" name="sign_out" value="1" checked> Sign the admin out of active sessions</label>
                <p class="review-hint">Email is not set up, so the link is shown to you once to pass on. Creating a new link cancels any earlier unused one.</p>
            </div>
            <div class="sa-card-foot">
                <a class="btn btn-light" href="<?= e(url('superadmin/admins/index.php')) ?>">Cancel</a>
                <button type="submit" class="btn btn-maroon"<?= $admin['is_active'] ? '' : ' disabled title="Reactivate the account first"' ?>>Create reset link</button>
            </div>
        </form>

    <?php else: ?>
        <section class="sa-card">
            <div class="sa-card-body">
                <h2 class="sa-card-title">Deactivate or remove</h2>
                <p class="sa-card-sub">Control the administrator's access to the website.</p>

                <div class="sa-danger sa-danger-warn">
                    <div>
                        <strong><?= $admin['is_active'] ? 'Deactivate account' : 'Reactivate account' ?></strong>
                        <p><?= $admin['is_active']
                            ? 'Signs the admin out and blocks login until reactivated. Account and activity history are kept.'
                            : 'This account is deactivated and cannot log in. Reactivating lets the administrator log in again.' ?></p>
                    </div>
                    <form method="post" action="<?= e(url('superadmin/admins/manage.php')) ?>"
                          data-confirm="<?= $admin['is_active'] ? 'Deactivate this account? The administrator will be signed out.' : 'Reactivate this account?' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="<?= $admin['is_active'] ? 'deactivate' : 'reactivate' ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit" class="btn <?= $admin['is_active'] ? 'btn-amber' : 'btn-green' ?>"><?= $admin['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                    </form>
                </div>

                <div class="sa-danger sa-danger-red">
                    <div>
                        <strong>Remove account</strong>
                        <p>Permanently deletes the account. This cannot be undone. Past audit log entries stay in the record.</p>
                    </div>
                    <button type="button" class="btn btn-red" data-open-dialog="remove-dialog">Remove account</button>
                </div>
            </div>
        </section>

        <dialog class="dialog" id="remove-dialog" aria-labelledby="remove-title">
            <form method="post" action="<?= e(url('superadmin/admins/manage.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="remove"><input type="hidden" name="id" value="<?= $id ?>">
                <h2 class="dialog-title" id="remove-title">Remove this account?</h2>
                <p>This permanently deletes <strong><?= e($admin['name']) ?></strong>. To confirm, type the email address
                   <strong><?= e($admin['email']) ?></strong> below.</p>
                <input class="input" name="confirm_email" autocomplete="off" placeholder="Type the email address" required>
                <div class="dialog-foot">
                    <button type="button" class="btn btn-light" data-dialog-close>Cancel</button>
                    <button type="submit" class="btn btn-red">Remove permanently</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
    </div>
</div>
<?php require dirname(__DIR__, 4) . '/includes/portal/superadmin-footer.php'; ?>
