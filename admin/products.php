<?php
/**
 * Products list — search, category filter, paginated. Delete via POST.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Products';
if (!can('products')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- delete (POST only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    $st = db()->prepare("SELECT name FROM products WHERE id = ?");
    $st->execute([$did]);
    $p = $st->fetch();
    if ($p) {
        db()->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$did]);
        db()->prepare("DELETE FROM products WHERE id = ?")->execute([$did]);
        log_activity("Deleted product: {$p['name']} (id $did)");
        flash('success', 'Product deleted.');
    }
    redirect(admin_url('products.php'));
}

$search = trim($_GET['q'] ?? '');
$catId = (int)($_GET['cat'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($catId > 0) { $where[] = "p.category_id = ?"; $params[] = $catId; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db()->prepare("SELECT COUNT(*) FROM products p $whereSql");
$total->execute($params);
$total = (int)$total->fetchColumn();

$offset = ($page - 1) * $per;
$st = db()->prepare(
    "SELECT p.*, c.name AS cat_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSql ORDER BY p.id DESC LIMIT $per OFFSET $offset"
);
$st->execute($params);
$products = $st->fetchAll();

$cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$base = admin_url('products.php') . '?' . http_build_query(array_filter(['q' => $search, 'cat' => $catId]));

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Products (<?= $total ?>)</h1>
  <a class="btn" href="<?= admin_url('product-form.php') ?>">+ Add Product</a>
</div>

<div class="card">
  <form method="get" action="" style="display:flex;gap:10px;flex-wrap:wrap">
    <input type="text" name="q" class="form-control" style="width:260px" placeholder="Search name or SKU..."
           value="<?= e($search) ?>">
    <select name="cat" class="form-control" style="width:auto">
      <option value="0">All categories</option>
      <?php foreach ($cats as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
    <?php if ($search !== '' || $catId > 0): ?>
    <a class="btn btn-gray" href="<?= admin_url('products.php') ?>">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if ($products): ?>
  <table class="table">
    <tr><th></th><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Flags</th><th>Status</th><th></th></tr>
    <?php foreach ($products as $p): ?>
    <?php $isLow = (int)$p['stock'] <= (int)$p['low_stock_alert']; ?>
    <tr>
      <td><img class="thumb" src="<?= e(product_thumb($p)) ?>" alt=""></td>
      <td><strong><?= e($p['name']) ?></strong></td>
      <td><?= e($p['sku']) ?: '—' ?></td>
      <td><?= e($p['cat_name']) ?: '—' ?></td>
      <td>
        <?= format_price(sale_price($p)) ?>
        <?php if (discount_percent($p) > 0): ?><br><small style="color:#6b7280"><s><?= format_price($p['price']) ?></s></small><?php endif; ?>
      </td>
      <td><strong style="<?= $isLow ? 'color:#dc2626' : '' ?>"><?= (int)$p['stock'] ?></strong></td>
      <td>
        <?php if ($p['featured']): ?><span class="badge badge-confirmed">Feat</span><?php endif; ?>
        <?php if ($p['is_new']): ?><span class="badge badge-new">New</span><?php endif; ?>
        <?php if ($p['on_sale']): ?><span class="badge badge-pending">Sale</span><?php endif; ?>
        <?php if ($p['flash_sale']): ?><span class="badge badge-cancelled">Flash</span><?php endif; ?>
      </td>
      <td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
      <td style="white-space:nowrap">
        <a class="btn btn-sm" href="<?= admin_url('product-form.php?id=' . (int)$p['id']) ?>">Edit</a>
        <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this product?')">
          <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?= pagination($total, $per, $page, $base) ?>
  <?php else: ?>
  <p>No products found.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
