<?php
/** includes/superadmin-sidebar.php - dark sidebar. Uses $activeNav to highlight the current page. */
$navItems = [
    'dashboard' => ['Dashboard',        'superadmin/dashboard.php'],
    'admins'    => ['Admin Management', 'superadmin/admins/index.php'],
    'audit'     => ['Audit Logs',       'superadmin/audit-logs.php'],
];
?>
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= e(url('superadmin/dashboard.php')) ?>">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="" width="26" height="26" onerror="this.style.display='none'">
        <span>Reading Club 2000</span>
    </a>

    <nav class="sidebar-nav" aria-label="Main">
        <?php foreach ($navItems as $key => [$label, $path]): ?>
            <a href="<?= e(url($path)) ?>"
               class="nav-link<?= $key === $activeNav ? ' is-active' : '' ?>"
               <?= $key === $activeNav ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">Super Admin</div>
        <form method="post" action="<?= e(url('superadmin/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Log out</button>
        </form>
    </div>
</aside>
