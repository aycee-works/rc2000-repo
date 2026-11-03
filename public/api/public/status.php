<?php
/**
 * GET api/public/status.php
 * Connection check for the website team: is the API reachable, is the database
 * connected, and has database/integration.sql been run? Reveals no internals.
 */
require_once dirname(__DIR__, 3) . '/includes/portal/public-api.php';
public_cors(['GET']);
public_require_method('GET');

$out = ['api' => true, 'database' => false, 'integration_sql' => false];
try {
    $pdo = db();
    $out['database'] = true;
    $out['integration_sql'] = (bool) $pdo->query(
        "SELECT (SELECT COUNT(*) FROM pg_sequences WHERE sequencename IN ('book_donation_seq','monetary_donation_seq','inquiry_seq')) = 3
            AND to_regclass('rc2000_admin.public_rate_limits') IS NOT NULL"
    )->fetchColumn();
} catch (Throwable $e) {
    error_log('Public status check: ' . $e->getMessage());
}
$ok = $out['database'] && $out['integration_sql'];
public_json($ok, $ok ? 'Ready.' : 'Not ready.', $out, $ok ? 200 : 503);
