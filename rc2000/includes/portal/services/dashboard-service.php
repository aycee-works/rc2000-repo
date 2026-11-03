<?php
/**
 * includes/services/dashboard-service.php
 * Database queries for the dashboard. Used by admin/dashboard.php and
 * admin/api/dashboard.php so both always show the same numbers.
 *
 * Metric definitions (change here only):
 *   total donations     = all book records + all monetary records
 *   pending donations   = book 'pending' + monetary 'pending'
 *   pending inquiries   = inquiries 'open' + 'in_progress'
 *   monetary total      = SUM(verified_amount) of status 'verified'
 *                         (reported total = SUM(reported_amount) of all records)
 */

/** Raw numbers for the four overview cards. */
function get_dashboard_stats(PDO $pdo): array
{
    $book = $pdo->query(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending
         FROM book_donations"
    )->fetch();

    $money = $pdo->query(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COALESCE(SUM(reported_amount), 0) AS reported,
                COALESCE(SUM(verified_amount) FILTER (WHERE status = 'verified'), 0) AS verified
         FROM monetary_donations"
    )->fetch();

    $inq = $pdo->query(
        "SELECT COUNT(*) FILTER (WHERE status = 'open') AS open_count,
                COUNT(*) FILTER (WHERE status = 'in_progress') AS in_progress
         FROM inquiries"
    )->fetch();

    return [
        'book_total'      => (int) $book['total'],
        'book_pending'    => (int) $book['pending'],
        'money_total'     => (int) $money['total'],
        'money_pending'   => (int) $money['pending'],
        'money_reported'  => (float) $money['reported'],
        'money_verified'  => (float) $money['verified'],
        'inquiry_open'    => (int) $inq['open_count'],
        'inquiry_progress'=> (int) $inq['in_progress'],
    ];
}

/** Turn raw stats into the display strings shown on the cards. */
function dashboard_card_values(array $s): array
{
    return [
        'total_donations'      => (string) ($s['book_total'] + $s['money_total']),
        'total_donations_sub'  => $s['book_total'] . ' book · ' . $s['money_total'] . ' monetary',
        'pending_donations'    => (string) ($s['book_pending'] + $s['money_pending']),
        'pending_donations_sub'=> $s['book_pending'] . ' book · ' . $s['money_pending'] . ' monetary',
        'pending_inquiries'    => (string) ($s['inquiry_open'] + $s['inquiry_progress']),
        'pending_inquiries_sub'=> $s['inquiry_open'] . ' open · ' . $s['inquiry_progress'] . ' in progress',
        'monetary_total'       => format_peso($s['money_verified']),
        'monetary_sub'         => 'Verified · ' . format_peso($s['money_reported']) . ' reported',
    ];
}

/**
 * Newest pending donations (book and monetary together).
 * Each row: type ('book'|'monetary'), id, label, donor, reference_no, created_at.
 */
function get_pending_donations(PDO $pdo, int $limit = 5): array
{
    $sql = "
        SELECT * FROM (
            SELECT 'book' AS type, id, book_title::text AS label, donor_name::text AS donor,
                   reference_no::text AS reference_no, created_at
            FROM book_donations WHERE status = 'pending'
            UNION ALL
            SELECT 'monetary' AS type, id, reported_amount::text AS label, donor_name::text AS donor,
                   reference_no::text AS reference_no, created_at
            FROM monetary_donations WHERE status = 'pending'
        ) AS pd
        ORDER BY created_at DESC, id DESC
        LIMIT " . (int) $limit;

    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        if ($r['type'] === 'monetary') {
            $r['label'] = format_peso($r['label']) . ' (' . $r['reference_no'] . ')';
        }
        $r['review_url'] = url('admin/donations/index.php?tab=' . $r['type'] . '&id=' . (int) $r['id']);
    }
    return $rows;
}

/** Newest unresolved inquiries (open or in progress). */
function get_pending_inquiries(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->query(
        "SELECT id, reference_no, subject, category, status, created_at
         FROM inquiries
         WHERE status IN ('open', 'in_progress')
         ORDER BY created_at DESC, id DESC
         LIMIT " . (int) $limit
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['review_url'] = url('admin/inquiries/index.php?id=' . (int) $r['id']);
    }
    return $rows;
}
