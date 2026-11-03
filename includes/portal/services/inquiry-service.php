<?php
/**
 * includes/services/inquiry-service.php
 * All database logic for inquiries (admin side).
 *
 * Statuses: open, in_progress, resolved, closed.
 *
 * Three actions (all enforced here in PHP, never only in JavaScript):
 *   save     Save a response and/or resolution notes, and optionally move the
 *            inquiry between open <-> in progress, or REOPEN a resolved/closed one.
 *            Saving a response on an 'open' inquiry moves it to 'in_progress'.
 *   resolve  Allowed only if a non-empty response is SAVED in inquiry_responses.
 *            (A response typed in the box is saved first, in the same transaction,
 *            then the database is checked again before resolving.)
 *   close    Allowed from open / in progress / resolved. If no response was ever
 *            saved, a resolution note explaining why is required.
 *
 * Nothing is ever deleted: responses, notes and the original message are kept
 * through every status change.
 *
 * Email: saving a response does NOT send an email. Responses are stored with
 * email_status 'not_configured' until a mail sender is added; the admin is told so.
 */

const INQUIRY_PER_PAGE = 10;
const INQUIRY_RESPONSE_MAX = 5000;
const INQUIRY_NOTES_MAX = 2000;

/** Statuses the admin may pick in the dropdown (resolved/closed use their own buttons). */
function inquiry_manual_status_options(string $current): array
{
    $keys = [
        'open'        => ['open', 'in_progress'],
        'in_progress' => ['in_progress', 'open'],
        'resolved'    => ['resolved', 'in_progress'],   // in_progress = reopen
        'closed'      => ['closed', 'open'],            // open = reopen
    ][$current] ?? [$current];

    $options = [];
    foreach ($keys as $k) {
        $options[$k] = INQUIRY_STATUSES[$k] ?? $k;
    }
    return $options;
}

/** Read search/filter/page values from the query string. */
function inquiry_filters_from_request(): array
{
    $status = get_str('status');
    if (get_str('pending') === '1' && $status === '') {
        $status = 'pending';                    // dashboard link: unresolved inquiries
    }
    if ($status !== 'pending' && !isset(INQUIRY_STATUSES[$status])) {
        $status = '';
    }
    $category = get_str('category');
    if (!in_array($category, INQUIRY_CATEGORIES, true)) {
        $category = '';
    }
    return [
        'q'        => mb_substr(get_str('q'), 0, 100),
        'category' => $category,
        'status'   => $status,             // '', 'pending' (= open + in progress) or a real status
        'page'     => max(1, (int) ($_GET['page'] ?? 1)),
    ];
}

/** One page of inquiries. Returns [rows, total, pages, page]. */
function list_inquiries(PDO $pdo, array $filters, int $perPage = INQUIRY_PER_PAGE): array
{
    $where = [];
    $params = [];

    if ($filters['q'] !== '') {
        $like = '%' . like_escape($filters['q']) . '%';
        $where[] = '(reference_no ILIKE ? OR subject ILIKE ? OR name ILIKE ? OR email ILIKE ? OR category ILIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($filters['category'] !== '') {
        $where[] = 'category = ?';
        $params[] = $filters['category'];
    }
    if ($filters['status'] === 'pending') {
        $where[] = "status IN ('open', 'in_progress')";
    } elseif ($filters['status'] !== '') {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count = $pdo->prepare("SELECT COUNT(*) FROM inquiries $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($filters['page'], $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT id, reference_no, subject, name, category, status, created_at
         FROM inquiries $whereSql
         ORDER BY created_at DESC, id DESC
         LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
    );
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function get_inquiry(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Saved responses for one inquiry, oldest first. */
function get_inquiry_responses(PDO $pdo, int $inquiryId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.body, r.email_status, r.created_at, a.name AS admin_name
         FROM inquiry_responses r
         LEFT JOIN admins a ON a.id = r.admin_id
         WHERE r.inquiry_id = ?
         ORDER BY r.created_at ASC, r.id ASC'
    );
    $stmt->execute([$inquiryId]);
    return $stmt->fetchAll();
}

/** Plain-language email state for a saved response (never claims "sent" unless it was). */
function inquiry_email_label(string $emailStatus): string
{
    return [
        'not_configured' => 'Saved only. Email delivery is not configured, so this was not sent.',
        'pending'        => 'Waiting to be emailed.',
        'sent'           => 'Emailed to the submitter.',
        'failed'         => 'Email delivery failed. Saved only.',
    ][$emailStatus] ?? 'Saved only.';
}

/**
 * Apply one admin action. $input keys: status, notes, response, version.
 * Returns ['ok'=>bool, 'message'=>string, 'code'=>int, 'changed'=>bool, 'record'=>?array]
 */
function apply_inquiry_action(PDO $pdo, int $id, int $adminId, string $action, array $input): array
{
    $fail = fn(string $msg, int $code) => ['ok' => false, 'message' => $msg, 'code' => $code, 'changed' => false, 'record' => null];

    // ---- 1. Validate input ----
    if (!in_array($action, ['save', 'resolve', 'close'], true)) {
        return $fail('Unknown action.', 422);
    }
    $response = trim($input['response'] ?? '');
    $notes    = trim($input['notes'] ?? '');
    $version  = $input['version'] ?? '';
    $target   = $input['status'] ?? '';

    if (mb_strlen($response) > INQUIRY_RESPONSE_MAX) {
        return $fail('The response is too long (maximum ' . INQUIRY_RESPONSE_MAX . ' characters).', 422);
    }
    if (mb_strlen($notes) > INQUIRY_NOTES_MAX) {
        return $fail('The resolution notes are too long (maximum ' . INQUIRY_NOTES_MAX . ' characters).', 422);
    }
    if ($action === 'save' && !isset(INQUIRY_STATUSES[$target])) {
        return $fail('Please choose a valid status.', 422);
    }

    // ---- 2. Check rules against the stored record, inside a transaction ----
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        $abort = function (string $msg, int $code) use ($pdo, $fail) {
            $pdo->rollBack();
            return $fail($msg, $code);
        };

        if (!$row) {
            return $abort('That inquiry no longer exists.', 404);
        }
        if ($version !== '' && $version !== $row['updated_at']) {
            return $abort('This inquiry was changed by someone else. Please refresh the page and try again.', 409);
        }

        $old = $row['status'];
        $new = $old;

        if ($action === 'save') {
            if (!array_key_exists($target, inquiry_manual_status_options($old))) {
                return $abort('You cannot change a ' . strtolower(INQUIRY_STATUSES[$old]) . ' inquiry to '
                    . strtolower(INQUIRY_STATUSES[$target]) . ' here. Use the Mark resolved or Close inquiry buttons.', 422);
            }
            if ($old === 'closed' && $target === 'closed') {
                return $abort('This inquiry is closed. Change the status to Open and save to reopen it first.', 422);
            }
            $new = $target;
            if ($response !== '' && $new === 'open') {
                $new = 'in_progress';           // replying means work has started
            }
            if ($response === '' && $new === $old && $notes === (string) ($row['resolution_notes'] ?? '')) {
                return $abort('Nothing to save. Type a response, edit the resolution notes, or change the status.', 422);
            }
        } elseif ($action === 'resolve') {
            if ($old === 'closed') {
                return $abort('This inquiry is closed. Reopen it before marking it as resolved.', 422);
            }
            if ($old === 'resolved') {
                return $abort('This inquiry is already resolved.', 422);
            }
            $new = 'resolved';
        } else { // close
            if ($old === 'closed') {
                return $abort('This inquiry is already closed.', 422);
            }
            $new = 'closed';
        }

        // Save a typed response (with the inquiry id, so it always belongs to the right inquiry)
        $responseSaved = false;
        if ($response !== '') {
            $ins = $pdo->prepare(
                "INSERT INTO inquiry_responses (inquiry_id, admin_id, body, email_status)
                 VALUES (?, ?, ?, 'not_configured')"
            );
            $ins->execute([$id, $adminId, $response]);
            $responseSaved = true;
        }

        // Resolve / close rules: check the DATABASE, not the form
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM inquiry_responses WHERE inquiry_id = ? AND TRIM(body) <> ''");
        $cnt->execute([$id]);
        $savedResponses = (int) $cnt->fetchColumn();

        if ($action === 'resolve' && $savedResponses < 1) {
            return $abort('Write and save a response to the submitter before marking this inquiry as resolved.', 422);
        }
        if ($action === 'close' && $savedResponses < 1 && $notes === '') {
            return $abort('No response has been saved for this inquiry. Add a resolution note explaining why it is being closed.', 422);
        }

        // Resolved / closed timestamps (history is kept unless the inquiry is reopened)
        $now = date('Y-m-d H:i:s');
        $resolvedBy = $row['resolved_by']; $resolvedAt = $row['resolved_at'];
        $closedBy   = $row['closed_by'];   $closedAt   = $row['closed_at'];
        if ($new === 'resolved') {
            $resolvedBy = $adminId; $resolvedAt = $now;
            $closedBy = null; $closedAt = null;
        } elseif ($new === 'closed') {
            $closedBy = $adminId; $closedAt = $now;
        } elseif (in_array($old, ['resolved', 'closed'], true)) {
            // reopened
            $resolvedBy = $resolvedAt = $closedBy = $closedAt = null;
        }

        $up = $pdo->prepare(
            'UPDATE inquiries
             SET status = ?, resolution_notes = ?, resolved_by = ?, resolved_at = ?,
                 closed_by = ?, closed_at = ?
             WHERE id = ?'
        );
        $up->execute([$new, $notes === '' ? null : $notes, $resolvedBy, $resolvedAt, $closedBy, $closedAt, $id]);

        log_activity(
            'inquiry_' . $action, 'inquiry', $id,
            $row['reference_no'] . ' status: ' . $old . ' -> ' . $new
            . ($responseSaved ? '; response saved' : '')
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // ---- 3. Build an honest message ----
    $ref = $row['reference_no'];
    if ($action === 'resolve') {
        $message = "Inquiry $ref marked as resolved.";
    } elseif ($action === 'close') {
        $message = "Inquiry $ref closed.";
    } elseif ($responseSaved) {
        $message = "Response saved for $ref.";
    } else {
        $message = "Inquiry $ref updated.";
    }
    if ($responseSaved) {
        $message .= ' Email delivery is not configured, so the response was saved but NOT emailed to the submitter.';
    }

    return ['ok' => true, 'message' => $message, 'code' => 200, 'changed' => true, 'record' => get_inquiry($pdo, $id)];
}
