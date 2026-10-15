<?php
/**
 * Add / edit product (with multiple image upload).
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Products';
if (!can('products')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$editId = (int)($_GET['id'] ?? 0);
$product = null;
if ($editId > 0) {
    $st = db()->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$editId]);
    $product = $st->fetch();
    if (!$product) redirect(admin_url('products.php'));
}
$isEdit = $product !== null;

$cats = db()->query("SELECT id, name FROM categories ORDER BY sort_order, name")->fetchAll();

$existingImages = [];
if ($isEdit) {
    $st = db()->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
    $st->execute([$editId]);
    $existingImages = $st->fetchAll();
}

/* ---------- save (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $sku           = trim($_POST['sku'] ?? '');
    $category_id   = (int)($_POST['category_id'] ?? 0) ?: null;
    $description   = trim($_POST['description'] ?? '');
    $price         = (float)($_POST['price'] ?? 0);
    $sale_price    = (float)($_POST['sale_price'] ?? 0);
    $purchase_price= (float)($_POST['purchase_price'] ?? 0);
    $stock         = (int)($_POST['stock'] ?? 0);
    $low_alert     = (int)($_POST['low_stock_alert'] ?? 5);
    $featured      = isset($_POST['featured']) ? 1 : 0;
    $is_new        = isset($_POST['is_new']) ? 1 : 0;
    $on_sale       = isset($_POST['on_sale']) ? 1 : 0;
    $flash_sale    = isset($_POST['flash_sale']) ? 1 : 0;
    $flash_raw     = trim($_POST['flash_ends_at'] ?? '');
    $flash_ends_at = ($flash_raw !== '' && strtotime($flash_raw)) ? date('Y-m-d H:i:s', strtotime($flash_raw)) : null;
    $status        = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $meta_title    = trim($_POST['meta_title'] ?? '');
    $meta_desc     = trim($_POST['meta_description'] ?? '');
    $meta_keys     = trim($_POST['meta_keywords'] ?? '');

    if ($name === '') {
        flash('error', 'Product name is required.');
        redirect(admin_url('product-form.php' . ($isEdit ? '?id=' . $editId : '')));
    }

    if ($isEdit) {
        $slug = ($name !== $product['name']) ? unique_slug('products', $name, $editId) : $product['slug'];
        db()->prepare(
            "UPDATE products SET category_id=?, name=?, slug=?, sku=?, description=?, price=?, sale_price=?,
             purchase_price=?, stock=?, low_stock_alert=?, featured=?, is_new=?, on_sale=?, flash_sale=?,
             flash_ends_at=?, status=?, meta_title=?, meta_description=?, meta_keywords=? WHERE id=?"
        )->execute([$category_id, $name, $slug, $sku, $description, $price, $sale_price, $purchase_price,
                    $stock, $low_alert, $featured, $is_new, $on_sale, $flash_sale, $flash_ends_at,
                    $status, $meta_title, $meta_desc, $meta_keys, $editId]);
        $pid = $editId;
        log_activity("Updated product: $name (id $pid)");
    } else {
        $slug = unique_slug('products', $name);
        db()->prepare(
            "INSERT INTO products (category_id, name, slug, sku, description, price, sale_price, purchase_price,
             stock, low_stock_alert, featured, is_new, on_sale, flash_sale, flash_ends_at, status,
             meta_title, meta_description, meta_keywords)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([$category_id, $name, $slug, $sku, $description, $price, $sale_price, $purchase_price,
                    $stock, $low_alert, $featured, $is_new, $on_sale, $flash_sale, $flash_ends_at,
                    $status, $meta_title, $meta_desc, $meta_keys]);
        $pid = (int)db()->lastInsertId();
        log_activity("Added product: $name (id $pid)");
    }

    /* delete checked existing images */
    foreach ($_POST['del_images'] ?? [] as $imgId) {
        $imgId = (int)$imgId;
        $st = db()->prepare("SELECT image FROM product_images WHERE id = ? AND product_id = ?");
        $st->execute([$imgId, $pid]);
        if ($row = $st->fetch()) {
            $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($row['image'], '/');
            if (is_file($f)) @unlink($f);
            db()->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);
        }
    }

    /* multiple image upload */
    if (!empty($_FILES['images']['name'][0])) {
        $maxSort = db()->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?");
        $maxSort->execute([$pid]);
        $sort = (int)$maxSort->fetchColumn() + 1;
        $files = $_FILES['images'];
        $n = count($files['name']);
        for ($i = 0; $i < $n; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $one = [
                'name' => $files['name'][$i], 'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
            $path = upload_image($one, 'products');
            if ($path) {
                db()->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?,?,?)")
                  ->execute([$pid, $path, $sort++]);
            }
        }
    }

    flash('success', $isEdit ? 'Product updated.' : 'Product added.');
    redirect(admin_url('product-form.php?id=' . $pid));
}

$adminTitle = $isEdit ? 'Edit Product' : 'Add Product';
$v = function ($k, $d = '') use ($product) {
    return $product ? $product[$k] : $d;
};
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h1>
  <a class="btn btn-gray" href="<?= admin_url('products.php') ?>">← Back</a>
</div>

<form method="post" action="" enctype="multipart/form-data">
<div class="grid-2">
  <div class="card">
    <h3>Basic Info</h3>
    <div class="form-group">
      <label>Product Name *</label>
      <input type="text" name="name" class="form-control" required value="<?= e($v('name')) ?>">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>SKU</label>
        <input type="text" name="sku" class="form-control" value="<?= e($v('sku')) ?>">
      </div>
      <div class="form-group">
        <label>Category</label>
        <select name="category_id" class="form-control">
          <option value="0">— None —</option>
          <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$v('category_id') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Description</label>
      <textarea name="description" class="form-control"><?= e($v('description')) ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="form-group">
        <label>Flash Sale Ends At</label>
        <input type="datetime-local" name="flash_ends_at" class="form-control"
               value="<?= $v('flash_ends_at') ? e(date('Y-m-d\TH:i', strtotime($v('flash_ends_at')))) : '' ?>">
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Pricing & Stock</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Price (Rs.) *</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e($v('price', '0')) ?>">
      </div>
      <div class="form-group">
        <label>Sale Price (Rs.)</label>
        <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="<?= e($v('sale_price', '0')) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Purchase Price (Rs.) <small style="color:#6b7280">for profit reports</small></label>
        <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" value="<?= e($v('purchase_price', '0')) ?>">
      </div>
      <div class="form-group">
        <label>Stock Qty</label>
        <input type="number" min="0" name="stock" class="form-control" value="<?= e($v('stock', '0')) ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Low Stock Alert At</label>
      <input type="number" min="0" name="low_stock_alert" class="form-control" value="<?= e($v('low_stock_alert', '5')) ?>">
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="featured" <?= $v('featured') ? 'checked' : '' ?>> Featured</label><br>
      <label><input type="checkbox" name="is_new" <?= $v('is_new') ? 'checked' : '' ?>> New Arrival</label><br>
      <label><input type="checkbox" name="on_sale" <?= $v('on_sale') ? 'checked' : '' ?>> On Sale</label><br>
      <label><input type="checkbox" name="flash_sale" <?= $v('flash_sale') ? 'checked' : '' ?>> Flash Sale</label>
    </div>
  </div>
</div>

<div class="card">
  <h3>Images</h3>
  <?php if ($existingImages): ?>
  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <?php foreach ($existingImages as $im): ?>
    <div style="text-align:center">
      <img src="<?= e(img_url($im['image'])) ?>" style="width:90px;height:90px;object-fit:cover;border-radius:6px"><br>
      <label style="font-size:12px"><input type="checkbox" name="del_images[]" value="<?= (int)$im['id'] ?>"> delete</label>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="form-group">
    <label>Upload New Images (multiple allowed)</label>
    <input type="file" name="images[]" class="form-control" multiple accept="image/*">
  </div>
</div>

<div class="card">
  <h3>SEO</h3>
  <div class="form-group">
    <label>Meta Title</label>
    <input type="text" name="meta_title" class="form-control" value="<?= e($v('meta_title')) ?>">
  </div>
  <div class="form-group">
    <label>Meta Description</label>
    <textarea name="meta_description" class="form-control" style="min-height:70px"><?= e($v('meta_description')) ?></textarea>
  </div>
  <div class="form-group">
    <label>Meta Keywords</label>
    <input type="text" name="meta_keywords" class="form-control" value="<?= e($v('meta_keywords')) ?>">
  </div>
</div>

<button class="btn" type="submit"><?= $isEdit ? 'Save Changes' : 'Add Product' ?></button>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
