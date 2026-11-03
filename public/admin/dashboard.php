<?php
// admin/dashboard.php - Dashboard: overview cards + pending donations/inquiries
require_once dirname(__DIR__, 2) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/portal/services/dashboard-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$cards = null;
$pendingDonations = [];
$pendingInquiries = [];
$loadError = null;

try {
    $pdo = db();
    $cards = dashboard_card_values(get_dashboard_stats($pdo));
    $pendingDonations = get_pending_donations($pdo, 5);
    $pendingInquiries = get_pending_inquiries($pdo, 5);
} catch (Throwable $e) {
    error_log('Dashboard load failed: ' . $e->getMessage());
    $loadError = 'The dashboard data could not be loaded. Please check the database connection and try again.' . dev_detail($e);
}

// Shown if the data could not be loaded
$cards = $cards ?? array_fill_keys([
    'total_donations', 'total_donations_sub', 'pending_donations', 'pending_donations_sub',
    'pending_inquiries', 'pending_inquiries_sub', 'monetary_total', 'monetary_sub',
], '–');

// First name of the logged-in admin, for the welcome message.
// If your login stores the name under a different session key, add it to $nameKeys.
$adminFirstName = '';
$nameKeys = ['full_name', 'admin_name', 'name', 'username'];
$sessionAdmin = $_SESSION['admin'] ?? $_SESSION['user'] ?? [];
foreach ($nameKeys as $key) {
    $candidate = $_SESSION[$key] ?? (is_array($sessionAdmin) ? ($sessionAdmin[$key] ?? '') : '');
    if (is_string($candidate) && trim($candidate) !== '') {
        $adminFirstName = explode(' ', trim($candidate))[0];
        break;
    }
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$extraCss  = ['dashboard.css'];
$extraJs   = ['dashboard.js'];
require dirname(__DIR__, 2) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Welcome, Admin<?= $adminFirstName !== '' ? ' ' . e($adminFirstName) : '' ?>!</h1>
    <p class="page-subtitle">Overview of donations and inquiries.</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<section class="stat-grid stat-grid-4" id="stat-cards" aria-label="Overview">
    <article class="stat-card">
        <div class="stat-label">Total donations</div>
        <div class="stat-value" data-stat="total_donations"><?= e($cards['total_donations']) ?></div>
        <div class="stat-sub" data-stat="total_donations_sub"><?= e($cards['total_donations_sub']) ?></div>
        <a class="stat-link" href="<?= e(url('admin/donations/index.php')) ?>">Review ›</a>
    </article>
    <article class="stat-card">
        <div class="stat-label">Pending donations</div>
        <div class="stat-value" data-stat="pending_donations"><?= e($cards['pending_donations']) ?></div>
        <div class="stat-sub" data-stat="pending_donations_sub"><?= e($cards['pending_donations_sub']) ?></div>
        <a class="stat-link" href="<?= e(url('admin/donations/index.php?status=pending')) ?>">Review ›</a>
    </article>
    <article class="stat-card">
        <div class="stat-label">Pending inquiries</div>
        <div class="stat-value" data-stat="pending_inquiries"><?= e($cards['pending_inquiries']) ?></div>
        <div class="stat-sub" data-stat="pending_inquiries_sub"><?= e($cards['pending_inquiries_sub']) ?></div>
        <a class="stat-link" href="<?= e(url('admin/inquiries/index.php?pending=1')) ?>">Review ›</a>
    </article>
    <article class="stat-card">
        <div class="stat-label">Total monetary donations</div>
        <div class="stat-value" data-stat="monetary_total"><?= e($cards['monetary_total']) ?></div>
        <div class="stat-sub" data-stat="monetary_sub"><?= e($cards['monetary_sub']) ?></div>
        <a class="stat-link" href="<?= e(url('admin/donations/index.php?tab=monetary')) ?>">Review ›</a>
    </article>
</section>

<div class="dashboard-lists">
    <section class="panel" aria-labelledby="pd-title">
        <div class="panel-header">
            <h2 class="panel-title" id="pd-title">Pending donations</h2>
            <a href="<?= e(url('admin/donations/index.php?status=pending')) ?>">View all</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Donation</th><th>Donor</th><th>Type</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (!$pendingDonations): ?>
                    <tr><td colspan="4" class="table-empty">No pending donations. You're all caught up.</td></tr>
                <?php endif; ?>
                <?php foreach ($pendingDonations as $d): ?>
                    <tr>
                        <td><?= e($d['label']) ?></td>
                        <td><?= e($d['donor']) ?></td>
                        <td><?= $d['type'] === 'book' ? 'Book' : 'Monetary' ?></td>
                        <td><a href="<?= e($d['review_url']) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" aria-labelledby="pi-title">
        <div class="panel-header">
            <h2 class="panel-title" id="pi-title">Pending inquiries</h2>
            <a href="<?= e(url('admin/inquiries/index.php?pending=1')) ?>">View all</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>No.</th><th>Subject</th><th>Category</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (!$pendingInquiries): ?>
                    <tr><td colspan="4" class="table-empty">No pending inquiries.</td></tr>
                <?php endif; ?>
                <?php foreach ($pendingInquiries as $q): ?>
                    <tr>
                        <td><?= e($q['reference_no']) ?></td>
                        <td class="cell-truncate" title="<?= e($q['subject']) ?>"><?= e($q['subject']) ?></td>
                        <td><?= e($q['category']) ?></td>
                        <td><a href="<?= e($q['review_url']) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require dirname(__DIR__, 2) . '/includes/portal/admin-footer.php'; ?>