<?php
/**
 * includes/public-api.php
 * Shared start-up for api/public/*.php (endpoints used by the PUBLIC website).
 * Unlike includes/bootstrap.php it starts NO session and needs NO admin login,
 * so nothing here can touch administrator authentication.
 */
require_once __DIR__ . '/../../config/portal/constants.php';
require_once __DIR__ . '/../../config/portal/app.php';
require_once __DIR__ . '/../../config/portal/database.php';
require_once __DIR__ . '/../../config/portal/public-api.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/services/public-submission-service.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

/** CORS: allow only origins listed in config; answer preflight (OPTIONS) requests. */
function public_cors(array $methods): void
{
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = public_config()['allowed_origins'];
    header('Vary: Origin');
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods) . ', OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Idempotency-Key');
        header('Access-Control-Max-Age: 600');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** JSON reply for public endpoints. $cache is a Cache-Control value. */
function public_json(bool $success, string $message, $data = null, int $status = 200, string $cache = 'no-store'): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: ' . $cache);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Only the listed HTTP methods are allowed; anything else gets 405. */
function public_require_method(string ...$methods): void
{
    $m = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($m, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        public_json(false, 'Method not allowed.', null, 405);
    }
}

/**
 * Read the submitted fields: JSON body or ordinary form / multipart fields.
 * Returns an array of strings (nested values are dropped).
 */
function public_input(): array
{
    $type = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_starts_with($type, 'application/json')) {
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if ($raw === false || strlen($raw) > 65536) {
            public_json(false, 'The request is too large.', null, 413);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            public_json(false, 'The request body is not valid JSON.', null, 400);
        }
    } else {
        $data = $_POST;
    }
    $out = [];
    foreach ($data as $k => $v) {
        if (is_string($k) && (is_string($v) || is_int($v) || is_float($v))) {
            $out[$k] = trim((string) $v);
        }
    }
    return $out;
}

/** Run a public endpoint's work, turning unexpected errors into a safe 500. */
function public_guard(callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        error_log('Public API error: ' . $e->getMessage());
        public_json(false, 'Something went wrong on the server. Please try again later.', null, 500);
    }
}

/**
 * Shared flow of the three submission endpoints:
 * method check -> honeypot -> rate limit -> validate/save -> reply.
 * $save receives (PDO, input array) and returns a submit_* result.
 */
function public_submit(string $bucket, callable $save): void
{
    public_cors(['POST']);
    public_require_method('POST');
    public_guard(function () use ($bucket, $save) {
        $in = public_input();

        // Honeypot: real visitors never fill the hidden "website" field. Bots get a fake success.
        if (($in['website'] ?? '') !== '') {
            public_json(true, 'Thank you. Your submission was received.', ['reference_no' => null], 201);
        }

        $pdo  = db();
        $wait = pub_rate_limit($pdo, $bucket);
        if ($wait > 0) {
            header('Retry-After: ' . $wait);
            public_json(false, 'Too many submissions. Please try again later.', ['retry_after_seconds' => $wait], 429);
        }

        $r = $save($pdo, $in);
        if (!$r['ok']) {
            public_json(false, $r['message'], ['errors' => $r['errors']], $r['code']);
        }
        public_json(true,
            $r['created'] ? 'Thank you. Your submission was received.' : 'This submission was already received.',
            $r['record'], $r['created'] ? 201 : 200);
    });
}
