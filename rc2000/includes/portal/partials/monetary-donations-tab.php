<?php
/**
 * includes/partials/monetary-donations-tab.php
 * The "Monetary donations" tab. Included by admin/donations/index.php, which
 * provides: $moneySummary, $moneyList, $moneyFilters, $moneySelected, $moneyHistory and donations_url().
 */
$baseParams = ['tab' => 'monetary', 'q' => $moneyFilters['q'], 'status' => $moneyFilters['status'], 'page' => $moneyFilters['page'] > 1 ? $moneyFilters['page'] : ''];
$hasFilters = $moneyFilters['q'] !== '' || $moneyFilters['status'] !== '';
$m = $moneySelected;
?>
<section class="stat-grid stat-grid-3" aria-label="Monetary donation summary">
    <article class="stat-card">
        <div class="stat-label">Reported amount</div>
        <div class="stat-value"><?= e(format_peso($moneySummary['reported'])) ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Verified amount</div>
        <div class="stat-value"><?= e(format_peso($moneySummary['verified'])) ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Awaiting verification</div>
        <div class="stat-value"><?= (int) $moneySummary['awaiting'] ?></div>
    </article>
</section>

<form class="filter-bar" method="get" action="<?= e(url('admin/donations/index.php')) ?>">
    <input type="hidden" name="tab" value="monetary">
    <input class="input filter-search" type="search" name="q" value="<?= e($moneyFilters['q']) ?>"
           placeholder="Search ticket no., donor, method or transaction ref" maxlength="100" aria-label="Search monetary donations">
    <select class="select filter-select" name="status" aria-label="Filter by status">
        <option value="">Status: All</option>
        <?php foreach (MONEY_STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $moneyFilters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-dark">Search</button>
    <?php if ($hasFilters): ?>
        <a class="filter-reset" href="<?= e(donations_url(['tab' => 'monetary'])) ?>">Clear</a>
    <?php endif; ?>
</form>

<section class="panel" aria-label="Monetary donations">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket No.</th><th>Donor</th><th>Reported amount</th>
                    <th>Method</th><th>Date</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$moneyList['rows']): ?>
                <tr><td colspan="7" class="table-empty">
                    <?= $hasFilters ? 'No monetary donations match your search.' : 'No monetary donations have been reported yet.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($moneyList['rows'] as $row):
                $isSelected = $m && (int) $m['id'] === (int) $row['id'];
                $reviewUrl = donations_url($baseParams + ['id' => (int) $row['id']]);
            ?>
                <tr<?= $isSelected ? ' class="is-selected"' : '' ?>>
                    <td><?= e($row['reference_no']) ?></td>
                    <td><?= e($row['donor_name']) ?></td>
                    <td><?= e(format_peso($row['reported_amount'])) ?></td>
                    <td><?= e($row['payment_method'] ?? '') ?></td>
                    <td><?= e(format_date_short($row['created_at'])) ?></td>
                    <td><?= status_badge($row['status'], 'money') ?></td>
                    <td><a href="<?= e($reviewUrl) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($moneyList['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($moneyList['page'] > 1): ?>
            <a href="<?= e(donations_url(array_merge($baseParams, ['page' => $moneyList['page'] - 1]))) ?>">Previous</a>
        <?php endif; ?>
        <?php for ($p = max(1, $moneyList['page'] - 2); $p <= min($moneyList['pages'], $moneyList['page'] + 2); $p++): ?>
            <?php if ($p === $moneyList['page']): ?>
                <span class="is-current" aria-current="page"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= e(donations_url(array_merge($baseParams, ['page' => $p > 1 ? $p : '']))) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($moneyList['page'] < $moneyList['pages']): ?>
            <a href="<?= e(donations_url(array_merge($baseParams, ['page' => $moneyList['page'] + 1]))) ?>">Next</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>

<?php if ($m):
    $historyText = (int) $moneyHistory['previous'] . ' previous donation' . ($moneyHistory['previous'] === 1 ? '' : 's')
        . ($moneyHistory['since'] ? ' · member since ' . $moneyHistory['since'] : '');
    $proofHtml = $m['proof_path']
        ? '<a href="' . e(url('admin/donations/proof.php?id=' . (int) $m['id'])) . '" target="_blank" rel="noopener">View proof of payment</a>'
        : '<span class="muted">None submitted</span>';
?>
<dialog class="dialog dialog-review" id="review-dialog" aria-labelledby="review-title" data-autoopen>
    <form id="money-review-form" class="review-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
        <input type="hidden" name="version" value="<?= e($m['updated_at']) ?>">

        <header class="dialog-head">
            <div>
                <p class="dialog-kicker">Monetary donation · <?= e($m['reference_no']) ?></p>
                <h2 class="dialog-title" id="review-title">Reported <?= e(format_peso($m['reported_amount'])) ?></h2>
            </div>
            <div class="dialog-head-side">
                <?= status_badge($m['status'], 'money') ?>
                <button type="button" class="dialog-x" data-dialog-close aria-label="Close">&times;</button>
            </div>
        </header>

        <div class="dialog-body">
            <section>
                <h3 class="review-heading">Payment details</h3>
                <dl class="detail-grid">
                    <?= detail_item('Ticket no.', e($m['reference_no'])) ?>
                    <?= detail_item('Reported amount', '<strong>' . e(format_peso($m['reported_amount'])) . '</strong>') ?>
                    <?= detail_item('Payment method', e($m['payment_method'] ?? '')) ?>
                    <?= detail_item('Transaction reference', e($m['transaction_ref'] ?? '')) ?>
                    <?= detail_item('Proof of payment', $proofHtml) ?>
                    <?= detail_item('Reported on', e(format_datetime($m['created_at']))) ?>
                </dl>
            </section>

            <section>
                <h3 class="review-heading">Donor</h3>
                <dl class="detail-grid">
                    <?= detail_item('Name', e($m['donor_name'])) ?>
                    <?= detail_item('Email', e($m['donor_email'])) ?>
                    <?= detail_item('Contact number', e($m['donor_contact'] ?? '')) ?>
                    <?= detail_item('Donation history', e($historyText)) ?>
                </dl>
            </section>

            <section class="review-decision">
                <h3 class="review-heading">Verification</h3>
                <div class="decision-grid">
                    <div class="field">
                        <label class="field-label-sm" for="money-amount">Verified amount</label>
                        <input class="input" type="text" inputmode="decimal" id="money-amount" name="verified_amount" autofocus
                               placeholder="₱0.00" autocomplete="off"
                               value="<?= $m['status'] === 'verified' && $m['verified_amount'] !== null ? e(number_format((float) $m['verified_amount'], 2, '.', '')) : '' ?>">
                    </div>
                    <div class="field">
                        <label class="field-label-sm" for="money-note">Verification note</label>
                        <input class="input" type="text" id="money-note" name="note" maxlength="255"
                               placeholder="e.g. Bank receipt"
                               value="<?= e($m['verification_note'] ?? '') ?>">
                    </div>
                </div>
                <p class="review-hint">Enter the amount you actually confirmed. A note is required if it differs from the reported amount, or when marking as not verified. The reported amount is never changed.</p>
                <?php if ($m['verified_at']): ?>
                    <p class="review-meta">
                        <?= $m['status'] === 'verified' ? 'Verified' : 'Last reviewed' ?>
                        <?= e(format_datetime($m['verified_at'])) ?><?= $m['verifier_name'] ? ' by ' . e($m['verifier_name']) : '' ?>
                        <?= $m['status'] === 'verified' && $m['verified_amount'] !== null ? '· confirmed ' . e(format_peso($m['verified_amount'])) : '' ?>
                    </p>
                <?php endif; ?>
            </section>

            <div class="review-message flash" id="review-message" role="alert" hidden></div>
        </div>

        <footer class="dialog-foot">
            <button type="button" class="btn btn-light" data-dialog-close>Cancel</button>
            <span class="spacer"></span>
            <button type="button" class="btn btn-red" data-decision="not_verified">Mark not verified</button>
            <button type="button" class="btn btn-green" data-decision="verified">Verify transaction</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
