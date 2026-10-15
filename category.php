<?php
/**
 * Category / All-products listing with sorting + pagination.
 */
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$cat = null;
if ($slug !== '') {
    $st = db()->prepare("SELECT * FROM categories WHERE slug = ? AND status = 'active'");
    $st->execute([$slug]);
    $cat = $st->fetch();
    if (!$cat) redirect(base_url());
    $pageTitle = $cat['meta_title'] ?: $cat['name'];
    $pageDesc  = $cat['meta_description'] ?: '';
    $pageKeys  = $cat['meta_keywords'] ?: '';
    $heading   = $cat['name'];
} else {
    $pageTitle = 'All Products';
    $pageDesc  = '';
    $pageKeys  = '';
    $heading   = 'All Products';
}

$sort = $_GET['sort'] ?? 'newest';
if (!in_array($sort, ['price_asc', 'price_desc', 'newest'], true)) $sort = 'newest';
$orderBy = 'p.id DESC';
$effPrice = '(CASE WHEN p.sale_price > 0 THEN p.sale_price ELSE p.price END)';
if ($sort === 'price_asc')  $orderBy = "$effPrice ASC";
if ($sort === 'price_desc') $orderBy = "$effPrice DESC";

/* price filters (Shop Under badges) */
$priceMin = (isset($_GET['price_min']) && $_GET['price_min'] !== '') ? max(0, (float)$_GET['price_min']) : null;
$priceMax = (isset($_GET['price_max']) && $_GET['price_max'] !== '') ? max(0, (float)$_GET['price_max']) : null;

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = ITEMS_PER_PAGE;
$params = [];
$where = "p.status = 'active'";
if ($cat) {
    $where .= " AND p.category_id = ?";
    $params[] = (int)$cat['id'];
}
if ($priceMin !== null) { $where .= " AND $effPrice >= ?"; $params[] = $priceMin; }
if ($priceMax !== null) { $where .= " AND $effPrice <= ?"; $params[] = $priceMax; }

if (!$cat && $priceMax !== null && $priceMin === null) {
    $heading   = 'Shop Under ' . format_price($priceMax);
    $pageTitle = $heading;
}

$st = db()->prepare("SELECT COUNT(*) FROM products p WHERE $where");
$st->execute($params);
$total = (int)$st->fetchColumn();

$offset = ($page - 1) * $per;
$st = db()->prepare("SELECT p.* FROM products p WHERE $where ORDER BY $orderBy LIMIT " . (int)$per . " OFFSET " . (int)$offset);
$st->execute($params);
$products = $st->fetchAll();

$qp = [];
if ($cat) $qp['slug'] = $cat['slug'];
$qp['sort'] = $sort;
if ($priceMin !== null) $qp['price_min'] = $priceMin;
if ($priceMax !== null) $qp['price_max'] = $priceMax;
$base = 'category.php?' . http_build_query($qp);
$clearUrl = base_url('category.php' . ($cat ? '?slug=' . urlencode($cat['slug']) : ''));

include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title"><?= e($heading) ?></h1>
  <?php if ($cat && $cat['description']): ?>
  <p class="cat-desc"><?= e($cat['description']) ?></p>
  <?php endif; ?>

  <?php if ($priceMin !== null || $priceMax !== null): ?>
  <div class="price-filter-bar">
    <span>
      Showing products
      <?php if ($priceMin !== null): ?>from <b><?= format_price($priceMin) ?></b><?php endif; ?>
      <?php if ($priceMax !== null): ?>under <b><?= format_price($priceMax) ?></b><?php endif; ?>
    </span>
    <a href="<?= $clearUrl ?>">Clear filter &#10005;</a>
  </div>
  <?php endif; ?>

  <div class="toolbar">
    <span><?= (int)$total ?> product(s)</span>
    <form method="get" class="sort-form">
      <?php if ($cat): ?><input type="hidden" name="slug" value="<?= e($cat['slug']) ?>"><?php endif; ?>
      <?php if ($priceMin !== null): ?><input type="hidden" name="price_min" value="<?= e($priceMin) ?>"><?php endif; ?>
      <?php if ($priceMax !== null): ?><input type="hidden" name="price_max" value="<?= e($priceMax) ?>"><?php endif; ?>
      <select name="sort" onchange="this.form.submit()">
        <option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest</option>
        <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Price: Low to High</option>
        <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Price: High to Low</option>
      </select>
    </form>
  </div>

  <?php if ($products): ?>
  <div class="product-grid">
    <?php foreach ($products as $p): ?>
    <?php $disc = discount_percent($p); ?>
    <div class="product-card">
      <a href="<?= product_url($p) ?>"><img src="<?= e(product_thumb($p)) ?>" alt="<?= e($p['name']) ?>"></a>
      <?php if ($disc > 0): ?><span class="sale-badge">-<?= (int)$disc ?>%</span><?php endif; ?>
      <div class="p-info">
        <h3><a href="<?= product_url($p) ?>"><?= e($p['name']) ?></a></h3>
        <div class="p-price">
          <?php if ($disc > 0): ?><span class="old"><?= format_price($p['price']) ?></span><?php endif; ?>
          <span class="new"><?= format_price(sale_price($p)) ?></span>
        </div>
        <form class="add-cart-form" method="post" action="<?= base_url('ajax/cart.php') ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <div class="qty-step"><button type="button" class="qty-minus">&minus;</button><input type="number" name="qty" value="1" min="1"><button type="button" class="qty-plus">+</button></div>
          <button class="btn btn-add" type="submit">Add to Cart</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?= pagination($total, $per, $page, $base) ?>
  <?php else: ?>
  <p>No products found.</p>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
