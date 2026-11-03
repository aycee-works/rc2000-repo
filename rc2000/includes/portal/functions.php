<?php
/**
 * includes/functions.php
 * Small helpers used across the admin pages and APIs.
 */

/** Escape text for safe output in HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a URL inside this project, e.g. url('admin/dashboard.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Build a URL to a file in /assets, e.g. asset('css/base.css'). */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Read a trimmed string from POST (or '' if missing). */
function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Read a trimmed string from GET (or '' if missing). */
function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Format a number as Philippine pesos, e.g. ₱3,000 or ₱1,250.50 */
function format_peso($amount): string
{
    $amount = (float) $amount;
    $decimals = (floor($amount) == $amount) ? 0 : 2;
    return '₱' . number_format($amount, $decimals);
}

/** Format a DB datetime for the admin UI, e.g. "Oct 9". */
function format_date_short(?string $datetime): string
{
    return $datetime ? date('M j', strtotime($datetime)) : '';
}

/** Format a DB datetime with year, e.g. "Oct 9, 2026". */
function format_date(?string $datetime): string
{
    return $datetime ? date('M j, Y', strtotime($datetime)) : '';
}

/** Format a DB datetime with time, e.g. "Oct 9, 2026 3:05 PM". */
function format_datetime(?string $datetime): string
{
    return $datetime ? date('M j, Y g:i A', strtotime($datetime)) : '';
}

/** Client IP address (REMOTE_ADDR only; headers like X-Forwarded-For can be faked). */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/** Status badge HTML. $type is 'book', 'money', 'inquiry' or 'content'. */
function status_badge(string $status, string $type): string
{
    $labels = [
        'book'    => BOOK_STATUSES,
        'money'   => MONEY_STATUSES,
        'inquiry' => INQUIRY_STATUSES,
        'content' => CONTENT_STATUSES,
    ][$type] ?? [];

    $label = $labels[$status] ?? $status;
    $class = 'badge badge-' . str_replace('_', '-', $status);
    return '<span class="' . e($class) . '">' . e($label) . '</span>';
}

/**
 * In development mode only, the real error reason to append to a generic message (so bugs are easy to find).
 * In production it returns '' so visitors never see internals. The full error is always in the PHP error log.
 */
function dev_detail(Throwable $e): string
{
    return (defined('APP_ENV') && APP_ENV === 'development')
        ? ' [Development details: ' . mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300) . ']'
        : '';
}

/** Send a JSON response and stop. Used by API endpoints. */
function json_response(bool $success, string $message, $data = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/**
 * Does activity_logs have the admin_name column (added by database/super-admin.sql)?
 * Cached per request so the audit log keeps working before and after that script is run.
 */
function activity_has_name_column(PDO $pdo): bool
{
    static $has = null;
    if ($has === null) {
        try {
            $has = (bool) $pdo->query(
                "SELECT 1 FROM information_schema.columns WHERE table_name = 'activity_logs' AND column_name = 'admin_name'"
            )->fetchColumn();
        } catch (Throwable $e) {
            $has = false;
        }
    }
    return $has;
}

/**
 * Write an entry to the admin audit log. Failures never break the request.
 * The actor defaults to the logged-in admin; pass $actorId/$actorName when there is no session
 * (for example an administrator setting a password from an emailed link).
 */
function log_activity(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $actorId = null, ?string $actorName = null): void
{
    $pdo = null;
    $inTx = false;
    try {
        $pdo = db();
        $inTx = $pdo->inTransaction();
        // PostgreSQL aborts the WHOLE transaction after any error, so inside a
        // transaction the log insert gets a savepoint it can roll back alone.
        if ($inTx) {
            $pdo->exec('SAVEPOINT log_activity');
        }
        $values = [
            $actorId ?? ($_SESSION['admin_id'] ?? null),
            $action,
            $entityType,
            $entityId,
            $details !== null ? mb_substr($details, 0, 500) : null,
            client_ip(),
        ];
        if (activity_has_name_column($pdo)) {
            $stmt = $pdo->prepare(
                'INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, admin_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $name = $actorName ?? ($_SESSION['admin_name'] ?? null);
            $values[] = $name !== null ? mb_substr((string) $name, 0, 100) : null;
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, details, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
        }
        $stmt->execute($values);
        if ($inTx) {
            $pdo->exec('RELEASE SAVEPOINT log_activity');
        }
    } catch (Throwable $e) {
        error_log('log_activity failed: ' . $e->getMessage());
        if ($pdo && $inTx) {
            try { $pdo->exec('ROLLBACK TO SAVEPOINT log_activity'); } catch (Throwable $ignored) {}
        }
    }
}

/**
 * Run an INSERT and return the new row's id (PostgreSQL: RETURNING id).
 * Pass the INSERT statement WITHOUT a RETURNING clause.
 */
function insert_returning_id(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql . ' RETURNING id');
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Escape % and _ so user text is matched literally in ILIKE (PostgreSQL's case-insensitive LIKE). */
function like_escape(string $text): string
{
    return addcslashes($text, '%_\\');
}

/**
 * One label/value row for a review popup's detail grid.
 * $htmlValue must ALREADY be escaped (use e()). Empty values show "Not provided".
 */
function detail_item(string $label, string $htmlValue, bool $wide = false): string
{
    if (trim(strip_tags($htmlValue)) === '') {
        $htmlValue = '<span class="muted">Not provided</span>';
    }
    return '<div class="detail' . ($wide ? ' detail-wide' : '') . '"><dt>' . e($label) . '</dt><dd>' . $htmlValue . '</dd></div>';
}
