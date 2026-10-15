<?php
/**
 * Product search.
 */
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$pageTitle = $q !== '' ? 'Search results for "' . $q . '"' : 'Search';
$pageDesc  = '';
$pageKeys  = '';

$products = [];
$total = 0;
$page = 1;
$per = ITEMS_PER_PAGE;
$base = '';
if ($q !== '') {
    $like = '%' . $q . '%';
    $st = db()->prepare("SELECT COUNT(*) FROM products WHERE status = 'active' AND (name LIKE ? OR description LIKE ?)");
    $st->execute([$like, $like]);
    $total = (int)$st->fetchColumn();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $per;
    $st = db()->prepare("SELECT * FROM products WHERE status = 'active' AND (name LIKE ? OR description LIKE ?) ORDER BY id DESC LIMIT " . (int)$per . " OFFSET " . (int)$offset);
    $st->execute([$like, $like]);
    $products = $st->fetchAll();
    $base = 'search.php?q=' . urlencode($q);
}

include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title">Search<?= $q !== '' ? ': ' . e($q) : '' ?></h1>
  <?php if ($q === ''): ?>
    <p>Enter a search term in the search box above.</p>
  <?php elseif (!$products): ?>
    <p>No products found for &ldquo;<?= e($q) ?>&rdquo;.</p>
  <?php else: ?>
    <p><?= (int)$total ?> result(s) found.</p>
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
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
