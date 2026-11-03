<?php
/**
 * POST api/public/inquiry.php   (JSON or form fields)
 * Fields: name, email, subject, category (Request|Technical|Report|Inquiry), message
 * Optional: Idempotency-Key header (or idempotency_key field).
 */
require_once dirname(__DIR__, 3) . '/includes/portal/public-api.php';
public_submit('inquiry', fn(PDO $pdo, array $in) => submit_inquiry($pdo, $in));
