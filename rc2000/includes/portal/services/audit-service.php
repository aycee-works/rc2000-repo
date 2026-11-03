<?php
/**
 * includes/services/audit-service.php
 * Reads the activity_logs table for the Super Admin pages (dashboard + audit logs).
 *
 * Old and new log rows are classified IN SQL (one place, so filters and labels always agree):
 *   activity_key: created, edited, deleted, approved, rejected, verified, not_verified, published,
 *                 archived, resolved, closed, deactivated, signed_in, signed_out
 *   module_key:   donations, inquiries, content, accounts, access, other
 * "Admin actions" = work on donations, inquiries and content (not logins, not account management).
 */

const AUDIT_WORK_MODULES = ['donations', 'inquiries', 'content'];

const AUDIT_ACTIVITY_LABELS = [
    'created' => 'Created', 'edited' => 'Edited', 'deleted' => 'Deleted',
    'approved' => 'Approved', 'rejected' => 'Rejected',
    'verified' => 'Verified', 'not_verified' => 'Not verified',
    'published' => 'Published', 'archived' => 'Archived',
    'resolved' => 'Resolved', 'closed' => 'Closed',
    'deactivated' => 'Deactivated', 'reactivated' => 'Reactivated', 'signed_in' => 'Signed in', 'signed_out' => 'Signed out',
];

const AUDIT_MODULE_LABELS = [
    'donations' => 'Donations', 'inquiries' => 'Inquiries', 'content' => 'Content',
    'accounts' => 'Accounts', 'access' => 'Access', 'other' => 'Other',
];

const AUDIT_ENTITY_NOUNS = [
    'book_donation' => 'book donation', 'monetary_donation' => 'monetary donation', 'inquiry' => 'inquiry',
    'gallery' => 'gallery image', 'story' => 'story', 'announcement' => 'announcement',
    'trivia' => 'trivia entry', 'admin' => 'admin account',
];

/** SQL that adds activity_key and module_key to each log row. Used as a sub-query. */
function audit_classified_sql(): string
{
    return <<<'SQL'
SELECT l.id, l.admin_id, l.action, l.entity_type, l.entity_id, l.details, l.created_at,
       COALESCE(l.admin_name, a.name, 'Removed account') AS admin_display,
       CASE
         WHEN l.action = 'login'  THEN 'signed_in'
         WHEN l.action = 'logout' THEN 'signed_out'
         WHEN l.action ~ '_created$' THEN 'created'
         WHEN l.action IN ('content_deleted', 'admin_removed') THEN 'deleted'
         WHEN l.action = 'content_status' THEN
              CASE substring(l.details from '-> ([a-z_]+)$')
                   WHEN 'published' THEN 'published' WHEN 'archived' THEN 'archived' ELSE 'edited' END
         WHEN l.action = 'book_donation_updated' THEN
              CASE substring(l.details from 'status: [a-z_]+ -> ([a-z_]+)')
                   WHEN 'approved' THEN 'approved' WHEN 'rejected' THEN 'rejected' ELSE 'edited' END
         WHEN l.action = 'money_verified'     THEN 'verified'
         WHEN l.action = 'money_not_verified' THEN 'not_verified'
         WHEN l.action = 'inquiry_resolve'    THEN 'resolved'
         WHEN l.action = 'inquiry_close'      THEN 'closed'
         WHEN l.action = 'admin_deactivated'  THEN 'deactivated'
         WHEN l.action = 'admin_reactivated'  THEN 'reactivated'
         ELSE 'edited'
       END AS activity_key,
       CASE
         WHEN l.action IN ('login', 'logout') THEN 'access'
         WHEN l.entity_type IN ('book_donation', 'monetary_donation') THEN 'donations'
         WHEN l.entity_type = 'inquiry' THEN 'inquiries'
         WHEN l.entity_type IN ('gallery', 'story', 'announcement', 'trivia') THEN 'content'
         WHEN l.entity_type = 'admin' OR l.action ~ '^admin_' THEN 'accounts'
         ELSE 'other'
       END AS module_key
FROM activity_logs l
LEFT JOIN admins a ON a.id = l.admin_id
SQL;
}

/** One-line description of a classified row, e.g. "Approved book donation BD-0001". */
function audit_describe(array $row): string
{
    $key = $row['activity_key'];
    if ($key === 'signed_in')  { return 'Logged in'; }
    if ($key === 'signed_out') { return 'Logged out'; }

    $verbs = [
        'created' => 'Created', 'edited' => 'Updated', 'deleted' => 'Deleted', 'approved' => 'Approved',
        'rejected' => 'Rejected', 'verified' => 'Verified', 'not_verified' => 'Marked not verified',
        'published' => 'Published', 'archived' => 'Archived', 'resolved' => 'Resolved',
        'closed' => 'Closed', 'deactivated' => 'Deactivated', 'reactivated' => 'Reactivated',
    ];
    $noun = AUDIT_ENTITY_NOUNS[$row['entity_type'] ?? ''] ?? 'record';
    $text = ($verbs[$key] ?? 'Updated') . ' ' . $noun;

    $details = (string) ($row['details'] ?? '');
    if (in_array($row['module_key'], ['donations', 'inquiries'], true)) {
        if (preg_match('/^([A-Z]{2,3}-\d+)/', $details, $m)) {
            $text .= ' ' . $m[1];
        }
    } elseif ($row['module_key'] === 'accounts') {
        if ($details !== '') {
            $text .= ': ' . $details;
        }
    } elseif ($details !== '' && !str_contains($details, '->')) {
        $text .= ': ' . $details;          // content title
    }
    return mb_substr($text, 0, 160);
}

/** Badge HTML for an activity key. */
function audit_activity_badge(string $key): string
{
    return '<span class="badge badge-act badge-act-' . e(str_replace('_', '-', $key)) . '">'
        . e(AUDIT_ACTIVITY_LABELS[$key] ?? ucfirst($key)) . '</span>';
}

/** Date and time columns the way the design shows them: "Oct 9, 2026" / "11:11 PM". */
function audit_date(string $ts): string { return date('M j, Y', strtotime($ts)); }
function audit_time(string $ts): string { return date('g:i A', strtotime($ts)); }

/** Recent log rows (newest first). $workOnly hides logins and account-management entries. */
function audit_recent(PDO $pdo, int $limit, bool $workOnly = true): array
{
    $where = $workOnly ? "WHERE module_key IN ('donations', 'inquiries', 'content')" : "WHERE module_key <> 'access'";
    $stmt = $pdo->prepare('SELECT * FROM (' . audit_classified_sql() . ') t ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

const AUDIT_PER_PAGE = 15;
const AUDIT_RANGES = [
    'today' => 'Today',
    '7d'    => 'Last 7 days',
    '30d'   => 'Last 30 days',
    'month' => 'This month',
];

/** Clean the filter values from the query string. Anything unknown is ignored. */
function audit_clean_filters(array $in): array
{
    $admin = (string) ($in['admin'] ?? '');
    return [
        'q'        => mb_substr(trim((string) ($in['q'] ?? '')), 0, 100),
        'admin'    => ($admin === 'removed' || ctype_digit($admin)) ? $admin : '',
        'module'   => isset(AUDIT_MODULE_LABELS[$in['module'] ?? '']) ? $in['module'] : '',
        'activity' => isset(AUDIT_ACTIVITY_LABELS[$in['activity'] ?? '']) ? $in['activity'] : '',
        'range'    => isset(AUDIT_RANGES[$in['range'] ?? '']) ? $in['range'] : '',
        'page'     => max(1, (int) ($in['page'] ?? 1)),
    ];
}

/**
 * Filtered, paged audit log. Logins/logouts are hidden unless the Access module (or a
 * sign-in activity) is chosen, so the default view is administrative work only.
 */
function audit_search(PDO $pdo, array $f): array
{
    $where = [];
    $params = [];
    if ($f['module'] !== '') {
        $where[] = 'module_key = ?'; $params[] = $f['module'];
    } elseif (!in_array($f['activity'], ['signed_in', 'signed_out'], true)) {
        $where[] = "module_key <> 'access'";
    }
    if ($f['activity'] !== '') { $where[] = 'activity_key = ?'; $params[] = $f['activity']; }
    if ($f['admin'] === 'removed') {
        $where[] = 'admin_id IS NULL';
    } elseif ($f['admin'] !== '') {
        $where[] = 'admin_id = ?'; $params[] = (int) $f['admin'];
    }
    if ($f['q'] !== '') {
        $where[] = '(details ILIKE ? OR admin_display ILIKE ? OR action ILIKE ?)';
        $like = '%' . like_escape($f['q']) . '%';
        array_push($params, $like, $like, $like);
    }
    $since = [
        'today' => "date_trunc('day', now())",
        '7d'    => "date_trunc('day', now()) - INTERVAL '6 days'",
        '30d'   => "date_trunc('day', now()) - INTERVAL '29 days'",
        'month' => "date_trunc('month', now())",
    ][$f['range']] ?? null;
    if ($since) { $where[] = "created_at >= $since"; }

    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $base = '(' . audit_classified_sql() . ') t';

    $c = $pdo->prepare("SELECT COUNT(*) FROM $base $w");
    $c->execute($params);
    $total = (int) $c->fetchColumn();
    $pages = max(1, (int) ceil($total / AUDIT_PER_PAGE));
    $page  = min($f['page'], $pages);

    $stmt = $pdo->prepare("SELECT * FROM $base $w ORDER BY created_at DESC, id DESC LIMIT " . AUDIT_PER_PAGE . ' OFFSET ' . (($page - 1) * AUDIT_PER_PAGE));
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/** Administrators who appear in the log, for the filter dropdown: [value => name]. */
function audit_admin_options(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT admin_id, MAX(admin_display) AS name FROM (' . audit_classified_sql() . ') t GROUP BY admin_id ORDER BY MAX(admin_display)'
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        if ($r['admin_id'] === null) {
            $out['removed'] = 'Removed accounts';
        } else {
            $out[(string) $r['admin_id']] = $r['name'];
        }
    }
    return $out;
}
