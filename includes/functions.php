<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/auth.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function media_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    // Uploaded files are stored under public/uploads, while the user-facing
    // URLs intentionally stay under /rc2000/uploads/...
    if (str_starts_with($path, '/uploads/')) {
        return app_url('/public' . $path);
    }
    return app_url($path);
}

function media_file_exists(?string $path): bool
{
    $path = trim((string) $path);
    if ($path === '') {
        return false;
    }
    if (preg_match('#^https?://#i', $path)) {
        return true;
    }
    $relative = ltrim($path, '/');
    $file = str_starts_with($relative, 'uploads/')
        ? dirname(__DIR__) . '/public/' . $relative
        : dirname(__DIR__) . '/' . $relative;
    return is_file($file);
}

function app_url(string $path = ''): string
{
    $base = getenv('RC2000_BASE_PATH') ?: '/rc2000';
    $base = '/' . trim($base, '/');
    if ($base === '/') {
        $base = '';
    }
    return $base . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function excerpt(string $body, int $length = 140): string
{
    $plain = trim(preg_replace('/\s+/', ' ', $body) ?? $body);
    if (mb_strlen($plain) <= $length) {
        return $plain;
    }
    return mb_substr($plain, 0, $length - 1) . '…';
}

function current_nav(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

function nav_active(string $file): string
{
    return current_nav() === $file ? ' class="is-active"' : '';
}

function db_ok(): bool
{
    try {
        db()->query('SELECT 1');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function fetch_all(string $sql, array $params = []): array
{
    try {
        $stmt = db()->prepare($sql);
        foreach ($params as $key => $value) {
            $placeholder = is_int($key) ? $key + 1 : $key;
            if (is_int($value)) {
                $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($placeholder, $value);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $error) {
        return [];
    }
}

function fetch_one(string $sql, array $params = []): ?array
{
    $rows = fetch_all($sql, $params);
    return $rows[0] ?? null;
}

function published_content(string $type, int $limit = 6): array
{
    if (in_array($type, ['story', 'featured_story', 'article'], true)) {
    $portalType = $type === 'article' ? 'article' : 'story';

$items = fetch_all(
    'SELECT id AS content_id,
            type AS content_type,
            title,
            body,
            cover_path AS cover_image_path,
            author AS author_name,
            published_at
     FROM rc2000_admin.stories
     WHERE type = :type
       AND status = :status
     ORDER BY published_at DESC NULLS LAST,
              created_at DESC,
              id DESC
     LIMIT :limit',
    [
        'type' => $portalType,
        'status' => 'published',
        'limit' => $limit
    ]
);

return $items;
    }

    return fetch_all(
        'SELECT content_id, title, body, cover_image_path, author_name, published_at
         FROM content
         WHERE content_type = :type AND status = :status
         ORDER BY published_at DESC NULLS LAST, created_at DESC
         LIMIT :limit',
        ['type' => $type, 'status' => 'published', 'limit' => $limit]
    );
}

function search_published_content(string $query, int $limit = 20): array
{
    $term = '%' . mb_strtolower(trim($query)) . '%';
    $adminStories = fetch_all(
        'SELECT id AS content_id, type AS content_type, title, body,
                cover_path AS cover_image_path, author AS author_name, published_at
         FROM rc2000_admin.stories
         WHERE status = :status AND (LOWER(title) LIKE :q1 OR LOWER(body) LIKE :q2)
         ORDER BY published_at DESC NULLS LAST, created_at DESC
         LIMIT :limit',
        ['status' => 'published', 'q1' => $term, 'q2' => $term, 'limit' => $limit]
    );
    $adminAnnouncements = fetch_all(
        "SELECT id AS content_id, 'announcement' AS content_type, title, body,
                image_path AS cover_image_path, NULL::varchar AS author_name, published_at
         FROM rc2000_admin.announcements
         WHERE status = :status AND (LOWER(title) LIKE :q1 OR LOWER(body) LIKE :q2)
         ORDER BY published_at DESC NULLS LAST, created_at DESC
         LIMIT :limit",
        ['status' => 'published', 'q1' => $term, 'q2' => $term, 'limit' => $limit]
    );
    if ($adminStories || $adminAnnouncements) {
        $items = array_merge($adminStories, $adminAnnouncements);
        usort($items, static function (array $a, array $b): int {
            return strtotime((string) ($b['published_at'] ?? '')) <=> strtotime((string) ($a['published_at'] ?? ''));
        });
        return array_slice($items, 0, $limit);
    }

    return fetch_all(
        'SELECT content_id, content_type, title, body, cover_image_path, author_name, published_at
         FROM content
         WHERE status = :status
           AND (LOWER(title) LIKE :q1 OR LOWER(body) LIKE :q2)
         ORDER BY published_at DESC NULLS LAST, created_at DESC
         LIMIT :limit',
        ['status' => 'published', 'q1' => $term, 'q2' => $term, 'limit' => $limit]
    );
}

function active_gallery(int $limit = 12): array
{
    // The admin portal owns the current gallery records. Keep the legacy
    // public table as a fallback for sites that have not installed the portal.
    $images = fetch_all(
        'SELECT id AS image_id, file_path AS image_path, caption, title AS alt_text
         FROM rc2000_admin.gallery_images
         WHERE status = :status
         ORDER BY sort_order, id
         LIMIT :limit',
        ['status' => 'published', 'limit' => $limit]
    );
    if ($images) {
        return $images;
    }

    return fetch_all(
        'SELECT image_id, image_path, caption, alt_text
         FROM public.gallery_images
         WHERE is_active = TRUE
         ORDER BY display_order, image_id
         LIMIT :limit',
        ['limit' => $limit]
    );
}

function published_trivia(int $limit = 8): array
{
    $items = fetch_all(
        "SELECT id AS trivia_id, question AS title,
                concat_ws(E'\\n', 'Answer: ' || answer, NULLIF(explanation, '')) AS body,
                NULL::varchar AS source, created_at AS published_at
         FROM rc2000_admin.trivia
         WHERE status = :status
         ORDER BY created_at DESC, id DESC
         LIMIT :limit",
        ['status' => 'published', 'limit' => $limit]
    );
    if ($items) {
        return $items;
    }

    return fetch_all(
        'SELECT trivia_id, title, body, image_path, source, published_at
         FROM trivia
         WHERE status = :status
         ORDER BY published_at DESC NULLS LAST, created_at DESC
         LIMIT :limit',
        ['status' => 'published', 'limit' => $limit]
    );
}

function active_donation_methods(): array
{
    return fetch_all(
        'SELECT donation_method_id, method_name, payment_provider, qr_image_path, instructions
         FROM donation_methods
         WHERE is_active = TRUE
         ORDER BY donation_method_id'
    );
}

function ticket_number(int $inquiryId, ?string $createdAt = null): string
{
    $year = $createdAt ? date('Y', strtotime($createdAt)) : date('Y');
    return sprintf('RCT-%s-%05d', $year, $inquiryId);
}

function parse_ticket_number(string $value): ?int
{
    $value = strtoupper(trim($value));
    if (preg_match('/^RCT-\d{4}-(\d+)$/', $value, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/^\d+$/', $value)) {
        return (int) $value;
    }
    return null;
}

function inquiry_status_label(string $status): string
{
    return [
        'pending' => 'Received — waiting to be reviewed',
        'in_progress' => 'In progress',
        'resolved' => 'Resolved',
    ][$status] ?? $status;
}

function page_start(string $title, array $options = []): void
{
    $GLOBALS['pageTitle'] = $title;
    $GLOBALS['pageClass'] = (string) ($options['class'] ?? '');
    require __DIR__ . '/header.php';
}

function page_end(): void
{
    require __DIR__ . '/footer.php';
}

function render_alerts(array $errors = [], ?string $success = null): void
{
    $success = $success ?: flash_get('success');
    if ($success) {
        echo '<div class="alert alert-success" role="status">' . e($success) . '</div>';
    }
    if ($errors) {
        echo '<div class="alert alert-error" role="alert"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . e($error) . '</li>';
        }
        echo '</ul></div>';
    }
}
