<?php
// admin/content/form.php - create / edit a story or article, announcement, or trivia entry
// URL: form.php?entity=story|announcement|trivia  [&id=123]  [&kind=article  (new story only)]
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/content-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$entity = get_str('entity');
if (!in_array($entity, ['story', 'announcement', 'trivia'], true)) {
    $entity = 'story';
}
$id = max(0, (int) ($_GET['id'] ?? 0));
$row = null;

try {
    if ($id > 0) {
        $row = get_content(db(), $entity, $id);
        if (!$row) {
            flash('error', 'That item could not be found.');
            redirect('admin/content/index.php');
        }
    }
} catch (Throwable $e) {
    error_log('Content form load failed: ' . $e->getMessage());
    flash('error', 'The item could not be loaded. Please try again.');
    redirect('admin/content/index.php');
}

$isNew = $row === null;
$statuses = content_entities()[$entity]['statuses'];
$v = fn(string $key, string $default = '') => $row[$key] ?? $default;      // current value helper
$kind = $isNew ? (get_str('kind') === 'article' ? 'article' : 'story') : ($row['type'] ?? 'story');
$noun = ['story' => 'story or article', 'announcement' => 'announcement', 'trivia' => 'trivia'][$entity];
$imageCol = ['story' => 'cover_path', 'announcement' => 'image_path'][$entity] ?? null;
$currentImage = $imageCol ? ($row[$imageCol] ?? null) : null;

$pageTitle = ($isNew ? 'New ' : 'Edit ') . $noun;
$activeNav = 'content';
$extraCss  = ['content.css'];
$extraJs   = ['content.js'];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title"><?= e(ucfirst($pageTitle)) ?></h1>
    <p class="page-subtitle"><a href="<?= e(url('admin/content/index.php')) ?>">&larr; Back to Manage content</a></p>
</header>

<section class="panel">
<form id="content-form" class="panel-body content-form" novalidate enctype="multipart/form-data"
      data-action="<?= e($entity) ?>_save" data-entity="<?= e($entity) ?>">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="version" value="<?= e($v('updated_at')) ?>">

    <?php if ($entity === 'trivia'): ?>
        <div class="field">
            <label class="field-label-sm" for="f-question">Question</label>
            <input class="input" type="text" id="f-question" name="question" maxlength="255" required value="<?= e($v('question')) ?>">
        </div>
        <div class="field">
            <label class="field-label-sm" for="f-answer">Answer</label>
            <input class="input" type="text" id="f-answer" name="answer" maxlength="255" required value="<?= e($v('answer')) ?>">
        </div>
        <div class="field">
            <label class="field-label-sm" for="f-expl">Explanation (optional)</label>
            <textarea class="textarea" id="f-expl" name="explanation" rows="4" maxlength="2000"><?= e($v('explanation')) ?></textarea>
        </div>
    <?php else: ?>
        <?php if ($entity === 'story'): ?>
            <div class="field field-narrow">
                <label class="field-label-sm" for="f-type">Type</label>
                <select class="select" id="f-type" name="type">
                    <option value="story"<?= $kind === 'story' ? ' selected' : '' ?>>Story</option>
                    <option value="article"<?= $kind === 'article' ? ' selected' : '' ?>>Article</option>
                </select>
            </div>
        <?php endif; ?>
        <div class="field">
            <label class="field-label-sm" for="f-title">Title</label>
            <input class="input" type="text" id="f-title" name="title" maxlength="200" required value="<?= e($v('title')) ?>">
        </div>
        <?php if ($entity === 'story'): ?>
            <div class="field">
                <label class="field-label-sm" for="f-author">Author / attribution (optional)</label>
                <input class="input" type="text" id="f-author" name="author" maxlength="120" value="<?= e($v('author')) ?>">
            </div>
        <?php endif; ?>
        <div class="field">
            <label class="field-label-sm" for="f-body"><?= $entity === 'story' ? 'Content' : 'Announcement text' ?></label>
            <textarea class="textarea" id="f-body" name="body" rows="<?= $entity === 'story' ? 14 : 8 ?>" required><?= e($v('body')) ?></textarea>
        </div>
        <div class="field">
            <label class="field-label-sm" for="f-image"><?= $entity === 'story' ? 'Cover image' : 'Image' ?> (optional · JPG, PNG or WebP, max 5 MB)</label>
            <?php if ($currentImage): ?>
                <div class="current-image">
                    <img src="<?= e(url($currentImage)) ?>" alt="Current image" id="f-current">
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                </div>
            <?php endif; ?>
            <input class="input" type="file" id="f-image" name="image" accept="image/jpeg,image/png,image/webp">
            <img id="f-preview" class="gallery-preview" alt="" hidden>
        </div>
        <div class="field field-narrow">
            <label class="field-label-sm" for="f-date">Publish date (optional)</label>
            <input class="input" type="date" id="f-date" name="published_date"
                   value="<?= e($row && $row['published_at'] ? substr($row['published_at'], 0, 10) : '') ?>">
        </div>
    <?php endif; ?>

    <div class="field field-narrow">
        <label class="field-label-sm" for="f-status">Status</label>
        <select class="select" id="f-status" name="status">
            <?php foreach ($statuses as $key): ?>
                <option value="<?= e($key) ?>"<?= $key === ($row['status'] ?? 'draft') ? ' selected' : '' ?>><?= e(CONTENT_STATUSES[$key]) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="review-hint">Only published items appear on the public website. Draft and archived items stay hidden.</p>
    </div>

    <div class="flash flash-error" id="form-message" role="alert" hidden></div>
    <div class="form-actions">
        <?php if (!$isNew): ?>
            <button type="button" class="btn btn-red" id="content-delete" data-entity="<?= e($entity) ?>" data-id="<?= (int) $id ?>">Delete</button>
        <?php endif; ?>
        <span class="spacer"></span>
        <a class="btn btn-light" href="<?= e(url('admin/content/index.php')) ?>">Cancel</a>
        <button type="submit" class="btn btn-dark" id="content-save">Save</button>
    </div>
</form>
</section>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
