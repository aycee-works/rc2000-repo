<?php
/**
 * includes/partials/book-donations-tab.php
 * The "Book donations" tab. Included by admin/donations/index.php, which
 * provides: $summary, $list, $filters, $selected, $history and donations_url().
 */
$baseParams = ['tab' => 'book', 'q' => $filters['q'], 'status' => $filters['status'], 'page' => $filters['page'] > 1 ? $filters['page'] : ''];
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '';
?>
<section class="stat-grid stat-grid-3" aria-label="Book donation summary">
    <article class="stat-card">
        <div class="stat-label">Total donations</div>
        <div class="stat-value"><?= (int) $summary['total'] ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Books confirmed received</div>
        <div class="stat-value"><?= (int) $summary['books_received'] ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Approved</div>
        <div class="stat-value"><?= (int) $summary['approved'] ?></div>
    </article>
</section>

<form class="filter-bar" method="get" action="<?= e(url('admin/donations/index.php')) ?>">
    <input type="hidden" name="tab" value="book">
    <input class="input filter-search" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Search title, author, donor or reference" maxlength="100" aria-label="Search book donations">
    <select class="select filter-select" name="status" aria-label="Filter by status">
        <option value="">Status: All</option>
        <?php foreach (BOOK_STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-dark">Search</button>
    <?php if ($hasFilters): ?>
        <a class="filter-reset" href="<?= e(donations_url(['tab' => 'book'])) ?>">Clear</a>
    <?php endif; ?>
</form>

<section class="panel" aria-label="Book donations">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Book title</th><th>Genre</th><th>Donor</th><th>Qty</th>
                    <th>Condition</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$list['rows']): ?>
                <tr><td colspan="7" class="table-empty">
                    <?= $hasFilters ? 'No book donations match your search.' : 'No book donations have been submitted yet.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($list['rows'] as $row):
                $isSelected = $selected && (int) $selected['id'] === (int) $row['id'];
                $reviewUrl = donations_url($baseParams + ['id' => (int) $row['id']]);
            ?>
                <tr<?= $isSelected ? ' class="is-selected"' : '' ?>>
                    <td><?= e($row['book_title']) ?></td>
                    <td><?= e($row['genre'] ?? '') ?></td>
                    <td><?= e($row['donor_name']) ?></td>
                    <td><?= (int) $row['qty_offered'] ?></td>
                    <td><?= e($row['book_condition']) ?></td>
                    <td><?= status_badge($row['status'], 'book') ?></td>
                    <td><a href="<?= e($reviewUrl) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($list['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($list['page'] > 1): ?>
            <a href="<?= e(donations_url(array_merge($baseParams, ['page' => $list['page'] - 1]))) ?>">Previous</a>
        <?php endif; ?>
        <?php for ($p = max(1, $list['page'] - 2); $p <= min($list['pages'], $list['page'] + 2); $p++): ?>
            <?php if ($p === $list['page']): ?>
                <span class="is-current" aria-current="page"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= e(donations_url(array_merge($baseParams, ['page' => $p > 1 ? $p : '']))) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($list['page'] < $list['pages']): ?>
            <a href="<?= e(donations_url(array_merge($baseParams, ['page' => $list['page'] + 1]))) ?>">Next</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>

<?php if ($selected):
    $historyText = (int) $history['previous'] . ' previous donation' . ($history['previous'] === 1 ? '' : 's')
        . ($history['since'] ? ' · member since ' . $history['since'] : '');
?>
<dialog class="dialog dialog-review" id="review-dialog" aria-labelledby="review-title" data-autoopen>
    <form id="book-review-form" class="review-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
        <input type="hidden" name="version" value="<?= e($selected['updated_at']) ?>">

        <header class="dialog-head">
            <div>
                <p class="dialog-kicker">Book donation · <?= e($selected['reference_no']) ?></p>
                <h2 class="dialog-title" id="review-title"><?= e($selected['book_title']) ?></h2>
            </div>
            <div class="dialog-head-side">
                <?= status_badge($selected['status'], 'book') ?>
                <button type="button" class="dialog-x" data-dialog-close aria-label="Close">&times;</button>
            </div>
        </header>

        <div class="dialog-body">
            <section>
                <h3 class="review-heading">Donation details</h3>
                <dl class="detail-grid">
                    <?= detail_item('Book title', e($selected['book_title'])) ?>
                    <?= detail_item('Author', e($selected['author'] ?? '')) ?>
                    <?= detail_item('Genre', e($selected['genre'] ?? '')) ?>
                    <?= detail_item('Condition', e($selected['book_condition'])) ?>
                    <?= detail_item('Quantity offered', (string) (int) $selected['qty_offered']) ?>
                    <?= detail_item('Quantity received', $selected['qty_received'] === null
                        ? '<span class="muted">Not confirmed yet</span>' : (string) (int) $selected['qty_received']) ?>
                    <?= detail_item('Submitted', e(format_datetime($selected['created_at']))) ?>
                    <?= detail_item('Reference no.', e($selected['reference_no'])) ?>
                </dl>
            </section>

            <section>
                <h3 class="review-heading">Donor</h3>
                <dl class="detail-grid">
                    <?= detail_item('Name', e($selected['donor_name'])) ?>
                    <?= detail_item('Email', e($selected['donor_email'])) ?>
                    <?= detail_item('Contact number', e($selected['donor_contact'] ?? '')) ?>
                    <?= detail_item('Donation history', e($historyText)) ?>
                </dl>
            </section>

            <?php if (!empty($selected['notes'])): ?>
            <section>
                <h3 class="review-heading">Donor notes</h3>
                <p class="review-notes"><?= nl2br(e($selected['notes'])) ?></p>
            </section>
            <?php endif; ?>

            <section class="review-decision">
                <h3 class="review-heading">Review decision</h3>
                <div class="decision-grid">
                    <div class="field">
                        <label class="field-label-sm" for="review-status">Update status</label>
                        <select class="select" id="review-status" name="status" autofocus>
                            <?php foreach (book_status_options($selected['status']) as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $key === $selected['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label-sm" for="review-qty">Quantity received</label>
                        <input class="input" type="number" id="review-qty" name="qty_received" min="0"
                               max="<?= (int) $selected['qty_offered'] ?>" step="1"
                               value="<?= $selected['qty_received'] === null ? '' : (int) $selected['qty_received'] ?>"
                               placeholder="Offered: <?= (int) $selected['qty_offered'] ?>">
                    </div>
                </div>
                <p class="review-hint">Offered: <?= (int) $selected['qty_offered'] ?>. Leave the quantity blank until the books are physically received. The offered quantity is never changed.</p>
                <?php if ($selected['reviewed_at']): ?>
                    <p class="review-meta">Last reviewed <?= e(format_datetime($selected['reviewed_at'])) ?><?= $selected['reviewer_name'] ? ' by ' . e($selected['reviewer_name']) : '' ?></p>
                <?php endif; ?>
            </section>

            <div class="review-message flash" id="review-message" role="alert" hidden></div>
        </div>

        <footer class="dialog-foot">
            <span class="spacer"></span>
            <button type="button" class="btn btn-light" data-dialog-close>Cancel</button>
            <button type="submit" class="btn btn-dark">Save status</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
