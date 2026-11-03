<?php
require_once __DIR__ . '/../includes/functions.php';

$allTrivia = published_trivia(50);
$dayNumber = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('z');
$trivia = $allTrivia ? [$allTrivia[$dayNumber % count($allTrivia)]] : [];
page_start('Daily Trivia');
?>
<main class="section">
  <div class="section-heading">
    <div>
      <span class="eyebrow">A little something to learn</span>
      <h1>Daily trivia</h1>
      <p>A new bookish fact from the club every day.</p>
    </div>
  </div>
  <div class="trivia-list wide">
    <?php if ($trivia): foreach ($trivia as $item): ?>
      <article class="trivia-card">
        <div class="trivia-icon">?</div>
        <div>
          <h2><?= e($item['title'] ?: 'Did you know?') ?></h2>
          <p><?= nl2br(e($item['body'])) ?></p>
          <?php if (!empty($item['source'])): ?>
            <small>Source: <?= e($item['source']) ?></small>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; else: ?>
      <article class="trivia-card">
        <div class="trivia-icon">?</div>
        <div>
          <h2>Did you know?</h2>
          <p>Reading for just a few minutes each day can introduce you to new ideas, places, and people.</p>
          <small>Add published rows to the trivia table to show your own facts.</small>
        </div>
      </article>
    <?php endif; ?>
  </div>
</main>
<?php page_end(); ?>
