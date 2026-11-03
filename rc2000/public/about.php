<?php
require_once __DIR__ . '/../includes/functions.php';

$reviews = published_content('review', 6);
// Replace this filename with an image placed in public/assets/images/.
$founderImage = 'circlephoto.jpg';
$founderImagePath = __DIR__ . '/assets/images/' . $founderImage;
page_start('About');
?>
<main>
  <section class="page-hero red">
    <div class="narrow">
      <h1>History of Reading Club 2000</h1>
      <p>Reading Club 2000 is an informal library set up by Hernando Guanlao outside his ancestral home in central Makati, Philippines. Hernando Guanlao, known by his nickname Nanie, wanted to encourage his local community to share his joy of reading.</p>
      <a class="button button-gold" href="/rc2000/public/about.php#founder">Read More</a>
    </div>
  </section>

  <section class="section about-person" id="founder" data-reveal>
    <?php if (is_file($founderImagePath)): ?>
      <img class="founder-portrait" src="<?= e(app_url('public/assets/images/' . $founderImage)) ?>" alt="Hernando “Mang Nanie” Guanlao" data-reveal>
    <?php else: ?>
      <div class="portrait-placeholder" aria-hidden="true" data-reveal>NG</div>
    <?php endif; ?>
    <div>
      <h2>Hernando “Mang Nanie” Guanlao</h2>
      <p>Founder of the original community library movement. Mang Nanie inspired the model this club follows — free books, open doors, no library card required.</p>
    </div>
  </section>

  <section class="section" data-reveal>
    <h2>Testimonial</h2>
    <div class="carousel testimonial-carousel" data-carousel>
      <div class="carousel-track">
        <?php if ($reviews): foreach ($reviews as $item): ?>
          <blockquote class="testimonial">
            <p>“<?= e(excerpt($item['body'], 220)) ?>”</p>
            <cite><?= e($item['author_name'] ?: $item['title']) ?></cite>
          </blockquote>
        <?php endforeach; else: ?>
          <blockquote class="testimonial">
            <p>“A library on a sidewalk teaches a whole neighborhood that knowledge isn’t locked behind a fee.”</p>
          </blockquote>
        <?php endif; ?>
      </div>
      <a class="see-all" href="/rc2000/public/explore.php">See all →</a>
    </div>
  </section>

  <section class="section mission-vision" aria-label="Our mission and vision">
    <article class="info-card" data-reveal>
      <h2>Mission</h2>
      <p>Make books and reading welcoming and accessible to our neighborhood.</p>
    </article>
    <article class="info-card navy" data-reveal>
      <h2>Vision</h2>
      <p>A community where everyone can find, share, and enjoy a good book.</p>
    </article>
  </section>
</main>
<?php page_end(); ?>
