<?php
/**
 * includes/services/content-service.php
 * Database logic for content shown on the public website:
 *   gallery images, stories + articles (one table, `type` column), announcements, trivia.
 *
 * Visibility: only status 'published' is public. 'draft' and 'archived' are hidden.
 * Trivia only has draft / published.
 * Files are removed only AFTER the database change has committed.
 *
 * Every save function returns:
 *   ['ok'=>bool, 'message'=>string, 'code'=>int, 'id'=>?int]
 */

require_once __DIR__ . '/upload-service.php';

const CONTENT_LIST_LIMIT = 50;
const CONTENT_BODY_MAX   = 100000;

/** Which table / statuses / file column each content kind uses. Table names come ONLY from here. */
function content_entities(): array
{
    return [
        'gallery'      => ['table' => 'gallery_images', 'label' => 'image',        'statuses' => ['draft', 'published', 'archived'], 'file_col' => 'file_path',  'dated' => false],
        'story'        => ['table' => 'stories',        'label' => 'story',        'statuses' => ['draft', 'published', 'archived'], 'file_col' => 'cover_path', 'dated' => true],
        'announcement' => ['table' => 'announcements',  'label' => 'announcement', 'statuses' => ['draft', 'published', 'archived'], 'file_col' => 'image_path', 'dated' => true],
        'trivia'       => ['table' => 'trivia',         'label' => 'trivia entry', 'statuses' => ['draft', 'published'],             'file_col' => null,         'dated' => false],
    ];
}

function content_fail(string $msg, int $code = 422): array
{
    return ['ok' => false, 'message' => $msg, 'code' => $code, 'id' => null];
}

/** Parse a "YYYY-MM-DD" date. Returns '' when blank, null when invalid, else the normalized date. */
function content_parse_date(string $raw): ?string
{
    if ($raw === '') {
        return '';
    }
    $d = DateTime::createFromFormat('!Y-m-d', $raw);
    return ($d && $d->format('Y-m-d') === $raw) ? $raw : null;
}

/** Choose the published_at value to store. */
function content_published_at(string $status, string $date, ?string $existing): ?string
{
    $value = $date !== '' ? $date . ' 00:00:00' : $existing;
    if ($status === 'published' && $value === null) {
        $value = date('Y-m-d H:i:s');
    }
    return $value;
}

// ------------------------------------------------------------
// Lists and single records (admin view: everything)
// ------------------------------------------------------------

function content_count(PDO $pdo, string $entity): int
{
    $t = content_entities()[$entity]['table'];
    return (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
}

function list_gallery(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, title, caption, file_path, status, updated_at FROM gallery_images
         ORDER BY sort_order ASC, id DESC LIMIT ' . (int) CONTENT_LIST_LIMIT
    )->fetchAll();
}

function list_stories(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, type, title, status, published_at, created_at FROM stories
         ORDER BY created_at DESC, id DESC LIMIT ' . (int) CONTENT_LIST_LIMIT
    )->fetchAll();
}

function list_announcements(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, title, status, published_at, created_at FROM announcements
         ORDER BY created_at DESC, id DESC LIMIT ' . (int) CONTENT_LIST_LIMIT
    )->fetchAll();
}

function list_trivia(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, question, status FROM trivia ORDER BY id DESC LIMIT ' . (int) CONTENT_LIST_LIMIT
    )->fetchAll();
}

/** One record of any kind, or null. */
function get_content(PDO $pdo, string $entity, int $id): ?array
{
    $def = content_entities()[$entity] ?? null;
    if (!$def) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM ' . $def['table'] . ' WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// ------------------------------------------------------------
// Publish / archive / delete (shared by all kinds)
// ------------------------------------------------------------

function set_content_status(PDO $pdo, int $adminId, string $entity, int $id, string $status): array
{
    $def = content_entities()[$entity] ?? null;
    if (!$def) {
        return content_fail('Unknown content type.');
    }
    if (!in_array($status, $def['statuses'], true)) {
        return content_fail('That status is not available for this content type.');
    }
    $row = get_content($pdo, $entity, $id);
    if (!$row) {
        return content_fail('That ' . $def['label'] . ' no longer exists.', 404);
    }
    if ($row['status'] === $status) {
        return ['ok' => true, 'message' => 'Already ' . $status . '.', 'code' => 200, 'id' => $id];
    }

    $sql = 'UPDATE ' . $def['table'] . ' SET status = ?';
    if ($def['dated'] && $status === 'published') {
        $sql .= ', published_at = COALESCE(published_at, NOW())';
    }
    $pdo->prepare($sql . ' WHERE id = ?')->execute([$status, $id]);
    log_activity('content_status', $entity, $id, $row['status'] . ' -> ' . $status);

    $verb = ['published' => 'published', 'archived' => 'archived', 'draft' => 'moved back to draft'][$status];
    return ['ok' => true, 'message' => ucfirst($def['label']) . ' ' . $verb . '.', 'code' => 200, 'id' => $id];
}

function delete_content(PDO $pdo, string $entity, int $id): array
{
    $def = content_entities()[$entity] ?? null;
    if (!$def) {
        return content_fail('Unknown content type.');
    }
    $row = get_content($pdo, $entity, $id);
    if (!$row) {
        return content_fail('That ' . $def['label'] . ' no longer exists.', 404);
    }

    $pdo->prepare('DELETE FROM ' . $def['table'] . ' WHERE id = ?')->execute([$id]);
    log_activity('content_deleted', $entity, $id, mb_substr((string) ($row['title'] ?? $row['question'] ?? ''), 0, 120));

    if ($def['file_col']) {
        delete_uploaded_file($row[$def['file_col']] ?? null);   // only after the row is gone
    }
    return ['ok' => true, 'message' => ucfirst($def['label']) . ' deleted.', 'code' => 200, 'id' => $id];
}

// ------------------------------------------------------------
// Gallery images
// ------------------------------------------------------------

/**
 * Create ($id = 0) or update a gallery image.
 * $in: title, caption, status, version.  $file: $_FILES['image'] (required when creating).
 */
function save_gallery(PDO $pdo, int $adminId, int $id, array $in, $file): array
{
    $wasNew = $id === 0;   // for the audit log: Created vs Edited
    $title   = trim($in['title'] ?? '');
    $caption = trim($in['caption'] ?? '');
    $status  = $in['status'] ?? '';

    if ($title === '' || mb_strlen($title) > 150) {
        return content_fail('Please enter a title (up to 150 characters).');
    }
    if (mb_strlen($caption) > 255) {
        return content_fail('The caption must be 255 characters or fewer.');
    }
    if (!in_array($status, content_entities()['gallery']['statuses'], true)) {
        return content_fail('Please choose a valid status.');
    }
    $hasFile = upload_provided($file);
    if ($id === 0 && !$hasFile) {
        return content_fail('Please choose an image to upload.');
    }

    $new = null;
    if ($hasFile) {
        $new = save_uploaded_image($file, 'gallery');
        if (!$new['ok']) {
            return content_fail($new['message']);
        }
    }

    $oldPath = null;
    $pdo->beginTransaction();
    try {
        if ($id === 0) {
            $id = insert_returning_id(
                $pdo,
                'INSERT INTO gallery_images (title, caption, file_path, original_name, mime_type, file_size, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$title, $caption ?: null, $new['path'], $new['original_name'], $new['mime'], $new['size'], $status, $adminId]
            );
            $message = 'Image added to the gallery.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM gallery_images WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                $pdo->rollBack();
                if ($new) { delete_uploaded_file($new['path']); }
                return content_fail('That image no longer exists.', 404);
            }
            $version = $in['version'] ?? '';
            if ($version !== '' && $version !== $row['updated_at']) {
                $pdo->rollBack();
                if ($new) { delete_uploaded_file($new['path']); }
                return content_fail('This image was changed by someone else. Please refresh the page and try again.', 409);
            }
            if ($new) {
                $oldPath = $row['file_path'];
                $pdo->prepare(
                    'UPDATE gallery_images SET title=?, caption=?, status=?, file_path=?, original_name=?, mime_type=?, file_size=? WHERE id=?'
                )->execute([$title, $caption ?: null, $status, $new['path'], $new['original_name'], $new['mime'], $new['size'], $id]);
            } else {
                $pdo->prepare('UPDATE gallery_images SET title=?, caption=?, status=? WHERE id=?')
                    ->execute([$title, $caption ?: null, $status, $id]);
            }
            $message = 'Image saved.';
        }
        log_activity($wasNew ? 'gallery_created' : 'gallery_updated', 'gallery', $id, $title);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($new) { delete_uploaded_file($new['path']); }
        throw $e;
    }

    delete_uploaded_file($oldPath);    // replaced image removed only after commit
    return ['ok' => true, 'message' => $message, 'code' => 200, 'id' => $id];
}

// ------------------------------------------------------------
// Stories / articles and announcements (same shape, one function)
// ------------------------------------------------------------

/**
 * Create ($id = 0) or update a story/article ($entity 'story') or announcement.
 * $in: title, body, status, published_date (YYYY-MM-DD or blank), version,
 *      and for stories: type ('story'|'article'), author.
 * $file: $_FILES['image'] (optional).  $removeImage: true to delete the current image.
 */
function save_post(PDO $pdo, int $adminId, string $entity, int $id, array $in, $file, bool $removeImage): array
{
    $wasNew = $id === 0;   // for the audit log: Created vs Edited
    if (!in_array($entity, ['story', 'announcement'], true)) {
        return content_fail('Unknown content type.');
    }
    $def = content_entities()[$entity];
    $isStory = $entity === 'story';

    $title  = trim($in['title'] ?? '');
    $body   = trim($in['body'] ?? '');
    $status = $in['status'] ?? '';
    $author = $isStory ? trim($in['author'] ?? '') : '';
    $type   = $isStory ? ($in['type'] ?? 'story') : null;
    $date   = content_parse_date(trim($in['published_date'] ?? ''));

    if ($title === '' || mb_strlen($title) > 200) {
        return content_fail('Please enter a title (up to 200 characters).');
    }
    if ($body === '' || mb_strlen($body) > CONTENT_BODY_MAX) {
        return content_fail('Please write the content (up to ' . number_format(CONTENT_BODY_MAX) . ' characters).');
    }
    if (mb_strlen($author) > 120) {
        return content_fail('The author name must be 120 characters or fewer.');
    }
    if ($isStory && !in_array($type, ['story', 'article'], true)) {
        return content_fail('Please choose whether this is a story or an article.');
    }
    if (!in_array($status, $def['statuses'], true)) {
        return content_fail('Please choose a valid status.');
    }
    if ($date === null) {
        return content_fail('The publish date is not valid.');
    }

    $folder = $isStory ? 'stories' : 'announcements';
    $col = $def['file_col'];
    $table = $def['table'];

    $new = null;
    if (upload_provided($file)) {
        $new = save_uploaded_image($file, $folder);
        if (!$new['ok']) {
            return content_fail($new['message']);
        }
    }

    $oldPath = null;
    $pdo->beginTransaction();
    try {
        $existing = null;
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                $pdo->rollBack();
                if ($new) { delete_uploaded_file($new['path']); }
                return content_fail('That ' . $def['label'] . ' no longer exists.', 404);
            }
            $version = $in['version'] ?? '';
            if ($version !== '' && $version !== $existing['updated_at']) {
                $pdo->rollBack();
                if ($new) { delete_uploaded_file($new['path']); }
                return content_fail('This ' . $def['label'] . ' was changed by someone else. Please refresh the page and try again.', 409);
            }
        }

        // Which image path to store
        $imagePath = $existing[$col] ?? null;
        if ($new) {
            $oldPath = $imagePath;
            $imagePath = $new['path'];
        } elseif ($removeImage && $imagePath) {
            $oldPath = $imagePath;
            $imagePath = null;
        }

        $publishedAt = content_published_at($status, $date, $existing['published_at'] ?? null);

        if ($isStory) {
            if ($id === 0) {
                $id = insert_returning_id(
                    $pdo,
                    'INSERT INTO stories (type, title, body, author, cover_path, status, published_at, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$type, $title, $body, $author ?: null, $imagePath, $status, $publishedAt, $adminId]
                );
            } else {
                $pdo->prepare(
                    'UPDATE stories SET type=?, title=?, body=?, author=?, cover_path=?, status=?, published_at=? WHERE id=?'
                )->execute([$type, $title, $body, $author ?: null, $imagePath, $status, $publishedAt, $id]);
            }
        } else {
            if ($id === 0) {
                $id = insert_returning_id(
                    $pdo,
                    'INSERT INTO announcements (title, body, image_path, status, published_at, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$title, $body, $imagePath, $status, $publishedAt, $adminId]
                );
            } else {
                $pdo->prepare(
                    'UPDATE announcements SET title=?, body=?, image_path=?, status=?, published_at=? WHERE id=?'
                )->execute([$title, $body, $imagePath, $status, $publishedAt, $id]);
            }
        }
        log_activity($entity . ($wasNew ? '_created' : '_updated'), $entity, $id, $title);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($new) { delete_uploaded_file($new['path']); }
        throw $e;
    }

    delete_uploaded_file($oldPath);
    $noun = $isStory ? ($type === 'article' ? 'Article' : 'Story') : 'Announcement';
    return ['ok' => true, 'message' => $noun . ' saved' . ($status === 'published' ? ' and published.' : ' as ' . $status . '.'), 'code' => 200, 'id' => $id];
}

// ------------------------------------------------------------
// Trivia
// ------------------------------------------------------------

/** Create ($id = 0) or update a trivia entry. $in: question, answer, explanation, status, version. */
function save_trivia(PDO $pdo, int $adminId, int $id, array $in): array
{
    $wasNew = $id === 0;   // for the audit log: Created vs Edited
    $question    = trim($in['question'] ?? '');
    $answer      = trim($in['answer'] ?? '');
    $explanation = trim($in['explanation'] ?? '');
    $status      = $in['status'] ?? '';

    if ($question === '' || mb_strlen($question) > 255) {
        return content_fail('Please enter the question (up to 255 characters).');
    }
    if ($answer === '' || mb_strlen($answer) > 255) {
        return content_fail('Please enter the answer (up to 255 characters).');
    }
    if (mb_strlen($explanation) > 2000) {
        return content_fail('The explanation must be 2000 characters or fewer.');
    }
    if (!in_array($status, content_entities()['trivia']['statuses'], true)) {
        return content_fail('Please choose a valid status.');
    }

    $pdo->beginTransaction();
    try {
        if ($id === 0) {
            $id = insert_returning_id(
                $pdo,
                'INSERT INTO trivia (question, answer, explanation, status, created_by) VALUES (?, ?, ?, ?, ?)',
                [$question, $answer, $explanation ?: null, $status, $adminId]
            );
        } else {
            $stmt = $pdo->prepare('SELECT updated_at FROM trivia WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                $pdo->rollBack();
                return content_fail('That trivia entry no longer exists.', 404);
            }
            $version = $in['version'] ?? '';
            if ($version !== '' && $version !== $row['updated_at']) {
                $pdo->rollBack();
                return content_fail('This trivia entry was changed by someone else. Please refresh the page and try again.', 409);
            }
            $pdo->prepare('UPDATE trivia SET question=?, answer=?, explanation=?, status=? WHERE id=?')
                ->execute([$question, $answer, $explanation ?: null, $status, $id]);
        }
        log_activity($wasNew ? 'trivia_created' : 'trivia_updated', 'trivia', $id, mb_substr($question, 0, 120));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    return ['ok' => true, 'message' => 'Trivia saved' . ($status === 'published' ? ' and published.' : ' as draft.'), 'code' => 200, 'id' => $id];
}
