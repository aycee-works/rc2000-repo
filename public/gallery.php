<?php
require_once __DIR__ . '/../includes/functions.php';

$images = active_gallery(48);
page_start('Gallery');
?>
<main class="section" data-reveal>
  <div class="section-heading">
    <div>
      <span class="eyebrow">Around the library</span>
      <h1>Gallery</h1>
      <p>People, books, and moments from Reading Club 2000.</p>
    </div>
  </div>
  <div class="gallery-grid mosaic">
    <?php if ($images): foreach ($images as $index => $image): ?>
      <figure>
        <?php if (media_file_exists($image['image_path'])): ?>
          <img src="<?= e(media_url($image['image_path'])) ?>" alt="<?= e($image['alt_text'] ?? '') ?>">
        <?php else: ?>
          <div class="gallery-tile art-<?= ($index % 4) + 1 ?>"><span><?= e($image['caption'] ?: 'Reading Club 2000') ?></span></div>
        <?php endif; ?>
        <?php if (!empty($image['caption'])): ?>
          <figcaption><?= e($image['caption']) ?></figcaption>
        <?php endif; ?>
      </figure>
    <?php endforeach; else: foreach (range(1, 8) as $index): ?>
      <div class="gallery-tile art-<?= (($index - 1) % 4) + 1 ?>">
        <span>Gallery image <?= $index ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php page_end(); ?>
