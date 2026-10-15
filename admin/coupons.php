<?php
/**
 * Discount coupons management.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Coupons';
if (!can('coupons')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = db()->prepare("SELECT * FROM coupons WHERE id = ?");
    $st->execute([$editId]);
    $edit = $st->fetch();
    if (!$edit) redirect(admin_url('coupons.php'));
}

/* ---------- save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    $cid         = (int)($_POST['id'] ?? 0);
    $code        = strtoupper(trim($_POST['code'] ?? ''));
    $type        = ($_POST['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $value       = (float)($_POST['value'] ?? 0);
    $min_order   = (float)($_POST['min_order'] ?? 0);
    $usage_limit = (int)($_POST['usage_limit'] ?? 0);
    $expires_raw = trim($_POST['expires_at'] ?? '');
    $expires_at  = ($expires_raw !== '' && strtotime($expires_raw)) ? date('Y-m-d', strtotime($expires_raw)) : null;
    $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($code === '' || $value <= 0) {
        flash('error', 'Coupon code and a value greater than 0 are required.');
        redirect(admin_url('coupons.php' . ($cid ? '?edit=' . $cid : '')));
    }
    $dup = db()->prepare("SELECT id FROM coupons WHERE code = ?" . ($cid ? " AND id != $cid" : ""));
    $dup->execute([$code]);
    if ($dup->fetch()) {
        flash('error', 'This coupon code already exists.');
        redirect(admin_url('coupons.php' . ($cid ? '?edit=' . $cid : '')));
    }

    if ($cid > 0) {
        db()->prepare("UPDATE coupons SET code=?, type=?, value=?, min_order=?, usage_limit=?, expires_at=?, status=? WHERE id=?")
          ->execute([$code, $type, $value, $min_order, $usage_limit, $expires_at, $status, $cid]);
        log_activity("Updated coupon: $code (id $cid)");
        flash('success', 'Coupon updated.');
    } else {
        db()->prepare("INSERT INTO coupons (code, type, value, min_order, usage_limit, used_count, expires_at, status) VALUES (?,?,?,?,?,0,?,?)")
          ->execute([$code, $type, $value, $min_order, $usage_limit, $expires_at, $status]);
        log_activity("Added coupon: $code");
        flash('success', 'Coupon added.');
    }
    redirect(admin_url('coupons.php'));
}

/* ---------- delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    $st = db()->prepare("SELECT code FROM coupons WHERE id = ?");
    $st->execute([$did]);
    if ($row = $st->fetch()) {
        db()->prepare("DELETE FROM coupons WHERE id = ?")->execute([$did]);
        log_activity("Deleted coupon: {$row['code']} (id $did)");
        flash('success', 'Coupon deleted.');
    }
    redirect(admin_url('coupons.php'));
}

$coupons = db()->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();
$v = function ($k, $d = '') use ($edit) {
    return $edit ? $edit[$k] : $d;
};
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Discount Coupons</h1>
  <?php if ($edit): ?><a class="btn btn-gray" href="<?= admin_url('coupons.php') ?>">+ New Coupon</a><?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h3><?= $edit ? 'Edit Coupon' : 'Add Coupon' ?></h3>
    <form method="post" action="">
      <input type="hidden" name="save_coupon" value="1">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div class="form-row">
        <div class="form-group">
          <label>Code *</label>
          <input type="text" name="code" class="form-control" required style="text-transform:uppercase"
                 value="<?= e($v('code')) ?>" placeholder="SAVE10">
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type" class="form-control">
            <option value="percent" <?= $v('type', 'percent') === 'percent' ? 'selected' : '' ?>>Percent %</option>
            <option value="fixed" <?= $v('type') === 'fixed' ? 'selected' : '' ?>>Fixed Rs.</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Value *</label>
          <input type="number" step="0.01" min="0.01" name="value" class="form-control" required value="<?= e($v('value', '10')) ?>">
        </div>
        <div class="form-group">
          <label>Min. Order (Rs.)</label>
          <input type="number" step="0.01" min="0" name="min_order" class="form-control" value="<?= e($v('min_order', '0')) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Usage Limit <small style="color:#6b7280">(0 = unlimited)</small></label>
          <input type="number" min="0" name="usage_limit" class="form-control" value="<?= e($v('usage_limit', '0')) ?>">
        </div>
        <div class="form-group">
          <label>Expires At</label>
          <input type="date" name="expires_at" class="form-control" value="<?= e($v('expires_at')) ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <button class="btn" type="submit"><?= $edit ? 'Save Changes' : 'Add Coupon' ?></button>
    </form>
  </div>

  <div class="card">
    <h3>All Coupons</h3>
    <?php if ($coupons): ?>
    <table class="table">
      <tr><th>Code</th><th>Value</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr>
      <?php foreach ($coupons as $c): ?>
      <tr>
        <td><strong><?= e($c['code']) ?></strong></td>
        <td><?= $c['type'] === 'percent' ? e($c['value']) . '%' : format_price($c['value']) ?></td>
        <td><?= (int)$c['used_count'] ?><?= (int)$c['usage_limit'] > 0 ? '/' . (int)$c['usage_limit'] : '' ?></td>
        <td><?= e($c['expires_at']) ?: '—' ?></td>
        <td><span class="badge badge-<?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm" href="<?= admin_url('coupons.php?edit=' . (int)$c['id']) ?>">Edit</a>
          <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this coupon?')">
            <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No coupons yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
