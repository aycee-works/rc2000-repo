
<?php
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| Retrieve published content
|--------------------------------------------------------------------------
*/

// Retrieve featured stories first.
// If no featured stories exist, display regular published stories.
$stories = published_content('featured_story', 8);

if (empty($stories)) {
    $stories = published_content('story', 8);
}

// Retrieve published articles and announcements.
$articles = published_content('article', 4);
$announcements = published_content('announcement', 6);

/*
|--------------------------------------------------------------------------
| Homepage images
|--------------------------------------------------------------------------
*/

$homeImages = [
    'banner' => 'home_banner.jpg',
    'stories' => [
        'stories/sfeatured1.png',
        'stories/sfeatured2.png',
        'stories/sfeatured3.png',
        'stories/sfeatured4.png',
    ],
    'articles' => [
        'articles/afeatured1.png',
        'articles/afeatured1-2.png',
        'articles/afeatured2.png',
    ],
];

/*
|--------------------------------------------------------------------------
| Homepage image helper
|--------------------------------------------------------------------------
*/

$homeImageUrl = static function (string $filename): ?string {
    $filename = ltrim($filename, '/\\');

    $filePath = __DIR__ . '/assets/images/' . $filename;

    if (is_file($filePath)) {
        return app_url('public/assets/images/' . $filename);
    }

    return null;
};

$heroImage = $homeImageUrl($homeImages['banner']);

page_start('Home');
?>

<main>

    <!-- HERO SECTION -->

    <section class="hero<?= $heroImage ? ' has-photo' : '' ?>">

        <div class="hero-copy" data-reveal>

            <span class="eyebrow">
                Reading Club 2000 · Makati
            </span>

            <h1>
                Give a book<br>
                a second story.
            </h1>

            <p>
                Borrow, donate, and take books with your neighborhood
                reading club — started by Mang Nanie in Barangay La Paz.
            </p>

            <div class="button-row">

                <a
                    class="button button-gold"
                    href="<?= e(app_url('public/donate.php')) ?>"
                >
                    Donate Now
                </a>

                <a
                    class="button button-ghost"
                    href="<?= e(app_url('public/about.php')) ?>"
                >
                    Learn more
                </a>

            </div>

        </div>

        <div class="hero-art" aria-hidden="true">

            <?php if ($heroImage): ?>

                <img
                    src="<?= e($heroImage) ?>"
                    alt=""
                >

            <?php else: ?>

                <div class="book-stack">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <p>Stories are better when shared.</p>

            <?php endif; ?>

        </div>

    </section>


    <!-- FEATURED STORIES -->

    <section
        class="section featured-stories"
        id="featured-stories"
        data-reveal
    >

        <div class="section-heading">

            <div>
                <span class="eyebrow">From our shelves</span>
                <h2>Featured Stories</h2>
            </div>

            <a href="<?= e(app_url('public/explore.php')) ?>">
                See all →
            </a>

        </div>

        <?php if (!empty($stories)): ?>

            <div class="featured-story-grid">

                <?php foreach ($stories as $index => $item): ?>

                    <?php
                    $storyId = (int) ($item['content_id'] ?? 0);
                    $storyTitle = $item['title'] ?? 'Untitled Story';
                    $storyBody = $item['body'] ?? '';
                    $storyCover = $item['cover_image_path'] ?? '';
                    ?>

                    <a
                        class="story-card"
                        data-reveal
                        href="<?= e(app_url('public/content.php')) ?>?id=<?= $storyId ?>"
                    >

                        <?php if (
                            $storyCover !== '' &&
                            media_file_exists($storyCover)
                        ): ?>

                            <img
                                src="<?= e(media_url($storyCover)) ?>"
                                alt="<?= e($storyTitle) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <?php
                            $decorativeImage = $homeImageUrl(
                                $homeImages['stories'][
                                    $index % count($homeImages['stories'])
                                ]
                            );
                            ?>

                            <?php if ($decorativeImage): ?>

                                <img
                                    src="<?= e($decorativeImage) ?>"
                                    alt=""
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div
                                    class="card-art art-<?= ($index % 4) + 1 ?>"
                                    aria-hidden="true"
                                ></div>

                            <?php endif; ?>

                        <?php endif; ?>

                        <div class="story-card-content">

                            <h3><?= e($storyTitle) ?></h3>

                            <p>
                                <?= e(excerpt($storyBody, 110)) ?>
                            </p>

                            <span class="story-read-link">
                                Read story
                                <span aria-hidden="true">→</span>
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p>No published stories are available yet.</p>

        <?php endif; ?>

    </section>


    <!-- FEATURED ARTICLES -->

    <section
        class="section"
        id="featured-articles"
        data-reveal
    >

        <div class="section-heading">

            <div>
                <span class="eyebrow">Read and discover</span>
                <h2>Featured Articles</h2>
            </div>

            <a href="<?= e(app_url('public/explore.php')) ?>#articles">
                Read more →
            </a>

        </div>

        <?php if (!empty($articles)): ?>

            <!-- Reuse the Stories card grid for a consistent appearance. -->
            <div class="featured-story-grid">

                <?php foreach ($articles as $index => $item): ?>

                    <?php
                    $articleId = (int) ($item['content_id'] ?? 0);
                    $articleTitle = $item['title'] ?? 'Untitled Article';
                    $articleBody = $item['body'] ?? '';
                    $articleCover = $item['cover_image_path'] ?? '';
                    ?>

                    <a
                        class="story-card"
                        data-reveal
                        href="<?= e(app_url('public/content.php')) ?>?id=<?= $articleId ?>"
                    >

                        <?php if (
                            $articleCover !== '' &&
                            media_file_exists($articleCover)
                        ): ?>

                            <img
                                src="<?= e(media_url($articleCover)) ?>"
                                alt="<?= e($articleTitle) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <?php
                            $decorativeImage = $homeImageUrl(
                                $homeImages['articles'][
                                    $index % count($homeImages['articles'])
                                ]
                            );
                            ?>

                            <?php if ($decorativeImage): ?>

                                <img
                                    src="<?= e($decorativeImage) ?>"
                                    alt=""
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div
                                    class="card-art art-<?= ($index % 4) + 1 ?>"
                                    aria-hidden="true"
                                ></div>

                            <?php endif; ?>

                        <?php endif; ?>

                        <div class="story-card-content">

                            <h3><?= e($articleTitle) ?></h3>

                            <p>
                                <?= e(excerpt($articleBody, 120)) ?>
                            </p>

                            <span class="story-read-link">
                                Read article
                                <span aria-hidden="true">→</span>
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p>No published articles are available yet.</p>

        <?php endif; ?>

    </section>


    <!-- ANNOUNCEMENTS -->

    <section
        class="section announcement-band"
        id="announcements"
        data-reveal
    >

        <div>
            <span class="eyebrow">BE UPDATED WITH RC2000</span>
            <h2>Announcements</h2>
        </div>

        <div class="announcement-slider" data-carousel>

            <div class="carousel-track">

                <?php if (!empty($announcements)): ?>

                    <?php foreach ($announcements as $item): ?>

                        <article class="announcement">

                            <h3>
                                <?= e($item['title'] ?? 'Announcement') ?>
                            </h3>

                            <p>
                                <?= e(
                                    excerpt($item['body'] ?? '', 280)
                                ) ?>
                            </p>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <article class="announcement">

                        <p>
                            Reading events, new stories, and community
                            updates will appear here.
                        </p>

                    </article>

                <?php endif; ?>

            </div>

            <a
                class="see-all"
                href="<?= e(app_url('public/explore.php')) ?>"
            >
                See all →
            </a>

            <div class="carousel-controls">

                <button
                    type="button"
                    data-prev
                    aria-label="Previous announcement"
                >
                    ←
                </button>

                <button
                    type="button"
                    data-next
                    aria-label="Next announcement"
                >
                    →
                </button>

            </div>

        </div>

    </section>

</main>

<?php page_end(); ?>
