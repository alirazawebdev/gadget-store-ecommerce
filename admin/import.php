<?php
/**
 * CSV product import — upload, preview, then confirm import.
 * Columns: name,sku,category_slug,price,sale_price,purchase_price,stock,description,status
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Import Products';
if (!can('products')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- step 1: upload & parse ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    if (!empty($_FILES['csv']['tmp_name']) && ($_FILES['csv']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($fh);
        $header = array_map(function ($h) { return strtolower(trim((string)$h)); }, (array)$header);
        if (!in_array('name', $header, true)) {
            fclose($fh);
            flash('error', 'Invalid CSV: header row must include a "name" column.');
            redirect(admin_url('import.php'));
        }
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            $hasVal = false;
            foreach ($line as $v) { if (trim((string)$v) !== '') { $hasVal = true; break; } }
            if (!$hasVal) continue;
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = trim((string)($line[$i] ?? ''));
            }
            if ($row['name'] === '') continue; // skip rows without name
            $rows[] = $row;
        }
        fclose($fh);
        $_SESSION['csv_import'] = $rows;
        flash('success', count($rows) . ' valid row(s) found. Review and confirm below.');
        redirect(admin_url('import.php?preview=1'));
    }
    flash('error', 'Please choose a CSV file to upload.');
    redirect(admin_url('import.php'));
}

/* ---------- step 2: confirm import ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $rows = $_SESSION['csv_import'] ?? [];
    $catMap = [];
    foreach (db()->query("SELECT id, slug FROM categories") as $c) {
        $catMap[$c['slug']] = (int)$c['id'];
    }
    $added = 0;
    $skipped = 0;
    foreach ($rows as $row) {
        $name = trim($row['name'] ?? '');
        if ($name === '') { $skipped++; continue; }
        $catSlug = trim($row['category_slug'] ?? '');
        $catId = ($catSlug !== '' && isset($catMap[$catSlug])) ? $catMap[$catSlug] : null;
        $status = strtolower(trim($row['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';
        $slug = unique_slug('products', $name);
        db()->prepare(
            "INSERT INTO products (category_id, name, slug, sku, description, price, sale_price,
             purchase_price, stock, status) VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $catId, $name, $slug, $row['sku'] ?? '', $row['description'] ?? '',
            (float)($row['price'] ?? 0), (float)($row['sale_price'] ?? 0),
            (float)($row['purchase_price'] ?? 0), (int)($row['stock'] ?? 0), $status,
        ]);
        $added++;
    }
    unset($_SESSION['csv_import']);
    log_activity("CSV import: $added products added, $skipped skipped");
    flash('success', "Import complete: $added product(s) added, $skipped skipped.");
    redirect(admin_url('products.php'));
}

/* ---------- cancel preview ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_import'])) {
    unset($_SESSION['csv_import']);
    redirect(admin_url('import.php'));
}

$preview = isset($_GET['preview']) ? ($_SESSION['csv_import'] ?? []) : [];
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Import Products (CSV)</h1>
  <a class="btn btn-gray" href="<?= admin_url('products.php') ?>">← Back</a>
</div>

<?php if ($preview): ?>
<div class="card">
  <h3>Preview — <?= count($preview) ?> row(s) ready</h3>
  <table class="table">
    <tr><th>Name</th><th>SKU</th><th>Category Slug</th><th>Price</th><th>Stock</th></tr>
    <?php foreach (array_slice($preview, 0, 10) as $r): ?>
    <tr>
      <td><?= e($r['name'] ?? '') ?></td>
      <td><?= e($r['sku'] ?? '') ?></td>
      <td><?= e($r['category_slug'] ?? '') ?></td>
      <td><?= e($r['price'] ?? '') ?></td>
      <td><?= e($r['stock'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (count($preview) > 10): ?>
  <p style="color:#6b7280;font-size:13px">…and <?= count($preview) - 10 ?> more row(s).</p>
  <?php endif; ?>
  <div style="margin-top:14px;display:flex;gap:10px">
    <form method="post" action="">
      <button class="btn" type="submit" name="confirm_import" value="1">✓ Confirm Import</button>
    </form>
    <form method="post" action="">
      <button class="btn btn-gray" type="submit" name="cancel_import" value="1">Cancel</button>
    </form>
  </div>
</div>
<?php else: ?>
<div class="card">
  <h3>Upload CSV File</h3>
  <p style="font-size:13px;color:#6b7280;margin-bottom:12px">
    Expected header columns: <code>name,sku,category_slug,price,sale_price,purchase_price,stock,description,status</code><br>
    • <code>name</code> is required — rows without a name are skipped.<br>
    • <code>category_slug</code> must match an existing category slug, otherwise the product is imported without a category.<br>
    • <code>status</code> can be <code>active</code> or <code>inactive</code> (default active).
  </p>
  <form method="post" action="" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end">
    <div class="form-group" style="margin:0">
      <label>CSV File</label>
      <input type="file" name="csv" class="form-control" accept=".csv" required>
    </div>
    <button class="btn" type="submit" name="upload_csv" value="1">Upload & Preview</button>
  </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
