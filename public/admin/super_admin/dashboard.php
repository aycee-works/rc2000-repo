<?php
// superadmin/dashboard.php - Super Admin dashboard: account counts, recent activity, admin accounts
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/superadmin-dashboard-service.php';

try {
    require_superadmin_portal();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$counts = $actions = null;
$recent = $accounts = [];
$loadError = null;
try {
    $pdo      = db();
    $counts   = superadmin_account_counts($pdo);
    $actions  = superadmin_action_counts($pdo);
    $recent   = audit_recent($pdo, 5, true);
    $accounts = superadmin_account_preview($pdo, 5);
} catch (Throwable $e) {
    error_log('Super admin dashboard failed: ' . $e->getMessage());
    $loadError = 'The dashboard data could not be loaded. If you have not yet run database/super-admin.sql in pgAdmin, run it first; otherwise check the database connection.' . dev_detail($e);
}

$maxModule = $actions ? max(1, ...array_values($actions['by_module'])) : 1;

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$extraCss  = ['dashboard.css'];
require dirname(__DIR__, 3) . '/includes/portal/superadmin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Super Admin dashboard</h1>
    <p class="page-subtitle">Monitor administrator accounts and recent administrator activity</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<section class="stat-grid stat-grid-4" aria-label="Overview">
    <article class="stat-card">
        <div class="stat-label">Active administrators</div>
        <div class="stat-value"><?= $counts ? (int) $counts['active'] : '–' ?></div>
        <div class="stat-sub"><?= $counts ? 'of ' . (int) $counts['total'] . ' admin account' . ($counts['total'] === 1 ? '' : 's') : '' ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Deactivated accounts</div>
        <div class="stat-value"><?= $counts ? (int) $counts['deactivated'] : '–' ?></div>
        <a class="stat-link" href="<?= e(url('superadmin/admins/index.php?status=deactivated')) ?>">Review accounts ›</a>
    </article>
    <article class="stat-card">
        <div class="stat-label">Admin actions today</div>
        <div class="stat-value"><?= $actions ? (int) $actions['today'] : '–' ?></div>
        <div class="stat-sub"><?= e(date('M j, Y')) ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Admin actions this week</div>
        <div class="stat-value"><?= $actions ? (int) $actions['week'] : '–' ?></div>
        <a class="stat-link" href="<?= e(url('superadmin/audit-logs.php')) ?>">Open audit logs ›</a>
    </article>
</section>

<div class="sa-dash-grid">
    <div class="sa-dash-main">
        <section class="panel" aria-label="Recent administrative activity">
            <div class="panel-header">
                <h2 class="panel-title">Recent administrative activity</h2>
                <a href="<?= e(url('superadmin/audit-logs.php')) ?>">View all</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Date</th><th>Time</th><th>Administrator</th><th>Activity</th><th>Module</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="5" class="table-empty">No administrator activity has been recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e(audit_date($row['created_at'])) ?></td>
                            <td><?= e(audit_time($row['created_at'])) ?></td>
                            <td><?= e($row['admin_display']) ?></td>
                            <td><?= audit_activity_badge($row['activity_key']) ?></td>
                            <td><?= e(AUDIT_MODULE_LABELS[$row['module_key']] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-label="Activity by module">
            <div class="panel-header">
                <h2 class="panel-title">Activity by module</h2>
                <span class="muted sa-small">This week · <?= $actions ? (int) $actions['week'] : 0 ?> action<?= $actions && $actions['week'] === 1 ? '' : 's' ?></span>
            </div>
            <div class="panel-body">
                <?php foreach (AUDIT_WORK_MODULES as $mod):
                    $n = $actions ? $actions['by_module'][$mod] : 0; ?>
                    <div class="sa-bar-row">
                        <span class="sa-bar-label"><?= e(AUDIT_MODULE_LABELS[$mod]) ?></span>
                        <span class="sa-bar-track"><span class="sa-bar-fill" style="width: <?= (int) round($n / $maxModule * 100) ?>%"></span></span>
                        <span class="sa-bar-count"><?= (int) $n ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <aside class="sa-dash-side">
        <section class="panel" aria-label="Admin accounts">
            <div class="panel-header"><h2 class="panel-title">Admin accounts</h2></div>
            <ul class="sa-account-list">
                <?php if (!$accounts): ?>
                    <li class="table-empty">No admin accounts yet.</li>
                <?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                    <li>
                        <div>
                            <div class="sa-account-name"><?= e($a['name']) ?></div>
                            <div class="sa-account-meta">
                                <?= $a['last_login_at'] ? 'Last active ' . e(date('M j, g:i A', strtotime($a['last_login_at']))) : 'Never logged in' ?>
                            </div>
                        </div>
                        <?= $a['is_active']
                            ? '<span class="acct-chip acct-active">Active</span>'
                            : '<span class="acct-chip acct-deactivated">Deactivated</span>' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="panel-body">
                <a class="btn btn-maroon btn-block" href="<?= e(url('superadmin/admins/index.php')) ?>">Manage admin accounts</a>
            </div>
        </section>
    </aside>
</div>
<?php require dirname(__DIR__, 3) . '/includes/portal/superadmin-footer.php'; ?>
