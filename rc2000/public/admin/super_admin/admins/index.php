<?php
// superadmin/admins/index.php - Admin account management: list, search, filter
require_once dirname(__DIR__, 4) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 4) . '/includes/portal/services/admin-account-service.php';

try {
    require_superadmin_portal();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$filters = [
    'q'      => mb_substr(get_str('q'), 0, 100),
    'status' => in_array(get_str('status'), ['active', 'deactivated'], true) ? get_str('status') : '',
    'page'   => max(1, (int) get_str('page')),
];
$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$loadError = null;
try {
    $list = list_admin_accounts(db(), $filters);
} catch (Throwable $e) {
    error_log('Admin list failed: ' . $e->getMessage());
    $loadError = 'The administrator list could not be loaded. Check the database connection and that database/super-admin.sql was run.' . dev_detail($e);
}
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '';
$pageUrl = fn(array $p) => url('superadmin/admins/index.php') . ($p ? '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null)) : '');

$pageTitle = 'Admin Management';
$activeNav = 'admins';
$extraJs   = ['superadmin.js'];
require dirname(__DIR__, 4) . '/includes/portal/superadmin-header.php';
?>
<header class="page-header sa-page-head">
    <div>
        <h1 class="page-title">Admin account management</h1>
        <p class="page-subtitle">Select an administrator to edit, reset the password, deactivate, or remove.</p>
    </div>
    <a class="btn btn-maroon" href="<?= e(url('superadmin/admins/add.php')) ?>">+ Add admin</a>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<form class="filter-bar" method="get" action="<?= e(url('superadmin/admins/index.php')) ?>">
    <input class="input filter-search" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Search by name or email" maxlength="100" aria-label="Search administrators">
    <select class="select filter-select" name="status" aria-label="Filter by status" data-autosubmit>
        <option value="">Status: All</option>
        <?php foreach (ADMIN_STATUS_OPTIONS as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-dark">Search</button>
    <?php if ($hasFilters): ?><a class="filter-reset" href="<?= e($pageUrl([])) ?>">Clear</a><?php endif; ?>
</form>

<section class="panel" aria-label="Administrator accounts">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Date created</th><th>Account</th></tr></thead>
            <tbody>
            <?php if (!$list['rows']): ?>
                <tr><td colspan="5" class="table-empty">
                    <?= $hasFilters ? 'No administrators match your search.' : 'No administrator accounts have been created yet.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($list['rows'] as $a): ?>
                <tr<?= $a['is_active'] ? '' : ' class="is-deactivated"' ?>>
                    <td><?= e($a['name']) ?></td>
                    <td><?= e($a['email']) ?></td>
                    <td><?= $a['is_active']
                        ? '<span class="acct-chip acct-active">Active</span>'
                        : '<span class="acct-chip acct-deactivated">Deactivated</span>' ?></td>
                    <td><?= e(date('M j, Y', strtotime($a['created_at']))) ?></td>
                    <td><a class="btn btn-light btn-sm" href="<?= e(url('superadmin/admins/manage.php?id=' . (int) $a['id'])) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($list['pages'] > 1):
        $base = ['q' => $filters['q'], 'status' => $filters['status']]; ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($list['page'] > 1): ?><a href="<?= e($pageUrl($base + ['page' => $list['page'] - 1])) ?>">Previous</a><?php endif; ?>
        <?php for ($p = max(1, $list['page'] - 2); $p <= min($list['pages'], $list['page'] + 2); $p++): ?>
            <?php if ($p === $list['page']): ?><span class="is-current" aria-current="page"><?= $p ?></span>
            <?php else: ?><a href="<?= e($pageUrl($base + ['page' => $p])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($list['page'] < $list['pages']): ?><a href="<?= e($pageUrl($base + ['page' => $list['page'] + 1])) ?>">Next</a><?php endif; ?>
    </nav>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__, 4) . '/includes/portal/superadmin-footer.php'; ?>
