<?php
/**
 * includes/admin-sidebar.php
 * Included by admin-header.php. Uses $activeNav to highlight the current page.
 */
$admin = current_admin();
$navItems = [
    'dashboard' => ['Dashboard',        'admin/dashboard.php'],
    'donations' => ['Manage donations', 'admin/donations/index.php'],
    'content'   => ['Manage content',   'admin/content/index.php'],
    'inquiries' => ['Manage inquiries', 'admin/inquiries/index.php'],
];
?>
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
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
        <div class="sidebar-user"><?= e($admin['name'] ?? '') ?></div>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Log out</button>
        </form>
    </div>
</aside>
