<?php
$searchValue = trim((string) ($_GET['q'] ?? ''));
?>
<header class="site-header">
  <a class="brand" href="/rc2000/public/home.php" aria-label="Reading Club 2000 home">
    <img class="brand-logo" src="<?= e(app_url('public/assets/images/icons/logo.png')) ?>" alt="Reading Club 2000 logo" width="24" height="24">
    <span class="brand-name">Reading Club 2000</span>
  </a>
  <nav class="main-nav" aria-label="Main navigation">
    <a href="/rc2000/public/home.php"<?= nav_active('home.php') ?>>Home</a>
    <a href="/rc2000/public/explore.php"<?= nav_active('explore.php') ?>>Explore</a>
    <a href="/rc2000/public/donate.php"<?= nav_active('donate.php') ?>>Donate</a>
    <a href="/rc2000/public/inquiry.php"<?= nav_active('inquiry.php') ?>>Inquiry</a>
    <a href="/rc2000/public/about.php"<?= nav_active('about.php') ?>>About</a>
    <a href="/rc2000/public/contact.php"<?= nav_active('contact.php') ?>>Contact Us</a>
  </nav>
  <form class="site-search" action="/rc2000/public/explore.php" method="get" role="search">
    <label class="sr-only" for="site-search">Search</label>
    <input id="site-search" type="search" name="q" placeholder="Search" value="<?= e($searchValue) ?>">
    <button type="submit" aria-label="Search"><img src="<?= e(app_url('public/assets/images/icons/search_icon.png')) ?>" alt="" width="20" height="20"></button>
  </form>
</header>
