<?php
/**
 * Categories — list + inline add/edit. Delete blocked if products exist.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Categories';
if (!can('categories')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$editCat = null;
if ($editId > 0) {
    $st = db()->prepare("SELECT * FROM categories WHERE id = ?");
    $st->execute([$editId]);
    $editCat = $st->fetch();
    if (!$editCat) redirect(admin_url('categories.php'));
}

/* ---------- save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cat'])) {
    $cid         = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $meta_title  = trim($_POST['meta_title'] ?? '');
    $meta_desc   = trim($_POST['meta_description'] ?? '');
    $meta_keys   = trim($_POST['meta_keywords'] ?? '');

    if ($name === '') {
        flash('error', 'Category name is required.');
        redirect(admin_url('categories.php' . ($cid ? '?edit=' . $cid : '')));
    }

    $image = null;
    if ($cid > 0) {
        $old = db()->prepare("SELECT image, slug, name FROM categories WHERE id = ?");
        $old->execute([$cid]);
        $oldRow = $old->fetch();
        $image = $oldRow['image'];
        $slug = ($name !== $oldRow['name']) ? unique_slug('categories', $name, $cid) : $oldRow['slug'];
    } else {
        $slug = unique_slug('categories', $name);
    }
    $up = upload_image($_FILES['image'] ?? [], 'categories');
    if ($up) {
        if (!empty($image)) {
            $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($image, '/');
            if (is_file($f)) @unlink($f);
        }
        $image = $up;
    }

    if ($cid > 0) {
        db()->prepare("UPDATE categories SET name=?, slug=?, image=?, description=?, sort_order=?, status=?, meta_title=?, meta_description=?, meta_keywords=? WHERE id=?")
          ->execute([$name, $slug, $image, $description, $sort_order, $status, $meta_title, $meta_desc, $meta_keys, $cid]);
        log_activity("Updated category: $name (id $cid)");
        flash('success', 'Category updated.');
    } else {
        db()->prepare("INSERT INTO categories (name, slug, image, description, sort_order, status, meta_title, meta_description, meta_keywords) VALUES (?,?,?,?,?,?,?,?,?)")
          ->execute([$name, $slug, $image, $description, $sort_order, $status, $meta_title, $meta_desc, $meta_keys]);
        log_activity("Added category: $name");
        flash('success', 'Category added.');
    }
    redirect(admin_url('categories.php'));
}

/* ---------- delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    $cnt = db()->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $cnt->execute([$did]);
    if ((int)$cnt->fetchColumn() > 0) {
        flash('error', 'Cannot delete: products exist in this category. Move them first.');
    } else {
        $st = db()->prepare("SELECT name, image FROM categories WHERE id = ?");
        $st->execute([$did]);
        if ($row = $st->fetch()) {
            if (!empty($row['image'])) {
                $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($row['image'], '/');
                if (is_file($f)) @unlink($f);
            }
            db()->prepare("DELETE FROM categories WHERE id = ?")->execute([$did]);
            log_activity("Deleted category: {$row['name']} (id $did)");
            flash('success', 'Category deleted.');
        }
    }
    redirect(admin_url('categories.php'));
}

$cats = db()->query(
    "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS pcount
     FROM categories c ORDER BY c.sort_order, c.name"
)->fetchAll();

$v = function ($k, $d = '') use ($editCat) {
    return $editCat ? $editCat[$k] : $d;
};
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Categories</h1>
  <?php if ($editCat): ?><a class="btn btn-gray" href="<?= admin_url('categories.php') ?>">+ New Category</a><?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h3><?= $editCat ? 'Edit Category' : 'Add Category' ?></h3>
    <form method="post" action="" enctype="multipart/form-data">
      <input type="hidden" name="save_cat" value="1">
      <input type="hidden" name="id" value="<?= $editCat ? (int)$editCat['id'] : 0 ?>">
      <div class="form-group">
        <label>Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= e($v('name')) ?>">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" style="min-height:70px"><?= e($v('description')) ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= e($v('sort_order', '0')) ?>">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Image</label>
        <?php if ($editCat && $editCat['image']): ?>
        <img src="<?= e(img_url($editCat['image'])) ?>" style="width:90px;height:90px;object-fit:cover;border-radius:6px;display:block;margin-bottom:8px">
        <?php endif; ?>
        <input type="file" name="image" class="form-control" accept="image/*">
      </div>
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
      <button class="btn" type="submit"><?= $editCat ? 'Save Changes' : 'Add Category' ?></button>
    </form>
  </div>

  <div class="card">
    <h3>All Categories</h3>
    <?php if ($cats): ?>
    <table class="table">
      <tr><th></th><th>Name</th><th>Products</th><th>Order</th><th>Status</th><th></th></tr>
      <?php foreach ($cats as $c): ?>
      <tr>
        <td><?php if ($c['image']): ?><img class="thumb" src="<?= e(img_url($c['image'])) ?>" alt=""><?php endif; ?></td>
        <td><strong><?= e($c['name']) ?></strong><br><small style="color:#6b7280">/<?= e($c['slug']) ?></small></td>
        <td><?= (int)$c['pcount'] ?></td>
        <td><?= (int)$c['sort_order'] ?></td>
        <td><span class="badge badge-<?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm" href="<?= admin_url('categories.php?edit=' . (int)$c['id']) ?>">Edit</a>
          <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this category?')">
            <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No categories yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
