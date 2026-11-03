
<?php
require_once __DIR__ . '/../includes/functions.php';

$query = trim((string) ($_GET['q'] ?? ''));

$results = $query !== ''
    ? search_published_content($query, 24)
    : [];

$gallery = active_gallery(8);
$articles = published_content('article', 8);
$stories = published_content('story', 8);
$trivia = published_trivia(3);

// Keep this empty until a published history source is connected.
$history = [];

/*
|--------------------------------------------------------------------------
| Content URL helper
|--------------------------------------------------------------------------
| published_content() returns content_id, not id.
*/

$contentUrl = static function (array $item): string {
    $id = (int) ($item['content_id'] ?? 0);

    if ($id <= 0) {
        return app_url('public/explore.php');
    }

    return app_url('public/content.php') . '?id=' . $id;
};

page_start('Explore');
?>

<main>

    <!-- SEARCH RESULTS -->

    <?php if ($query !== ''): ?>

        <section class="section">

            <div class="section-heading">

                <div>
                    <span class="eyebrow">Search</span>
                    <h1>Results for “<?= e($query) ?>”</h1>
                </div>

            </div>

            <?php if (!$results): ?>

                <p>No published stories or articles matched that search.</p>

            <?php else: ?>

                <div class="card-grid three">

                    <?php foreach ($results as $item): ?>

                        <?php
                        $resultType = (string) ($item['content_type'] ?? 'story');
                        $resultTitle = $item['title'] ?? 'Untitled';
                        $resultBody = $item['body'] ?? '';
                        ?>

                        <article class="story-card">

                            <?php
                            $resultCover = $item['cover_image_path'] ?? '';
                            ?>

                            <?php if (
                                $resultCover !== '' &&
                                media_file_exists($resultCover)
                            ): ?>

                                <img
                                    src="<?= e(media_url($resultCover)) ?>"
                                    alt="<?= e($resultTitle) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div
                                    class="card-art art-1"
                                    aria-hidden="true"
                                ></div>

                            <?php endif; ?>

                            <div class="story-card-content">

                                <p class="eyebrow">
                                    <?= e(str_replace('_', ' ', $resultType)) ?>
                                </p>

                                <h2><?= e($resultTitle) ?></h2>

                                <p>
                                    <?= e(excerpt($resultBody, 160)) ?>
                                </p>

                                <a
                                    class="text-link"
                                    href="<?= e($contentUrl($item)) ?>"
                                >
                                    Read more →
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>


    <!-- GALLERY -->

    <section class="section" id="gallery">

        <div class="section-heading">

            <div>
                <span class="eyebrow">Around the library</span>
                <h1>Gallery</h1>
            </div>

            <a href="<?= e(app_url('public/gallery.php')) ?>">
                Open gallery →
            </a>

        </div>

        <div class="gallery-grid mosaic">

            <?php if ($gallery): ?>

                <?php foreach ($gallery as $index => $image): ?>

                    <figure class="span-<?= ($index % 5) + 1 ?>">

                        <?php if (
                            media_file_exists($image['image_path'] ?? '')
                        ): ?>

                            <img
                                src="<?= e(media_url($image['image_path'])) ?>"
                                alt="<?= e($image['alt_text'] ?? '') ?>"
                                loading="lazy"
                            >

                            <?php if (!empty($image['caption'])): ?>

                                <figcaption>
                                    <?= e($image['caption']) ?>
                                </figcaption>

                            <?php endif; ?>

                        <?php else: ?>

                            <div class="gallery-tile art-<?= ($index % 4) + 1 ?>">
                                <span>
                                    <?= e($image['caption'] ?? 'Reading Club 2000') ?>
                                </span>
                            </div>

                        <?php endif; ?>

                    </figure>

                <?php endforeach; ?>

            <?php else: ?>

                <?php foreach (range(1, 6) as $index): ?>

                    <div class="gallery-tile art-<?= (($index - 1) % 4) + 1 ?>"></div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </section>


    <!-- STORIES -->

    <section class="section" id="stories">

        <div class="section-heading">

            <div>
                <span class="eyebrow">From the community</span>
                <h1>Stories</h1>
                <p>Discover stories shared by Reading Club 2000.</p>
            </div>

        </div>

        <?php if (!empty($stories)): ?>

            <div class="card-grid three">

                <?php foreach ($stories as $index => $item): ?>

                    <?php
                    $title = $item['title'] ?? 'Untitled Story';
                    $body = $item['body'] ?? '';
                    $cover = $item['cover_image_path'] ?? '';
                    ?>

                    <article class="story-card">

                        <?php if (
                            $cover !== '' &&
                            media_file_exists($cover)
                        ): ?>

                            <img
                                src="<?= e(media_url($cover)) ?>"
                                alt="<?= e($title) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div
                                class="card-art art-<?= ($index % 4) + 1 ?>"
                                aria-hidden="true"
                            ></div>

                        <?php endif; ?>

                        <div class="story-card-content">

                            <h3><?= e($title) ?></h3>

                            <?php if (trim($body) !== ''): ?>

                                <p>
                                    <?= e(excerpt($body, 180)) ?>
                                </p>

                            <?php endif; ?>

                            <a
                                class="text-link"
                                href="<?= e($contentUrl($item)) ?>"
                            >
                                Read story →
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p>No published stories are available yet.</p>

        <?php endif; ?>

    </section>


    <!-- ARTICLES -->

    <section class="section tinted" id="articles">

        <div class="section-heading">

            <div>
                <span class="eyebrow">Reading and learning</span>
                <h1>Articles</h1>
                <p>Explore articles published by Reading Club 2000.</p>
            </div>

        </div>

        <?php if (!empty($articles)): ?>

            <!-- Articles now reuse the white Stories card design. -->
            <div class="card-grid three">

                <?php foreach ($articles as $index => $item): ?>

                    <?php
                    $title = $item['title'] ?? 'Untitled Article';
                    $body = $item['body'] ?? '';
                    $cover = $item['cover_image_path'] ?? '';
                    ?>

                    <article class="story-card">

                        <?php if (
                            $cover !== '' &&
                            media_file_exists($cover)
                        ): ?>

                            <img
                                src="<?= e(media_url($cover)) ?>"
                                alt="<?= e($title) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div
                                class="card-art art-<?= ($index % 4) + 1 ?>"
                                aria-hidden="true"
                            ></div>

                        <?php endif; ?>

                        <div class="story-card-content">

                            <h3><?= e($title) ?></h3>

                            <?php if (trim($body) !== ''): ?>

                                <p>
                                    <?= e(excerpt($body, 180)) ?>
                                </p>

                            <?php endif; ?>

                            <a
                                class="text-link"
                                href="<?= e($contentUrl($item)) ?>"
                            >
                                Read article →
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p>No published articles are available yet.</p>

        <?php endif; ?>

    </section>


    <!-- TRIVIA AND LIBRARY HISTORY -->

    <section class="section split">

        <div>

            <h2>Trivia</h2>

            <div class="trivia-list">

                <?php if ($trivia): ?>

                    <?php foreach ($trivia as $item): ?>

                        <article class="trivia-card">

                            <div class="trivia-icon">?</div>

                            <div>

                                <h3>
                                    <?= e($item['title'] ?: 'Did you know?') ?>
                                </h3>

                                <p>
                                    <?= e(excerpt($item['body'], 140)) ?>
                                </p>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <article class="trivia-card">

                        <div class="trivia-icon">?</div>

                        <div>

                            <h3>Did you know?</h3>

                            <p>
                                A sidewalk library can turn leftover books
                                into a neighborhood habit.
                            </p>

                        </div>

                    </article>

                <?php endif; ?>

            </div>

            <p>
                <a
                    class="text-link"
                    href="<?= e(app_url('public/daily_trivia.php')) ?>"
                >
                    More trivia →
                </a>
            </p>

        </div>

        <aside class="history-panel" id="history">

            <h2>Library History</h2>

            <?php if ($history): ?>

                <h3><?= e($history[0]['title']) ?></h3>

                <p>
                    <?= e(excerpt($history[0]['body'], 420)) ?>
                </p>

            <?php else: ?>

                <p>
                    It all began in 2000. Mang Nanie was about to turn 60
                    when he found himself worrying about retirement.
                    Instead of slowing down, he gathered books from around
                    the house, set them out, and showed neighbors that
                    reading could be free. The rest is history.
                </p>

            <?php endif; ?>

            <a
                class="text-link"
                href="<?= e(app_url('public/about.php')) ?>"
            >
                More →
            </a>

        </aside>

    </section>

</main>

<?php page_end(); ?>
