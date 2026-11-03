<?php
// admin/donations/index.php - Manage donations (tabs: ?tab=book | ?tab=monetary, ?id= selects a row)
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/book-donation-service.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/monetary-donation-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

/** Build a link back to this page, e.g. donations_url(['tab' => 'book', 'id' => 3]). Empty values are dropped. */
function donations_url(array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return url('admin/donations/index.php' . ($params ? '?' . http_build_query($params) : ''));
}

$tab = get_str('tab') === 'monetary' ? 'monetary' : 'book';
$loadError = null;

// ---- Book tab data ----
$summary = ['total' => 0, 'approved' => 0, 'books_received' => 0];
$list = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$selected = null;
$history = ['previous' => 0, 'since' => null];
$filters = book_filters_from_request();

// ---- Monetary tab data ----
$moneySummary = ['total' => 0, 'reported' => 0.0, 'verified' => 0.0, 'awaiting' => 0];
$moneyList = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$moneySelected = null;
$moneyHistory = ['previous' => 0, 'since' => null];
$moneyFilters = money_filters_from_request();

try {
    $pdo = db();
    $selectedId = (int) ($_GET['id'] ?? 0);

    if ($tab === 'book') {
        $summary = get_book_summary($pdo);
        $list = list_book_donations($pdo, $filters);
        $filters['page'] = $list['page'];

        if ($selectedId > 0) {
            $selected = get_book_donation($pdo, $selectedId);
            if (!$selected) {
                flash('error', 'That donation could not be found.');
            }
        }
        if ($selected) {
            $history = get_donor_history($pdo, $selected['donor_email']);
        }
    } else {
        $moneySummary = get_money_summary($pdo);
        $moneyList = list_money_donations($pdo, $moneyFilters);
        $moneyFilters['page'] = $moneyList['page'];

        if ($selectedId > 0) {
            $moneySelected = get_money_donation($pdo, $selectedId);
            if (!$moneySelected) {
                flash('error', 'That donation could not be found.');
            }
        }
        if ($moneySelected) {
            $moneyHistory = get_donor_history($pdo, $moneySelected['donor_email']);
        }
    }
} catch (Throwable $e) {
    error_log('Donations load failed: ' . $e->getMessage());
    $loadError = 'The donations could not be loaded. Please check the database connection and try again.';
}

$pageTitle = 'Manage donations';
$activeNav = 'donations';
$extraCss  = ['donations.css'];
$extraJs   = ['review-dialog.js', $tab === 'book' ? 'book-donations.js' : 'monetary-donations.js'];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Manage donations</h1>
    <p class="page-subtitle">Review book and monetary donation submission.</p>
</header>

<nav class="tabs" aria-label="Donation type">
    <a class="tab<?= $tab === 'book' ? ' is-active' : '' ?>" href="<?= e(donations_url(['tab' => 'book'])) ?>">Book donations</a>
    <a class="tab<?= $tab === 'monetary' ? ' is-active' : '' ?>" href="<?= e(donations_url(['tab' => 'monetary'])) ?>">Monetary donations</a>
</nav>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<?php
require dirname(__DIR__, 3) . '/includes/portal/partials/' . ($tab === 'book' ? 'book-donations-tab.php' : 'monetary-donations-tab.php');
?>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
