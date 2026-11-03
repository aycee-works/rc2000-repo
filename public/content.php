<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$contentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$content = null;

if ($contentId && $contentId > 0) {
    foreach (['story', 'article'] as $type) {
        $items = published_content($type, 1000);

        foreach ($items as $item) {
            if ((int) ($item['content_id'] ?? 0) === $contentId) {
                $content = $item;
                break 2;
            }
        }
    }
}

if (!$content) {
    http_response_code(404);
    page_start('Content Not Found');
    ?>
    <main class="section narrow">
        <p class="eyebrow">Reading Club 2000</p>
        <h1>Content not found</h1>
        <p>
            This story or article may no longer be available,
            or its link may be invalid.
        </p>
        <a
            class="button button-gold"
            href="<?= e(app_url('public/explore.php')) ?>"
        >
            Back to Explore
        </a>
    </main>
    <?php
    page_end();
    exit;
}

$type = (string) ($content['content_type'] ?? 'story');
$title = (string) ($content['title'] ?? 'Untitled');
$body = (string) ($content['body'] ?? '');
$cover = (string) ($content['cover_image_path'] ?? '');
$author = (string) ($content['author_name'] ?? '');
$publishedAt = $content['published_at'] ?? null;

$pageTitle = $type === 'article' ? 'Article' : 'Story';

page_start($title);
?>

<main>
    <section class="page-hero red">
        <div class="section narrow">
            <p class="eyebrow">
                <?= e($pageTitle) ?>
            </p>

            <h1><?= e($title) ?></h1>

            <?php if ($author !== ''): ?>
                <p>By <?= e($author) ?></p>
            <?php endif; ?>

            <?php if (!empty($publishedAt)): ?>
                <p>
                    <?= e(date('F j, Y', strtotime((string) $publishedAt))) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="section narrow">
        <?php if ($cover !== '' && media_file_exists($cover)): ?>
            <img
                src="<?= e(media_url($cover)) ?>"
                alt="<?= e($title) ?>"
                class="content-cover"
                loading="lazy"
            >
        <?php endif; ?>

        <div class="content-body">
            <?= nl2br(e($body)) ?>
        </div>

        <p>
            <a
                class="button button-gold"
                href="<?= e(app_url('public/explore.php')) ?>"
            >
                Back to Explore
            </a>
        </p>
    </section>
</main>

<?php page_end(); ?>