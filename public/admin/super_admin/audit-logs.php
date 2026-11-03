<?php
// superadmin/audit-logs.php - Audit logs: review administrative activity with filters
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/audit-service.php';

try {
    require_superadmin_portal();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$f = audit_clean_filters($_GET);
$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$adminOptions = [];
$loadError = null;
try {
    $pdo = db();
    $list = audit_search($pdo, $f);
    $adminOptions = audit_admin_options($pdo);
} catch (Throwable $e) {
    error_log('Audit logs failed: ' . $e->getMessage());
    $loadError = 'The audit log could not be loaded. If you have not yet run database/super-admin.sql in pgAdmin, run it first; otherwise check the database connection.' . dev_detail($e);
}
$hasFilters = $f['q'] !== '' || $f['admin'] !== '' || $f['module'] !== '' || $f['activity'] !== '' || $f['range'] !== '';
$pageUrl = function (array $p): string {
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return url('superadmin/audit-logs.php') . ($p ? '?' . http_build_query($p) : '');
};
$base = ['q' => $f['q'], 'admin' => $f['admin'], 'module' => $f['module'], 'activity' => $f['activity'], 'range' => $f['range']];

$pageTitle = 'Audit Logs';
$activeNav = 'audit';
require dirname(__DIR__, 3) . '/includes/portal/superadmin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Audit logs</h1>
    <p class="page-subtitle">Review administrative activity on content, donations, and inquiries.</p>
</header>

<?php if ($loadError): ?><div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div><?php endif; ?>

<form class="filter-bar audit-filters" method="get" action="<?= e(url('superadmin/audit-logs.php')) ?>">
    <input class="input filter-search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search activity" maxlength="100" aria-label="Search activity">
    <select class="select" name="admin" aria-label="Administrator">
        <option value="">Administrator</option>
        <?php foreach ($adminOptions as $val => $name): ?>
            <option value="<?= e((string) $val) ?>"<?= $f['admin'] === (string) $val ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="select" name="module" aria-label="Module">
        <option value="">Module</option>
        <?php foreach (AUDIT_MODULE_LABELS as $k => $label): if ($k === 'other') { continue; } ?>
            <option value="<?= e($k) ?>"<?= $f['module'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="select" name="activity" aria-label="Activity">
        <option value="">Activity</option>
        <?php foreach (AUDIT_ACTIVITY_LABELS as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $f['activity'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="select" name="range" aria-label="Date range">
        <option value="">Date range</option>
        <?php foreach (AUDIT_RANGES as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $f['range'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-maroon">Apply</button>
    <?php if ($hasFilters): ?><a class="filter-reset" href="<?= e($pageUrl([])) ?>">Clear</a><?php endif; ?>
</form>

<section class="panel" aria-label="Audit log entries">
    <div class="table-wrap">
        <table class="data-table audit-table">
            <thead>
                <tr><th>Date</th><th>Time</th><th>Administrator</th><th>Activity</th><th>Module</th><th>Action details</th></tr>
            </thead>
            <tbody>
            <?php if (!$list['rows']): ?>
                <tr><td colspan="6" class="table-empty">
                    <?= $hasFilters ? 'No activity matches your filters.' : 'No administrator activity has been recorded yet.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($list['rows'] as $row): ?>
                <tr>
                    <td><?= e(audit_date($row['created_at'])) ?></td>
                    <td><?= e(audit_time($row['created_at'])) ?></td>
                    <td><?= e($row['admin_display']) ?></td>
                    <td><?= audit_activity_badge($row['activity_key']) ?></td>
                    <td><?= e(AUDIT_MODULE_LABELS[$row['module_key']] ?? '') ?></td>
                    <td class="audit-detail"><?= e(audit_describe($row)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($list['pages'] > 1): ?>
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
<p class="muted sa-small"><?= (int) $list['total'] ?> entr<?= $list['total'] === 1 ? 'y' : 'ies' ?><?= $hasFilters ? ' match your filters' : '' ?>. Logins and logouts are hidden unless you choose the Access module.</p>
<?php require dirname(__DIR__, 3) . '/includes/portal/superadmin-footer.php'; ?>
