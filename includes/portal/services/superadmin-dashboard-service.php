<?php
/**
 * includes/services/superadmin-dashboard-service.php
 * Numbers and lists for the Super Admin dashboard.
 * "Admin accounts" always means accounts with role = 'admin' (the super admin is not listed).
 */
require_once __DIR__ . '/audit-service.php';

function superadmin_account_counts(PDO $pdo): array
{
    $row = $pdo->query(
        "SELECT COUNT(*) FILTER (WHERE is_active)     AS active,
                COUNT(*) FILTER (WHERE NOT is_active) AS deactivated,
                COUNT(*)                              AS total
         FROM admins WHERE role = 'admin'"
    )->fetch();
    return ['active' => (int) $row['active'], 'deactivated' => (int) $row['deactivated'], 'total' => (int) $row['total']];
}

/** Admin actions today / this week and the week's split by module. */
function superadmin_action_counts(PDO $pdo): array
{
    $row = $pdo->query(
        "SELECT COUNT(*) FILTER (WHERE created_at >= date_trunc('day', now())) AS today,
                COUNT(*)                                                       AS week
         FROM (" . audit_classified_sql() . ") t
         WHERE module_key IN ('donations', 'inquiries', 'content')
           AND created_at >= date_trunc('week', now())"
    )->fetch();

    $byModule = array_fill_keys(AUDIT_WORK_MODULES, 0);
    $rows = $pdo->query(
        "SELECT module_key, COUNT(*) AS n
         FROM (" . audit_classified_sql() . ") t
         WHERE module_key IN ('donations', 'inquiries', 'content')
           AND created_at >= date_trunc('week', now())
         GROUP BY module_key"
    )->fetchAll();
    foreach ($rows as $r) {
        $byModule[$r['module_key']] = (int) $r['n'];
    }
    return ['today' => (int) $row['today'], 'week' => (int) $row['week'], 'by_module' => $byModule];
}

/** A few admin accounts for the side list: active first, most recently seen first. */
function superadmin_account_preview(PDO $pdo, int $limit): array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, email, is_active, last_login_at
         FROM admins WHERE role = 'admin'
         ORDER BY is_active DESC, last_login_at DESC NULLS LAST, id ASC
         LIMIT " . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}
