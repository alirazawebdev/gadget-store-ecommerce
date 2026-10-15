<?php
/**
 * Stock movement log — filterable.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Stock Log';
if (!can('reports')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$type = $_GET['type'] ?? '';
if (!in_array($type, ['in', 'out', 'adjust'], true)) $type = '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;

$where = [];
$params = [];
if ($type !== '') { $where[] = "s.type = ?"; $params[] = $type; }
if ($q !== '') { $where[] = "p.name LIKE ?"; $params[] = "%$q%"; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$join = "FROM stock_log s LEFT JOIN products p ON p.id = s.product_id LEFT JOIN users u ON u.id = s.created_by";
$total = db()->prepare("SELECT COUNT(*) $join $whereSql");
$total->execute($params);
$total = (int)$total->fetchColumn();

$offset = ($page - 1) * $per;
$st = db()->prepare(
    "SELECT s.*, p.name AS product_name, u.name AS by_name $join
     $whereSql ORDER BY s.created_at DESC, s.id DESC LIMIT $per OFFSET $offset"
);
$st->execute($params);
$logs = $st->fetchAll();

$base = admin_url('stock-log.php') . '?' . http_build_query(array_filter(['type' => $type, 'q' => $q]));
$badge = ['in' => 'approved', 'out' => 'cancelled', 'adjust' => 'pending'];

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Stock Log (<?= $total ?>)</h1>
</div>

<div class="card">
  <form method="get" action="" style="display:flex;gap:10px;flex-wrap:wrap">
    <select name="type" class="form-control" style="width:auto">
      <option value="">All types</option>
      <option value="in" <?= $type === 'in' ? 'selected' : '' ?>>Stock In</option>
      <option value="out" <?= $type === 'out' ? 'selected' : '' ?>>Stock Out</option>
      <option value="adjust" <?= $type === 'adjust' ? 'selected' : '' ?>>Adjust</option>
    </select>
    <input type="text" name="q" class="form-control" style="width:240px" placeholder="Search product..."
           value="<?= e($q) ?>">
    <button class="btn" type="submit">Filter</button>
    <?php if ($type !== '' || $q !== ''): ?>
    <a class="btn btn-gray" href="<?= admin_url('stock-log.php') ?>">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if ($logs): ?>
  <table class="table">
    <tr><th>Date</th><th>Product</th><th>Type</th><th style="text-align:right">Qty</th><th>Note</th><th>By</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= e(date('M j, Y h:i A', strtotime($l['created_at']))) ?></td>
      <td><?= e($l['product_name']) ?: '<small style="color:#6b7280">deleted product #' . (int)$l['product_id'] . '</small>' ?></td>
      <td><span class="badge badge-<?= $badge[$l['type']] ?? 'read' ?>"><?= e(ucfirst($l['type'])) ?></span></td>
      <td style="text-align:right"><strong><?= (int)$l['change_qty'] > 0 ? '+' : '' ?><?= (int)$l['change_qty'] ?></strong></td>
      <td><?= e($l['note']) ?></td>
      <td><?= e($l['by_name']) ?: '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?= pagination($total, $per, $page, $base) ?>
  <?php else: ?>
  <p>No stock movements yet.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
