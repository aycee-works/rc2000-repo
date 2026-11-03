<?php
/**
 * includes/services/public-submission-service.php
 * Saves donations and inquiries sent by the public website.
 *
 * Every submit_* function returns:
 *   ['ok'=>true,  'created'=>bool, 'record'=>[reference_no,status,submitted_at]]
 *   ['ok'=>false, 'code'=>int, 'message'=>string, 'errors'=>[field=>message]]
 * Records are always created as pending/open, and reference numbers come from
 * database sequences, so the website can never choose a status or a number.
 */

require_once __DIR__ . '/upload-service.php';

const PUBLIC_PROOF_MAX_BYTES = 5 * 1024 * 1024;

function pub_fail(array $errors, int $code = 422, string $message = 'Please correct the highlighted fields.'): array
{
    return ['ok' => false, 'code' => $code, 'message' => $message, 'errors' => $errors];
}

function pub_len(string $s): int { return mb_strlen($s, 'UTF-8'); }

/** Strip control characters (keeps newlines/tabs) from free text. */
function pub_clean(string $s): string
{
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
}

/** Validate the donor/sender fields shared by all forms. Adds to $errors, returns cleaned values. */
function pub_person(array $in, array &$errors, string $nameKey, string $emailKey, ?string $contactKey): array
{
    $name  = pub_clean($in[$nameKey] ?? '');
    $email = trim($in[$emailKey] ?? '');
    if (pub_len($name) < 2 || pub_len($name) > 120) {
        $errors[$nameKey] = 'Name must be 2 to 120 characters.';
    }
    if ($email === '' || pub_len($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[$emailKey] = 'Enter a valid email address.';
    }
    $contact = null;
    if ($contactKey !== null) {
        $raw = pub_clean($in[$contactKey] ?? '');
        if ($raw !== '') {
            if (!preg_match('/^[0-9+() \-]{7,30}$/', $raw)) {
                $errors[$contactKey] = 'Contact number may only contain digits, spaces, + ( ) and -.';
            } else {
                $contact = $raw;
            }
        }
    }
    return [$name, $email, $contact];
}

/** Idempotency key from the Idempotency-Key header or the idempotency_key field. */
function pub_idempotency_key(array $in, array &$errors): ?string
{
    $key = trim($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($in['idempotency_key'] ?? ''));
    if ($key === '') {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
        $errors['idempotency_key'] = 'Idempotency key must be 8 to 64 letters, digits, - or _.';
        return null;
    }
    return $key;
}

/**
 * Rate limit by IP. Returns 0 when allowed (and records the attempt),
 * otherwise the number of seconds to wait. Never blocks if the limit table is missing.
 */
function pub_rate_limit(PDO $pdo, string $bucket): int
{
    [$max, $window] = public_config()['rate_limits'][$bucket] ?? [10, 3600];
    $ip = client_ip();
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*), EXTRACT(EPOCH FROM (MIN(created_at) + (? * INTERVAL '1 second') - now()))
             FROM public_rate_limits WHERE bucket = ? AND ip_address = ? AND created_at > now() - (? * INTERVAL '1 second')"
        );
        $stmt->execute([(int) $window, $bucket, $ip, (int) $window]);
        [$count, $wait] = $stmt->fetch(PDO::FETCH_NUM);
        if ((int) $count >= (int) $max) {
            return max(1, (int) ceil((float) $wait));
        }
        $pdo->prepare('INSERT INTO public_rate_limits (bucket, ip_address) VALUES (?, ?)')->execute([$bucket, $ip]);
        if (random_int(1, 50) === 1) { // occasional cleanup
            $pdo->exec("DELETE FROM public_rate_limits WHERE created_at < now() - INTERVAL '2 days'");
        }
    } catch (Throwable $e) {
        error_log('Rate limit check failed: ' . $e->getMessage());
    }
    return 0;
}

function pub_find_by_key(PDO $pdo, string $table, string $key): ?array
{
    $allowed = ['book_donations', 'monetary_donations', 'inquiries'];
    if (!in_array($table, $allowed, true)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT reference_no, status, created_at FROM $table WHERE idempotency_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? pub_record($row) : null;
}

function pub_record(array $row): array
{
    return [
        'reference_no' => $row['reference_no'],
        'status'       => $row['status'],
        'submitted_at' => date('c', strtotime($row['created_at'])),
    ];
}

/** Run an INSERT ... RETURNING row; on a duplicate idempotency key return the original record. */
function pub_insert(PDO $pdo, string $table, string $sql, array $params, ?string $key): array
{
    try {
        $stmt = $pdo->prepare($sql . ' RETURNING reference_no, status, created_at');
        $stmt->execute($params);
        return ['ok' => true, 'created' => true, 'record' => pub_record($stmt->fetch())];
    } catch (PDOException $e) {
        if ($key !== null && $e->getCode() === '23505' && ($existing = pub_find_by_key($pdo, $table, $key))) {
            return ['ok' => true, 'created' => false, 'record' => $existing];
        }
        throw $e;
    }
}

function submit_book_donation(PDO $pdo, array $in): array
{
    $errors = [];
    [$name, $email, $contact] = pub_person($in, $errors, 'donor_name', 'donor_email', 'donor_contact');
    $key    = pub_idempotency_key($in, $errors);
    $title  = pub_clean($in['book_title'] ?? '');
    $author = pub_clean($in['author'] ?? '');
    $genre  = pub_clean($in['genre'] ?? '');
    $cond   = trim($in['book_condition'] ?? 'Good');
    $notes  = pub_clean($in['notes'] ?? '');
    $qtyRaw = trim($in['qty_offered'] ?? '1');

    if ($title === '' || pub_len($title) > 200)  { $errors['book_title'] = 'Book title is required (up to 200 characters).'; }
    if (pub_len($author) > 150)                  { $errors['author'] = 'Author must be 150 characters or fewer.'; }
    if (pub_len($genre) > 60)                    { $errors['genre'] = 'Genre must be 60 characters or fewer.'; }
    if (!in_array($cond, BOOK_CONDITIONS, true)) { $errors['book_condition'] = 'Condition must be one of: ' . implode(', ', BOOK_CONDITIONS) . '.'; }
    if (!ctype_digit($qtyRaw) || (int) $qtyRaw < 1 || (int) $qtyRaw > 1000) { $errors['qty_offered'] = 'Quantity must be a whole number from 1 to 1000.'; }
    if (pub_len($notes) > 1000)                  { $errors['notes'] = 'Notes must be 1000 characters or fewer.'; }
    if ($errors) { return pub_fail($errors); }

    if ($key !== null && ($existing = pub_find_by_key($pdo, 'book_donations', $key))) {
        return ['ok' => true, 'created' => false, 'record' => $existing];
    }
    return pub_insert($pdo, 'book_donations',
        "INSERT INTO book_donations (reference_no, idempotency_key, donor_name, donor_email, donor_contact, book_title, author, genre, book_condition, qty_offered, notes)
         VALUES ('BD-' || lpad(nextval('book_donation_seq')::text, 4, '0'), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$key, $name, $email, $contact, $title, $author ?: null, $genre ?: null, $cond, (int) $qtyRaw, $notes ?: null], $key);
}

/**
 * Check an uploaded proof of payment (JPG, PNG, WebP or PDF, up to 5 MB) by its REAL content.
 * Returns ['ok'=>true,'ext'=>..] or ['ok'=>false,'message'=>..].
 */
function pub_validate_proof($file): array
{
    if (!is_array($file) || is_array($file['error'] ?? null)) {
        return ['ok' => false, 'message' => 'Invalid file upload.'];
    }
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'message' => 'The proof file is too large (maximum 5 MB).'];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'message' => 'The proof file could not be uploaded. Please try again.'];
    }
    $size = (int) $file['size'];
    if ($size < 1 || $size > PUBLIC_PROOF_MAX_BYTES) {
        return ['ok' => false, 'message' => 'The proof file must be between 1 byte and 5 MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime === 'application/pdf') {
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
        return $head === '%PDF-' ? ['ok' => true, 'ext' => 'pdf'] : ['ok' => false, 'message' => 'The file is not a valid PDF.'];
    }
    $check = validate_image_file($file['tmp_name'], $size);
    return $check['ok'] ? ['ok' => true, 'ext' => $check['ext']] : ['ok' => false, 'message' => $check['message']];
}

function submit_monetary_donation(PDO $pdo, array $in, $proofFile): array
{
    $errors = [];
    [$name, $email, $contact] = pub_person($in, $errors, 'donor_name', 'donor_email', 'donor_contact');
    $key    = pub_idempotency_key($in, $errors);
    $amount = str_replace(',', '', trim($in['reported_amount'] ?? ''));
    $method = pub_clean($in['payment_method'] ?? '');
    $ref    = pub_clean($in['transaction_ref'] ?? '');

    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
        $errors['reported_amount'] = 'Enter an amount greater than 0 (up to 2 decimal places).';
    }
    if ($method === '' || pub_len($method) > 40) { $errors['payment_method'] = 'Payment method is required (up to 40 characters).'; }
    if (pub_len($ref) > 100)                     { $errors['transaction_ref'] = 'Transaction reference must be 100 characters or fewer.'; }

    $hasProof = is_array($proofFile) && (int) ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $proof = null;
    if ($hasProof) {
        $proof = pub_validate_proof($proofFile);
        if (!$proof['ok']) { $errors['proof'] = $proof['message']; }
    }
    if ($errors) { return pub_fail($errors); }

    if ($key !== null && ($existing = pub_find_by_key($pdo, 'monetary_donations', $key))) {
        return ['ok' => true, 'created' => false, 'record' => $existing];
    }

    // Store the proof privately (uploads/proofs is blocked from the web; only the admin viewer serves it)
    $path = null;
    if ($proof) {
        $dir = ROOT_PATH . '/uploads/proofs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return pub_fail([], 500, 'The proof could not be saved. Please try again later.');
        }
        $name2 = bin2hex(random_bytes(16)) . '.' . $proof['ext'];
        if (!move_uploaded_file($proofFile['tmp_name'], $dir . '/' . $name2)) {
            return pub_fail([], 500, 'The proof could not be saved. Please try again later.');
        }
        @chmod($dir . '/' . $name2, 0640);
        $path = 'uploads/proofs/' . $name2;
    }

    try {
        $result = pub_insert($pdo, 'monetary_donations',
            "INSERT INTO monetary_donations (reference_no, idempotency_key, donor_name, donor_email, donor_contact, reported_amount, payment_method, transaction_ref, proof_path)
             VALUES ('MD-' || lpad(nextval('monetary_donation_seq')::text, 4, '0'), ?, ?, ?, ?, ?, ?, ?, ?)",
            [$key, $name, $email, $contact, $amount, $method, $ref ?: null, $path], $key);
    } catch (Throwable $e) {
        if ($path) { @unlink(ROOT_PATH . '/' . $path); }
        throw $e;
    }
    // A duplicate retry already has its own stored proof; drop the extra copy
    if ($path && !$result['created']) { @unlink(ROOT_PATH . '/' . $path); }
    return $result;
}

function submit_inquiry(PDO $pdo, array $in): array
{
    $errors = [];
    [$name, $email] = pub_person($in, $errors, 'name', 'email', null);
    $key     = pub_idempotency_key($in, $errors);
    $subject = pub_clean($in['subject'] ?? '');
    $cat     = trim($in['category'] ?? 'Inquiry');
    $message = pub_clean($in['message'] ?? '');

    if ($subject === '' || pub_len($subject) > 200)   { $errors['subject'] = 'Subject is required (up to 200 characters).'; }
    if (!in_array($cat, INQUIRY_CATEGORIES, true))    { $errors['category'] = 'Category must be one of: ' . implode(', ', INQUIRY_CATEGORIES) . '.'; }
    if (pub_len($message) < 10 || pub_len($message) > 5000) { $errors['message'] = 'Message must be 10 to 5000 characters.'; }
    if ($errors) { return pub_fail($errors); }

    if ($key !== null && ($existing = pub_find_by_key($pdo, 'inquiries', $key))) {
        return ['ok' => true, 'created' => false, 'record' => $existing];
    }
    return pub_insert($pdo, 'inquiries',
        "INSERT INTO inquiries (reference_no, idempotency_key, name, email, subject, category, message)
         VALUES ('INQ-' || lpad(nextval('inquiry_seq')::text, 4, '0'), ?, ?, ?, ?, ?, ?)",
        [$key, $name, $email, $subject, $cat, $message], $key);
}
