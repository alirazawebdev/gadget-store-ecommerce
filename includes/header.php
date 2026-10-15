<?php
/**
 * Frontend header — qadrigadgets.pk style.
 * Set $pageTitle, $pageDesc, $pageKeys before including.
 */
require_once __DIR__ . '/functions.php';

$seoTitle = (!empty($pageTitle) ? $pageTitle . ' | ' : '') . (setting('site_title') ?: site_name());
$seoDesc  = $pageDesc ?? setting('meta_description', '');
$seoKeys  = $pageKeys ?? setting('meta_keywords', '');
$cats     = active_categories();
$cartN    = cart_count();
$logo     = setting('site_logo') ? img_url(setting('site_logo')) : base_url('assets/images/logo.svg');
$phone    = setting('contact_phone');
$phoneDigits = preg_replace('/\D/', '', (string)$phone);
/* cache-busting: bump query string whenever the files change */
$cssVer = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
$patchVer = @filemtime(__DIR__ . '/../assets/css/patch.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($seoTitle) ?></title>
<meta name="description" content="<?= e($seoDesc) ?>">
<?php if ($seoKeys): ?><meta name="keywords" content="<?= e($seoKeys) ?>"><?php endif; ?>
<?= setting('gsc_code') /* raw: Search Console verification meta */ ?>
<?= setting('ga_code')  /* raw: Google Analytics script */ ?>
<link rel="icon" href="<?= e($logo) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=<?= (int)$cssVer ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/patch.css') ?>?v=<?= (int)$patchVer ?>">
</head>
<body>

<?php if ($ann = setting('announcement_text')): ?>
<div class="top-marquee">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 6; $i++): ?><span><?= e($ann) ?></span><span class="m-dot">&#9679;</span><?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="container hd-row">
    <a class="logo" href="<?= base_url() ?>">
      <img src="<?= e($logo) ?>" alt="<?= e(site_name()) ?>">
      <span><?= e(site_name()) ?></span>
    </a>

    <nav class="hd-nav" id="hdNav">
      <a href="<?= base_url() ?>">Home</a>
      <a href="<?= base_url('category.php') ?>">All Products</a>
      <div class="hd-drop">
        <a href="<?= base_url('category.php') ?>" class="hd-drop-btn">Product Categories &#9662;</a>
        <div class="hd-drop-menu">
          <?php foreach ($cats as $c): ?>
          <a href="<?= category_url($c) ?>"><?= e($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?= base_url('track-order.php') ?>">Track Order</a>
      <a href="<?= base_url('my-orders.php') ?>">My Orders</a>
      <a href="<?= base_url('page.php?slug=contact-us') ?>">Contact Us</a>
    </nav>

    <div class="hd-icons">
      <button class="hd-ic" id="searchToggle" aria-label="Search">&#128269;</button>
      <?php if ($phoneDigits): ?>
      <a class="hd-ic" href="tel:<?= e($phoneDigits) ?>" aria-label="Call us">&#128222;</a>
      <?php endif; ?>
      <a class="hd-ic hd-cart" href="<?= base_url('cart.php') ?>" aria-label="Cart">&#128722;<span class="cart-count"><?= (int)$cartN ?></span></a>
      <button class="hd-ic hd-burger" id="menuToggle" aria-label="Menu">&#9776;</button>
    </div>
  </div>

  <div class="hd-search" id="hdSearch" style="display:none">
    <div class="container">
      <form class="search-form" action="<?= base_url('search.php') ?>" method="get">
        <input type="text" name="q" placeholder="Search products..." value="<?= e($_GET['q'] ?? '') ?>" required>
        <button type="submit">Search</button>
      </form>
    </div>
  </div>
</header>

<main>
<?php show_flash(); ?>
