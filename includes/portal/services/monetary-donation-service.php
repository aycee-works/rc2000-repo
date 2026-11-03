<?php
/**
 * includes/services/monetary-donation-service.php
 * All database logic for monetary donations (admin side).
 *
 * Money rules:
 *   reported_amount  = what the donor says they sent. NEVER changed here.
 *   verified_amount  = what the admin confirmed. NULL unless status = 'verified'.
 *   Only 'verified' records count toward the verified total.
 *
 * Decisions (status changes):
 *   A record may be verified or marked not verified at any time, including
 *   re-reviewing a record that was already decided. 'pending' is only the
 *   initial status set when the donor submits.
 *   - Verify:        needs a verified amount > 0 (max 2 decimals).
 *                    If it differs from the reported amount, a note is required.
 *   - Not verified:  needs a note explaining why. The verified amount is cleared
 *                    so the record is excluded from the verified total.
 */

const MONEY_PER_PAGE = 10;
const MONEY_MAX_AMOUNT = 9999999999.99;   // fits DECIMAL(12,2)

function money_filters_from_request(): array
{
    $status = get_str('status');
    if (!isset(MONEY_STATUSES[$status])) {
        $status = '';
    }
    return [
        'q'      => mb_substr(get_str('q'), 0, 100),
        'status' => $status,
        'page'   => max(1, (int) ($_GET['page'] ?? 1)),
    ];
}

/** Numbers for the three summary cards. */
function get_money_summary(PDO $pdo): array
{
    $row = $pdo->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(reported_amount), 0) AS reported,
                COALESCE(SUM(verified_amount) FILTER (WHERE status = 'verified'), 0) AS verified,
                COUNT(*) FILTER (WHERE status = 'pending') AS awaiting
         FROM monetary_donations"
    )->fetch();

    return [
        'total'    => (int) $row['total'],
        'reported' => (float) $row['reported'],
        'verified' => (float) $row['verified'],
        'awaiting' => (int) $row['awaiting'],
    ];
}

/** One page of donations matching the filters. Returns [rows, total, pages, page]. */
function list_money_donations(PDO $pdo, array $filters, int $perPage = MONEY_PER_PAGE): array
{
    $where = [];
    $params = [];

    if ($filters['q'] !== '') {
        $like = '%' . like_escape($filters['q']) . '%';
        $where[] = '(reference_no ILIKE ? OR donor_name ILIKE ? OR transaction_ref ILIKE ? OR payment_method ILIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['status'] !== '') {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count = $pdo->prepare("SELECT COUNT(*) FROM monetary_donations $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($filters['page'], $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT id, reference_no, donor_name, reported_amount, payment_method, status, created_at
         FROM monetary_donations $whereSql
         ORDER BY created_at DESC, id DESC
         LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
    );
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function get_money_donation(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, a.name AS verifier_name
         FROM monetary_donations m
         LEFT JOIN admins a ON a.id = m.verified_by
         WHERE m.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Clean an amount typed by the admin: allows "1,000", "₱1000.5", " 250 ".
 * Returns a normalized string like "1000.50", or null when it is not a valid
 * positive amount with at most 2 decimals.
 */
function parse_money_amount(string $raw): ?string
{
    $clean = str_replace(['₱', ',', ' '], '', trim($raw));
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $clean)) {
        return null;
    }
    $value = (float) $clean;
    if ($value <= 0 || $value > MONEY_MAX_AMOUNT) {
        return null;
    }
    return number_format($value, 2, '.', '');
}

/**
 * Save the admin's decision on one monetary donation.
 * $decision is 'verified' or 'not_verified'.
 * $version is the record's updated_at when the page loaded (edit-conflict check).
 *
 * Returns ['ok'=>bool, 'message'=>string, 'code'=>int, 'changed'=>bool, 'record'=>?array]
 */
function update_money_donation(PDO $pdo, int $id, int $adminId, string $decision, string $amountRaw, string $note, string $version): array
{
    $fail = fn(string $msg, int $code) => ['ok' => false, 'message' => $msg, 'code' => $code, 'changed' => false, 'record' => null];

    // ---- 1. Validate input ----
    if (!in_array($decision, ['verified', 'not_verified'], true)) {
        return $fail('Unknown action.', 422);
    }
    $note = trim($note);
    if (mb_strlen($note) > 255) {
        return $fail('The verification note must be 255 characters or fewer.', 422);
    }

    $amount = null;
    if ($decision === 'verified') {
        $amount = parse_money_amount($amountRaw);
        if ($amount === null) {
            return $fail('Enter the verified amount as a number greater than 0, with up to 2 decimals.', 422);
        }
    } elseif ($note === '') {
        return $fail('Please record the reason in the verification note before marking this as not verified.', 422);
    }

    // ---- 2. Check against the stored record inside a transaction ----
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM monetary_donations WHERE id = ? FOR UPDATE');
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

        $reported = (float) $row['reported_amount'];
        if ($decision === 'verified' && abs((float) $amount - $reported) >= 0.005 && $note === '') {
            $pdo->rollBack();
            return $fail('The verified amount (' . format_peso($amount) . ') differs from the reported amount ('
                . format_peso($reported) . '). Please explain in the verification note.', 422);
        }

        $oldAmount = $row['verified_amount'] === null ? null : (float) $row['verified_amount'];
        $same = $row['status'] === $decision
            && (($oldAmount === null && $amount === null) || ($oldAmount !== null && $amount !== null && abs($oldAmount - (float) $amount) < 0.005))
            && (string) $row['verification_note'] === $note;
        if ($same) {
            $pdo->rollBack();
            return ['ok' => true, 'message' => 'No changes to save.', 'code' => 200, 'changed' => false, 'record' => $row];
        }

        // ---- 3. Save (reported_amount, proof and transaction details are never touched) ----
        $up = $pdo->prepare(
            'UPDATE monetary_donations
             SET status = ?, verified_amount = ?, verification_note = ?, verified_by = ?, verified_at = NOW()
             WHERE id = ?'
        );
        $up->execute([$decision, $amount, $note === '' ? null : $note, $adminId, $id]);

        log_activity(
            $decision === 'verified' ? 'money_verified' : 'money_not_verified',
            'monetary_donation', $id,
            $row['reference_no'] . ' status: ' . $row['status'] . ' -> ' . $decision
            . '; verified_amount: ' . ($oldAmount ?? 'none') . ' -> ' . ($amount ?? 'none')
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $message = $decision === 'verified'
        ? 'Donation ' . $row['reference_no'] . ' verified: ' . format_peso($amount) . ' confirmed.'
        : 'Donation ' . $row['reference_no'] . ' marked as not verified.';

    return ['ok' => true, 'message' => $message, 'code' => 200, 'changed' => true, 'record' => get_money_donation($pdo, $id)];
}
