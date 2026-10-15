<?php
/**
 * Product detail page — gallery, price, stock note, add-to-cart, reviews, related.
 */
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$st = db()->prepare("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.status = 'active'");
$st->execute([$slug]);
$p = $st->fetch();

if (!$p) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    $pageDesc = '';
    $pageKeys = '';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="container section">
      <h1 class="page-title">Product not found</h1>
      <p>The product you are looking for is no longer available.</p>
      <a class="btn" href="<?= base_url() ?>">Back to Home</a>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* review submission (POST-redirect-GET) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'review') {
    $rname    = trim($_POST['name'] ?? '');
    $rrating  = (int)($_POST['rating'] ?? 0);
    $rcomment = trim($_POST['comment'] ?? '');
    if ($rname === '' || $rrating < 1 || $rrating > 5 || $rcomment === '') {
        flash('error', 'Please fill all fields and select a rating.');
    } else {
        db()->prepare("INSERT INTO reviews (product_id, name, rating, comment, status) VALUES (?, ?, ?, ?, 'pending')")
          ->execute([$p['id'], $rname, $rrating, $rcomment]);
        flash('success', 'Thank you! Your review will appear after approval.');
    }
    redirect(product_url($p));
}

/* view counter */
db()->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$p['id']]);

$pageTitle = $p['meta_title'] ?: $p['name'];
$pageDesc  = $p['meta_description'] ?: '';
$pageKeys  = $p['meta_keywords'] ?: '';

$imgs  = product_images($p['id']);
$disc  = discount_percent($p);
$stock = (int)$p['stock'];
$lowAt = (int)$p['low_stock_alert'];

$rst = db()->prepare("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC");
$rst->execute([$p['id']]);
$reviews = $rst->fetchAll();
$avg = 0;
if ($reviews) {
    $sum = 0;
    foreach ($reviews as $r) $sum += (int)$r['rating'];
    $avg = round($sum / count($reviews), 1);
}

$rel = db()->prepare("SELECT * FROM products WHERE status = 'active' AND category_id = ? AND id != ? ORDER BY id DESC LIMIT 4");
$rel->execute([$p['category_id'], $p['id']]);
$related = $rel->fetchAll();

include __DIR__ . '/includes/header.php';

$shortDesc = trim(mb_substr(strip_tags($p['description'] ?? ''), 0, 200));
$featLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($p['description'] ?? '')))));
$feats = array_slice($featLines, 0, 5);
$saveAmt = max(0, (float)$p['price'] - (float)sale_price($p));
$freeAbove = (float)setting('free_shipping_above', 0);
?>
<div class="container section">
  <nav class="breadcrumb">
    <a href="<?= base_url() ?>">Home</a> /
    <?php if (!empty($p['cat_name'])): ?><a href="<?= base_url('category.php?slug=' . urlencode($p['cat_slug'])) ?>"><?= e($p['cat_name']) ?></a> /<?php endif; ?>
    <span><?= e(mb_substr($p['name'], 0, 60)) ?></span>
  </nav>

  <div class="shop-pd">
    <div class="shop-gal">
      <div class="g-main">
        <?php if ($disc > 0): ?><span class="g-badge">-<?= (int)$disc ?>%</span><?php endif; ?>
        <div class="g-smartbadges">
          <?php foreach (boost_badges($p) as $bdg): ?><span class="smart-badge <?= $bdg[0] ?>"><?= e($bdg[1]) ?></span><?php endforeach; ?>
        </div>
        <img id="pdMainImg" src="<?= e($imgs ? img_url($imgs[0]) : product_thumb($p)) ?>" alt="<?= e($p['name']) ?>">
        <?php if (count($imgs) > 1): ?>
        <button type="button" class="g-arrow g-prev" id="galPrev" aria-label="Previous">&#8249;</button>
        <button type="button" class="g-arrow g-next" id="galNext" aria-label="Next">&#8250;</button>
        <?php endif; ?>
      </div>
      <?php if (count($imgs) > 1): ?>
      <div class="g-thumbs">
        <?php foreach ($imgs as $i => $im): ?>
        <img src="<?= e(img_url($im)) ?>" class="<?= $i === 0 ? 'active' : '' ?>" alt="<?= e($p['name']) ?>">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="shop-info">
      <h1><?= e($p['name']) ?></h1>
      <div class="shop-rating">
        <span class="stars"><?= str_repeat('&#9733;', (int)round($avg)) ?><?= str_repeat('&#9734;', 5 - (int)round($avg)) ?></span>
        <b><?= e($avg) ?></b>
        <a href="#shop-tabs">(<?= count($reviews) ?> reviews)</a>
        <?php if ($stock > 0): ?><span class="stk-ok">In Stock</span><?php else: ?><span class="stk-no">Out of Stock</span><?php endif; ?>
      </div>
      <?php if (boost_on('boost_viewers') && $stock > 0): ?>
      <div class="boost-viewers"><span class="pulse-dot"></span><b><?= boost_viewers((int)$p['id']) ?></b>&nbsp;people are viewing this right now</div>
      <?php endif; ?>

      <div class="shop-price">
        <span class="now"><?= format_price(sale_price($p)) ?></span>
        <?php if ($disc > 0): ?>
        <span class="old"><?= format_price($p['price']) ?></span>
        <span class="save">Save <?= format_price($saveAmt) ?></span>
        <?php endif; ?>
      </div>
      <?php if (boost_on('boost_countdown') && sale_price($p) < (float)$p['price'] && !empty($p['flash_ends_at']) && strtotime($p['flash_ends_at']) > time()): ?>
      <div class="boost-countdown"><span class="bcd-label">⏳ Sale ends in</span><span class="bcd-timer" data-countdown="<?= e(date('Y-m-d\TH:i:s', strtotime($p['flash_ends_at']))) ?>"></span></div>
      <?php endif; ?>
      <p class="shop-incl">Inclusive of all taxes &middot; Cash on Delivery available</p>

      <?php if ($shortDesc !== ''): ?><p class="shop-short"><?= e($shortDesc) ?>&hellip;</p><?php endif; ?>

      <?php if ($feats): ?>
      <div class="shop-feats">
        <h4>About this item</h4>
        <ul>
          <?php foreach ($feats as $fl): ?><li><?= e(mb_substr($fl, 0, 140)) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <hr class="pz-hr">

      <?php if ($stock <= 0): ?>
        <button class="shop-add" disabled>Out of Stock</button>
      <?php else: ?>
        <?php
        $lowLimit = (int)$p['low_stock_alert'] > 0 ? (int)$p['low_stock_alert'] : boost_int('boost_lowstock_default', 5);
        $soldPct = ($stock > 0 && boost_on('boost_stockbar')) ? boost_sold_percent($p) : null;
        ?>
        <?php if (boost_on('boost_lowstock') && $stock <= $lowLimit): ?>
        <p class="pz-stock low boost-urgency"><span class="urgency-pulse">⚡</span> Only <?= (int)$stock ?> left in stock &mdash; order soon!</p>
        <?php endif; ?>
        <?php if ($soldPct !== null): ?>
        <div class="boost-stockbar">
          <div class="bsb-top"><span>🔥 Selling fast!</span><b><?= (int)$soldPct ?>% claimed</b></div>
          <div class="bsb-track"><div class="bsb-fill" style="width:<?= (int)$soldPct ?>%"></div></div>
        </div>
        <?php endif; ?>
        <form class="add-cart-form shop-buybox" method="post" action="<?= base_url('ajax/cart.php') ?>" data-checkout="<?= base_url('checkout.php') ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <div class="qty-row">
            <div class="qty-step"><button type="button" class="qty-minus">&minus;</button><input type="number" name="qty" value="1" min="1" max="<?= (int)$stock ?>"><button type="button" class="qty-plus">+</button></div>
            <button class="shop-add btn-add" type="submit">&#128722; Add to Cart</button>
          </div>
          <button class="shop-buy" type="submit" name="buy_now" value="1">&#9889; Buy Now</button>
        </form>
      <?php endif; ?>

      <div class="shop-perks">
        <div><span>&#128666;</span><b>Free Shipping</b><small><?= $freeAbove > 0 ? 'On orders over ' . format_price($freeAbove) : 'Nationwide delivery' ?></small></div>
        <div><span>&#128274;</span><b>Secure Payment</b><small>100% safe checkout</small></div>
        <div><span>&#8617;&#65039;</span><b>Easy Returns</b><small>7-day return policy</small></div>
      </div>

      <div class="pz-meta">
        <?php if (!empty($p['sku'])): ?><div><b>SKU:</b> <?= e($p['sku']) ?></div><?php endif; ?>
        <?php if (!empty($p['cat_name'])): ?><div><b>Category:</b> <a href="<?= base_url('category.php?slug=' . urlencode($p['cat_slug'])) ?>"><?= e($p['cat_name']) ?></a></div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="shop-tabs" id="shop-tabs">
    <div class="tab-btns">
      <button type="button" class="tab-btn active" data-tab="tab-desc">Description</button>
      <button type="button" class="tab-btn" data-tab="tab-rev">Reviews (<?= count($reviews) ?>)</button>
    </div>
    <div class="tab-panel active" id="tab-desc">
      <div class="pd-desc"><?= nl2br(e($p['description'])) ?></div>
    </div>
    <div class="tab-panel" id="tab-rev">
      <?php if ($reviews): ?>
        <?php foreach ($reviews as $r): ?>
        <div class="review">
          <b><?= e($r['name']) ?></b>
          <span class="stars"><?= str_repeat('&#9733;', max(1, min(5, (int)$r['rating']))) ?></span>
          <p><?= nl2br(e($r['comment'])) ?></p>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No reviews yet. Be the first to review this product!</p>
      <?php endif; ?>

      <h3 style="margin-top:18px">Write a Review</h3>
      <form method="post" class="review-form">
        <input type="hidden" name="form" value="review">
        <div class="form-group"><label>Name *</label><input class="form-control" type="text" name="name" required></div>
        <div class="form-group"><label>Rating *</label>
          <select class="form-control" name="rating" required>
            <option value="5">5 &#9733;</option>
            <option value="4">4 &#9733;</option>
            <option value="3">3 &#9733;</option>
            <option value="2">2 &#9733;</option>
            <option value="1">1 &#9733;</option>
          </select>
        </div>
        <div class="form-group"><label>Review *</label><textarea class="form-control" name="comment" rows="4" required></textarea></div>
        <button class="btn" type="submit">Submit Review</button>
      </form>
    </div>
  </div>

  <?php if ($related): ?>
  <h2 class="sec-title">You May Also Like</h2>
  <p class="sec-sub">Frequently bought together</p>
  <div class="product-grid">
    <?php foreach ($related as $rp): ?>
      <?= product_card_html($rp) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (boost_on('boost_stickybar') && $stock > 0): ?>
<div class="sticky-buybar" id="stickyBuybar">
  <img src="<?= e($imgs ? img_url($imgs[0]) : product_thumb($p)) ?>" alt="<?= e($p['name']) ?>">
  <div class="sbb-info">
    <strong><?= e(mb_substr($p['name'], 0, 48)) ?></strong>
    <span><?= format_price(sale_price($p)) ?></span>
  </div>
  <form class="add-cart-form" method="post" action="<?= base_url('ajax/cart.php') ?>">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <input type="hidden" name="qty" value="1">
    <button class="btn btn-add sbb-btn" type="submit">Add to Cart</button>
  </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
