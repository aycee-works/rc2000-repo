<?php
// admin/inquiries/index.php - Manage inquiries (?id= selects a row)
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/inquiry-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

/** Build a link back to this page. Empty values are dropped. */
function inquiries_url(array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return url('admin/inquiries/index.php' . ($params ? '?' . http_build_query($params) : ''));
}

$filters = inquiry_filters_from_request();
$list = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$selected = null;
$responses = [];
$loadError = null;

try {
    $pdo = db();
    $list = list_inquiries($pdo, $filters);
    $filters['page'] = $list['page'];

    $selectedId = (int) ($_GET['id'] ?? 0);
    if ($selectedId > 0) {
        $selected = get_inquiry($pdo, $selectedId);
        if (!$selected) {
            flash('error', 'That inquiry could not be found.');
        }
    }
    if ($selected) {
        $responses = get_inquiry_responses($pdo, (int) $selected['id']);
    }
} catch (Throwable $e) {
    error_log('Inquiries load failed: ' . $e->getMessage());
    $loadError = 'The inquiries could not be loaded. Please check the database connection and try again.';
}

$baseParams = ['q' => $filters['q'], 'category' => $filters['category'], 'status' => $filters['status'], 'page' => $filters['page'] > 1 ? $filters['page'] : ''];
$hasFilters = $filters['q'] !== '' || $filters['category'] !== '' || $filters['status'] !== '';

$pageTitle = 'Manage inquiries';
$activeNav = 'inquiries';
$extraCss  = ['inquiries.css'];
$extraJs   = ['review-dialog.js', 'inquiries.js'];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Manage inquiries</h1>
    <p class="page-subtitle">Review, respond, and resolve inquiries submitted through the website.</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<form class="filter-bar" method="get" action="<?= e(url('admin/inquiries/index.php')) ?>">
    <input class="input filter-search" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Search inquiry number, subject or name" maxlength="100" aria-label="Search inquiries">
    <select class="select filter-select" name="category" aria-label="Filter by category">
        <option value="">Category: All</option>
        <?php foreach (INQUIRY_CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>"<?= $filters['category'] === $cat ? ' selected' : '' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="select filter-select" name="status" aria-label="Filter by status">
        <option value="">Status: All</option>
        <option value="pending"<?= $filters['status'] === 'pending' ? ' selected' : '' ?>>Unresolved (open + in progress)</option>
        <?php foreach (INQUIRY_STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-dark">Search</button>
    <?php if ($hasFilters): ?>
        <a class="filter-reset" href="<?= e(inquiries_url([])) ?>">Clear</a>
    <?php endif; ?>
</form>

<section class="panel" aria-label="Inquiries">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Inquiry no.</th><th>Subject</th><th>Submitted by</th><th>Category</th>
                    <th>Date</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$list['rows']): ?>
                <tr><td colspan="7" class="table-empty">
                    <?= $hasFilters ? 'No inquiries match your search.' : 'No inquiries have been submitted yet.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($list['rows'] as $row):
                $isSelected = $selected && (int) $selected['id'] === (int) $row['id'];
                $reviewUrl = inquiries_url($baseParams + ['id' => (int) $row['id']]);
            ?>
                <tr<?= $isSelected ? ' class="is-selected"' : '' ?>>
                    <td><?= e($row['reference_no']) ?></td>
                    <td class="cell-truncate" title="<?= e($row['subject']) ?>"><?= e($row['subject']) ?></td>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['category']) ?></td>
                    <td><?= e(format_date_short($row['created_at'])) ?></td>
                    <td><?= status_badge($row['status'], 'inquiry') ?></td>
                    <td><a href="<?= e($reviewUrl) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($list['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($list['page'] > 1): ?>
            <a href="<?= e(inquiries_url(array_merge($baseParams, ['page' => $list['page'] - 1]))) ?>">Previous</a>
        <?php endif; ?>
        <?php for ($p = max(1, $list['page'] - 2); $p <= min($list['pages'], $list['page'] + 2); $p++): ?>
            <?php if ($p === $list['page']): ?>
                <span class="is-current" aria-current="page"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= e(inquiries_url(array_merge($baseParams, ['page' => $p > 1 ? $p : '']))) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($list['page'] < $list['pages']): ?>
            <a href="<?= e(inquiries_url(array_merge($baseParams, ['page' => $list['page'] + 1]))) ?>">Next</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>

<?php if ($selected) { require dirname(__DIR__, 3) . '/includes/portal/partials/inquiry-review-dialog.php'; } ?>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
