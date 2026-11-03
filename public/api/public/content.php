<?php
/**
 * GET api/public/content.php?type=gallery|stories|announcements|trivia
 * Optional: id=<number> (one item), limit=1..50 (default 12), page=1.., kind=story|article (stories only)
 * Returns PUBLISHED items only. Drafts and archived items are never exposed.
 * Text fields are plain text: the website must escape them (textContent, not innerHTML).
 * Image fields are paths relative to the admin project root; prefix with the admin site's base URL.
 */
require_once dirname(__DIR__, 3) . '/includes/portal/public-api.php';
public_cors(['GET']);
public_require_method('GET');

public_guard(function () {
    $type  = $_GET['type'] ?? '';
    $id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 12)));
    $page  = max(1, (int) ($_GET['page'] ?? 1));

    $defs = [
        'gallery'       => ['gallery_images', 'id, title, caption, file_path AS image, created_at AS date', 'sort_order ASC, created_at DESC, id DESC'],
        'stories'       => ['stories',        'id, type AS kind, title, body, author, cover_path AS image, published_at AS date', 'published_at DESC NULLS LAST, id DESC'],
        'announcements' => ['announcements',  'id, title, body, image_path AS image, published_at AS date', 'published_at DESC NULLS LAST, id DESC'],
        'trivia'        => ['trivia',         'id, question, answer, explanation, created_at AS date', 'id DESC'],
    ];
    if (!isset($defs[$type])) {
        public_json(false, 'Unknown type. Use gallery, stories, announcements or trivia.', null, 400);
    }
    [$table, $cols, $order] = $defs[$type];

    $where  = ["status = 'published'"];
    $params = [];
    if ($id > 0) { $where[] = 'id = ?'; $params[] = $id; }
    if ($type === 'stories' && in_array($_GET['kind'] ?? '', ['story', 'article'], true)) {
        $where[] = 'type = ?'; $params[] = $_GET['kind'];
    }
    $w = implode(' AND ', $where);
    $pdo = db();

    $count = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $w");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare("SELECT $cols FROM $table WHERE $w ORDER BY $order LIMIT $limit OFFSET " . (($page - 1) * $limit));
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    foreach ($items as &$it) {
        $it['id'] = (int) $it['id'];
        if (isset($it['date'])) { $it['date'] = date('c', strtotime($it['date'])); }
    }
    unset($it);

    if ($id > 0) {
        if (!$items) { public_json(false, 'Not found.', null, 404); }
        public_json(true, 'OK', $items[0], 200, 'public, max-age=60');
    }
    public_json(true, 'OK', ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit, 'pages' => max(1, (int) ceil($total / $limit))], 200, 'public, max-age=60');
});
