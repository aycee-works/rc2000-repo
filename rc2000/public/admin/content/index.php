<?php
// admin/content/index.php - Manage content: Gallery, Stories and articles, Announcements, Trivia
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/content-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$gallery = $stories = $announcements = $trivia = [];
$counts = ['gallery' => 0, 'story' => 0, 'announcement' => 0, 'trivia' => 0];
$loadError = null;

try {
    $pdo = db();
    $gallery       = list_gallery($pdo);
    $stories       = list_stories($pdo);
    $announcements = list_announcements($pdo);
    $trivia        = list_trivia($pdo);
    foreach ($counts as $entity => $_) {
        $counts[$entity] = content_count($pdo, $entity);
    }
} catch (Throwable $e) {
    error_log('Content load failed: ' . $e->getMessage());
    $loadError = 'The content could not be loaded. Please check the database connection and try again.';
}

function form_url(string $entity, int $id = 0, string $kind = ''): string
{
    $p = ['entity' => $entity];
    if ($id > 0) { $p['id'] = $id; }
    if ($kind !== '') { $p['kind'] = $kind; }
    return url('admin/content/form.php?' . http_build_query($p));
}

/** "Archive" for published items, "Publish" for everything else. */
function toggle_button(string $entity, array $row): string
{
    $publish = $row['status'] !== 'published';
    return '<button type="button" class="link-button" data-set-status data-entity="' . e($entity)
         . '" data-id="' . (int) $row['id'] . '" data-status="' . ($publish ? 'published' : 'archived') . '">'
         . ($publish ? 'Publish' : 'Archive') . '</button>';
}

function limit_note(int $shown, int $total): string
{
    return $total > $shown ? '<p class="panel-note">Showing the newest ' . $shown . ' of ' . $total . '.</p>' : '';
}

$pageTitle = 'Manage content';
$activeNav = 'content';
$extraCss  = ['content.css'];
$extraJs   = ['content.js'];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Manage content</h1>
    <p class="page-subtitle">Edit what appears on the public website. Only published items are visible to visitors.</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<div class="content-grid">

    <!-- Gallery -->
    <section class="panel" aria-labelledby="gal-title">
        <div class="panel-header"><h2 class="panel-title" id="gal-title">Gallery</h2></div>
        <div class="panel-body">
            <div class="gallery-grid">
                <?php foreach ($gallery as $g): ?>
                    <button type="button" class="gallery-tile" data-gallery-edit
                            data-id="<?= (int) $g['id'] ?>" data-title="<?= e($g['title']) ?>"
                            data-caption="<?= e($g['caption'] ?? '') ?>" data-status="<?= e($g['status']) ?>"
                            data-version="<?= e($g['updated_at']) ?>" data-src="<?= e(url($g['file_path'])) ?>"
                            title="<?= e($g['title']) ?>">
                        <img src="<?= e(url($g['file_path'])) ?>" alt="<?= e($g['title']) ?>" loading="lazy">
                        <?php if ($g['status'] !== 'published'): ?>
                            <span class="tile-badge"><?= e(CONTENT_STATUSES[$g['status']]) ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="gallery-tile gallery-add" data-gallery-new aria-label="Add a gallery image">+</button>
            </div>
            <?= limit_note(count($gallery), $counts['gallery']) ?>
        </div>
    </section>

    <!-- Stories and articles -->
    <section class="panel" aria-labelledby="story-title">
        <div class="panel-header">
            <h2 class="panel-title" id="story-title">Stories and articles</h2>
            <span class="panel-buttons">
                <a class="btn btn-maroon btn-sm" href="<?= e(form_url('story')) ?>">New story</a>
                <a class="btn btn-light btn-sm" href="<?= e(form_url('story', 0, 'article')) ?>">New article</a>
            </span>
        </div>
        <ul class="item-list">
            <?php if (!$stories): ?><li class="item-empty">No stories or articles yet.</li><?php endif; ?>
            <?php foreach ($stories as $s): ?>
                <li class="item-row">
                    <div class="item-main">
                        <span class="item-title" title="<?= e($s['title']) ?>"><?= e($s['title']) ?></span>
                        <span class="item-sub"><?= $s['type'] === 'article' ? 'Article' : 'Story' ?> · <?= e(format_date($s['published_at'] ?: $s['created_at'])) ?></span>
                    </div>
                    <?= status_badge($s['status'], 'content') ?>
                    <span class="item-actions"><a href="<?= e(form_url('story', (int) $s['id'])) ?>">Edit</a> · <?= toggle_button('story', $s) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= limit_note(count($stories), $counts['story']) ?>
    </section>

    <!-- Announcements -->
    <section class="panel" aria-labelledby="ann-title">
        <div class="panel-header">
            <h2 class="panel-title" id="ann-title">Announcements</h2>
            <a class="btn btn-maroon btn-sm" href="<?= e(form_url('announcement')) ?>">New announcement</a>
        </div>
        <ul class="item-list">
            <?php if (!$announcements): ?><li class="item-empty">No announcements yet.</li><?php endif; ?>
            <?php foreach ($announcements as $a): ?>
                <li class="item-row">
                    <div class="item-main">
                        <span class="item-title" title="<?= e($a['title']) ?>"><?= e($a['title']) ?></span>
                        <span class="item-sub"><?= e(format_date($a['published_at'] ?: $a['created_at'])) ?></span>
                    </div>
                    <?= status_badge($a['status'], 'content') ?>
                    <span class="item-actions"><a href="<?= e(form_url('announcement', (int) $a['id'])) ?>">Edit</a> · <?= toggle_button('announcement', $a) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= limit_note(count($announcements), $counts['announcement']) ?>
    </section>

    <!-- Trivia -->
    <section class="panel" aria-labelledby="triv-title">
        <div class="panel-header">
            <h2 class="panel-title" id="triv-title">Trivia</h2>
            <a class="btn btn-maroon btn-sm" href="<?= e(form_url('trivia')) ?>">Add trivia</a>
        </div>
        <ul class="item-list">
            <?php if (!$trivia): ?><li class="item-empty">No trivia yet.</li><?php endif; ?>
            <?php foreach ($trivia as $t): ?>
                <li class="item-row">
                    <div class="item-main"><span class="item-title" title="<?= e($t['question']) ?>"><?= e($t['question']) ?></span></div>
                    <?= status_badge($t['status'], 'content') ?>
                    <span class="item-actions"><a href="<?= e(form_url('trivia', (int) $t['id'])) ?>">Edit</a></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= limit_note(count($trivia), $counts['trivia']) ?>
    </section>
</div>

<!-- Gallery add/edit popup -->
<dialog id="gallery-dialog" class="dialog" aria-labelledby="gallery-dialog-title">
    <form id="gallery-form" novalidate>
        <h2 class="dialog-title" id="gallery-dialog-title">Add image</h2>
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="version" value="">

        <img id="gallery-preview" class="gallery-preview" alt="" hidden>

        <div class="field">
            <label class="field-label-sm" for="gal-image" id="gal-image-label">Image (JPG, PNG or WebP, max 5 MB)</label>
            <input class="input" type="file" id="gal-image" name="image" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="field">
            <label class="field-label-sm" for="gal-title-input">Title</label>
            <input class="input" type="text" id="gal-title-input" name="title" maxlength="150" required>
        </div>
        <div class="field">
            <label class="field-label-sm" for="gal-caption">Caption</label>
            <input class="input" type="text" id="gal-caption" name="caption" maxlength="255">
        </div>
        <div class="field">
            <label class="field-label-sm" for="gal-status">Status</label>
            <select class="select" id="gal-status" name="status">
                <?php foreach (CONTENT_STATUSES as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flash flash-error" id="gallery-message" role="alert" hidden></div>
        <div class="dialog-actions">
            <button type="button" class="btn btn-red" id="gallery-delete" hidden>Delete</button>
            <span class="spacer"></span>
            <button type="button" class="btn btn-light" id="gallery-cancel">Cancel</button>
            <button type="submit" class="btn btn-dark" id="gallery-save">Save</button>
        </div>
    </form>
</dialog>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
