<?php
/**
 * Homepage — hero slider (clean banners), categories marquee,
 * flash sale, product marquees.
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = setting('site_title') ?: site_name();

$sliders  = db()->query("SELECT * FROM sliders WHERE status = 'active' ORDER BY sort_order, id")->fetchAll();
$cats     = active_categories();
$flash    = db()->query("SELECT * FROM products WHERE status = 'active' AND flash_sale = 1 AND (flash_ends_at IS NULL OR flash_ends_at > NOW()) ORDER BY id DESC LIMIT 12")->fetchAll();
$featured = db()->query("SELECT * FROM products WHERE status = 'active' AND featured = 1 ORDER BY id DESC LIMIT 12")->fetchAll();
$newArr   = db()->query("SELECT * FROM products WHERE status = 'active' AND is_new = 1 ORDER BY id DESC LIMIT 12")->fetchAll();
$onSale   = db()->query("SELECT * FROM products WHERE status = 'active' AND on_sale = 1 ORDER BY id DESC LIMIT 12")->fetchAll();

$flashEnd = null;
foreach ($flash as $f) {
    if (!empty($f['flash_ends_at']) && ($flashEnd === null || $f['flash_ends_at'] < $flashEnd)) {
        $flashEnd = $f['flash_ends_at'];
    }
}

/* Shop Under price badges (slider, like qadrigadgets.pk) */
$shopUnder = [500, 1000, 1500, 2000, 3000, 5000];

include __DIR__ . '/includes/header.php';
?>

<?php if ($sliders): ?>
<div class="hero-slider">
  <?php foreach ($sliders as $s): ?>
  <div class="slide">
    <?php if ($s['link']): ?><a href="<?= e($s['link']) ?>"><?php endif; ?>
      <?php $mobImg = $s['mobile_image'] ?? ''; ?>
      <?php if ($mobImg): ?>
      <picture>
        <source media="(max-width: 768px)" srcset="<?= e(img_url($mobImg)) ?>">
        <img src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['title']) ?>">
      </picture>
      <?php else: ?>
      <img src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['title']) ?>">
      <?php endif; ?>
    <?php if ($s['link']): ?></a><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="slider-dots" id="sliderDots"></div>
</div>
<?php endif; ?>

<?php if ($cats): ?>
<section class="section">
  <div class="container">
    <h2 class="sec-title">Shop by Category</h2>
    <p class="sec-sub">Top picks across every shelf</p>
    <div class="cat-marquee">
      <div class="cat-track">
        <?php foreach (marquee_items($cats, 16) as $c): ?>
        <a class="cat-circle" href="<?= category_url($c) ?>">
          <img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
          <span><?= e($c['name']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <h2 class="sec-title">Shop by Price</h2>
    <p class="sec-sub">Budget-friendly picks for everyone</p>
  </div>
  <div class="su-marquee">
    <div class="su-track">
      <?php foreach (array_merge($shopUnder, $shopUnder) as $amt): ?>
      <a class="su-badge" href="<?= base_url('category.php?price_max=' . $amt . '&sort=price_asc') ?>">
        <span class="su-top">SHOP</span>
        <span class="su-ribbon">UNDER</span>
        <span class="su-price">PKR <?= number_format($amt) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($flash): ?>
<section class="section">
  <div class="container">
    <div class="flash-section">
      <h2 class="sec-title sec-title-light">&#9889; Flash Sale</h2>
      <?php if ($flashEnd): ?>
      <div class="countdown countdown-center" data-countdown="<?= e(date('Y-m-d\TH:i:s', strtotime($flashEnd))) ?>"></div>
      <?php endif; ?>
      <div class="product-grid">
        <?php foreach ($flash as $p): ?>
          <?= product_card_html($p) ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section">
  <div class="container">
    <h2 class="sec-title">Featured Products</h2>
    <p class="sec-sub">Handpicked bestsellers loved by customers</p>
  </div>
  <div class="p-marquee">
    <div class="p-track">
      <?php foreach (marquee_items($featured) as $p): ?>
        <?= product_card_html($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="see-more-wrap">
    <a class="see-more-btn" href="<?= base_url('category.php') ?>">See More Products &rsaquo;</a>
  </div>
</section>
<?php endif; ?>

<?php if ($newArr): ?>
<section class="section">
  <div class="container">
    <h2 class="sec-title">New Arrivals</h2>
    <p class="sec-sub">Fresh stock, just landed in store</p>
  </div>
  <div class="p-marquee">
    <div class="p-track">
      <?php foreach (marquee_items($newArr) as $p): ?>
        <?= product_card_html($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="see-more-wrap">
    <a class="see-more-btn" href="<?= base_url('category.php') ?>">See More Products &rsaquo;</a>
  </div>
</section>
<?php endif; ?>

<?php if ($onSale): ?>
<section class="section">
  <div class="container">
    <h2 class="sec-title">On Sale</h2>
    <p class="sec-sub">Big discounts for a limited time</p>
  </div>
  <div class="p-marquee">
    <div class="p-track">
      <?php foreach (marquee_items($onSale) as $p): ?>
        <?= product_card_html($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="see-more-wrap">
    <a class="see-more-btn" href="<?= base_url('category.php') ?>">See More Products &rsaquo;</a>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="search-cta">
      <h2>Can't find what you're looking for?</h2>
      <p>Search thousands of products in our store</p>
      <form class="search-form search-cta-form" action="<?= base_url('search.php') ?>" method="get">
        <input type="text" name="q" placeholder="Search products..." required>
        <button type="submit">Search</button>
      </form>
    </div>
  </div>
</section>

<?php
/* Customer testimonials slider (above footer) — safe if table not migrated yet */
$testimonials = [];
try {
    $testimonials = db()->query("SELECT * FROM testimonials WHERE status = 'active' ORDER BY sort_order ASC, id DESC")->fetchAll();
} catch (Exception $ex) { $testimonials = []; }
?>
<?php if ($testimonials): ?>
<section class="section testi-section">
  <div class="container">
    <h2 class="sec-title">What Our Customers Say</h2>
    <p class="sec-sub">Real reviews from happy customers across Pakistan</p>
  </div>
  <div class="testi-marquee">
    <div class="testi-track">
      <?php foreach (marquee_items($testimonials, 8) as $t): ?>
        <div class="testi-card">
          <div class="testi-stars"><?= str_repeat('★', (int)$t['rating']) ?><span><?= str_repeat('★', 5 - (int)$t['rating']) ?></span></div>
          <p class="testi-text">"<?= e($t['review_text']) ?>"</p>
          <div class="testi-user">
            <?php if (!empty($t['photo'])): ?>
              <img src="<?= e(img_url($t['photo'])) ?>" alt="<?= e($t['name']) ?>">
            <?php else: ?>
              <span class="testi-avatar"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span>
            <?php endif; ?>
            <div>
              <strong><?= e($t['name']) ?></strong>
              <small>✓ Verified Buyer</small>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
