<?php
/**
 * includes/services/book-donation-service.php
 * All database logic for book donations (admin side).
 *
 * Status rules (the only allowed changes):
 *   pending  -> approved | rejected
 *   approved -> rejected
 *   rejected -> pending            (reopen for reconsideration)
 * Saving without changing the status (e.g. only the received quantity) is allowed.
 *
 * Quantity rules:
 *   qty_offered  is what the donor declared. It is NEVER changed here.
 *   qty_received is set by the admin when books are physically received.
 *                Blank (NULL) = not confirmed yet. Must be 0..qty_offered.
 *   "Books confirmed received" counts qty_received of APPROVED donations only.
 */

const BOOK_PER_PAGE = 10;

function book_allowed_transitions(): array
{
    return [
        'pending'  => ['approved', 'rejected'],
        'approved' => ['rejected'],
        'rejected' => ['pending'],
    ];
}

/** Statuses to show in the "Update status" dropdown: current + allowed next ones. */
function book_status_options(string $current): array
{
    $keys = array_merge([$current], book_allowed_transitions()[$current] ?? []);
    $options = [];
    foreach ($keys as $k) {
        $options[$k] = BOOK_STATUSES[$k] ?? $k;
    }
    return $options;
}

/** Read and clean search/filter/page values from the query string. */
function book_filters_from_request(): array
{
    $status = get_str('status');
    if (!isset(BOOK_STATUSES[$status])) {
        $status = '';
    }
    return [
        'q'      => mb_substr(get_str('q'), 0, 100),
        'status' => $status,
        'page'   => max(1, (int) ($_GET['page'] ?? 1)),
    ];
}

/** Numbers for the three summary cards. */
function get_book_summary(PDO $pdo): array
{
    $row = $pdo->query(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'approved') AS approved,
                COALESCE(SUM(qty_received) FILTER (WHERE status = 'approved'), 0) AS books_received
         FROM book_donations"
    )->fetch();

    return [
        'total'          => (int) $row['total'],
        'approved'       => (int) $row['approved'],
        'books_received' => (int) $row['books_received'],
    ];
}

/** One page of donations matching the filters. Returns [rows, total, pages, page]. */
function list_book_donations(PDO $pdo, array $filters, int $perPage = BOOK_PER_PAGE): array
{
    $where = [];
    $params = [];

    if ($filters['q'] !== '') {
        $like = '%' . like_escape($filters['q']) . '%';
        $where[] = '(book_title ILIKE ? OR donor_name ILIKE ? OR reference_no ILIKE ? OR author ILIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['status'] !== '') {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count = $pdo->prepare("SELECT COUNT(*) FROM book_donations $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min($filters['page'], $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT id, reference_no, book_title, genre, donor_name, qty_offered, qty_received,
                book_condition, status, created_at
         FROM book_donations $whereSql
         ORDER BY created_at DESC, id DESC
         LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
    );
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/** Full record for the review panel, or null. */
function get_book_donation(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT b.*, a.name AS reviewer_name
         FROM book_donations b
         LEFT JOIN admins a ON a.id = b.reviewed_by
         WHERE b.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** How many other donations this email has made, and the year of the first one. */
function get_donor_history(PDO $pdo, string $email): array
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c, MIN(created_at) AS first_at FROM (
             SELECT created_at FROM book_donations WHERE donor_email = ?
             UNION ALL
             SELECT created_at FROM monetary_donations WHERE donor_email = ?
         ) AS t'
    );
    $stmt->execute([$email, $email]);
    $row = $stmt->fetch();
    return [
        'previous' => max(0, (int) $row['c'] - 1),
        'since'    => $row['first_at'] ? date('Y', strtotime($row['first_at'])) : null,
    ];
}

/**
 * Save the admin's review of one donation.
 * $version is the record's updated_at when the page was loaded; if someone
 * else saved in the meantime we refuse instead of overwriting their work.
 *
 * Returns ['ok'=>bool, 'message'=>string, 'code'=>int, 'changed'=>bool, 'record'=>?array]
 */
function update_book_donation(PDO $pdo, int $id, int $adminId, string $newStatus, string $qtyRaw, string $version): array
{
    $fail = fn(string $msg, int $code) => ['ok' => false, 'message' => $msg, 'code' => $code, 'changed' => false, 'record' => null];

    // ---- 1. Validate the input (no database needed yet) ----
    if (!isset(BOOK_STATUSES[$newStatus])) {
        return $fail('Please choose a valid status.', 422);
    }
    $qty = null;
    if ($qtyRaw !== '') {
        if (!ctype_digit($qtyRaw) || strlen($qtyRaw) > 5) {
            return $fail('Quantity received must be a whole number, 0 or more.', 422);
        }
        $qty = (int) $qtyRaw;
    }

    // ---- 2. Check against the stored record, inside a transaction ----
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM book_donations WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            return $fail('That donation no longer exists.', 404);
        }
        if ($version !== '' && $version !== $row['updated_at']) {
            $pdo->rollBack();
            return $fail('This donation was changed by someone else. Please refresh the page and try again.', 409);
        }
        if ($qty !== null && $qty > (int) $row['qty_offered']) {
            $pdo->rollBack();
            return $fail('Quantity received cannot be more than the quantity offered (' . (int) $row['qty_offered'] . ').', 422);
        }

        $oldStatus = $row['status'];
        if ($newStatus !== $oldStatus && !in_array($newStatus, book_allowed_transitions()[$oldStatus] ?? [], true)) {
            $pdo->rollBack();
            return $fail('A ' . strtolower(BOOK_STATUSES[$oldStatus]) . ' donation cannot be changed to '
                . strtolower(BOOK_STATUSES[$newStatus]) . '.', 422);
        }

        $oldQty = $row['qty_received'] === null ? null : (int) $row['qty_received'];
        if ($newStatus === $oldStatus && $qty === $oldQty) {
            $pdo->rollBack();
            return ['ok' => true, 'message' => 'No changes to save.', 'code' => 200, 'changed' => false, 'record' => $row];
        }

        // ---- 3. Save (qty_offered is deliberately not touched) ----
        $up = $pdo->prepare(
            'UPDATE book_donations
             SET status = ?, qty_received = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?'
        );
        $up->execute([$newStatus, $qty, $adminId, $id]);

        log_activity(
            'book_donation_updated', 'book_donation', $id,
            $row['reference_no'] . ' status: ' . $oldStatus . ' -> ' . $newStatus
            . '; qty_received: ' . ($oldQty ?? 'blank') . ' -> ' . ($qty ?? 'blank')
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $message = 'Donation ' . $row['reference_no'] . ' saved';
    if ($newStatus !== $oldStatus) {
        $message .= ': ' . BOOK_STATUSES[$oldStatus] . ' → ' . BOOK_STATUSES[$newStatus];
    }
    return ['ok' => true, 'message' => $message . '.', 'code' => 200, 'changed' => true, 'record' => get_book_donation($pdo, $id)];
}
