<?php
/**
 * Orders list — filter by status, search, paginated.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Orders';
if (!can('orders')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$statuses = order_statuses();
$status = $_GET['status'] ?? '';
if ($status !== '' && !isset($statuses[$status])) $status = '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($status !== '') { $where[] = "o.status = ?"; $params[] = $status; }
if ($search !== '') {
    $where[] = "(o.order_no LIKE ? OR o.phone LIKE ? OR o.name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db()->prepare("SELECT COUNT(*) FROM orders o $whereSql");
$total->execute($params);
$total = (int)$total->fetchColumn();

$offset = ($page - 1) * $per;
$st = db()->prepare(
    "SELECT o.*, COUNT(oi.id) AS items_count
     FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
     $whereSql GROUP BY o.id ORDER BY o.created_at DESC LIMIT $per OFFSET $offset"
);
$st->execute($params);
$orders = $st->fetchAll();

$base = admin_url('orders.php') . '?' . http_build_query(array_filter(['status' => $status, 'q' => $search]));

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Orders (<?= $total ?>)</h1>
</div>

<div class="card">
  <form method="get" action="" style="display:flex;gap:10px;flex-wrap:wrap">
    <select name="status" class="form-control" style="width:auto">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $k => $v): ?>
      <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" class="form-control" style="width:260px" placeholder="Search order #, phone, name..."
           value="<?= e($search) ?>">
    <button class="btn" type="submit">Filter</button>
    <?php if ($status !== '' || $search !== ''): ?>
    <a class="btn btn-gray" href="<?= admin_url('orders.php') ?>">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if ($orders): ?>
  <table class="table">
    <tr><th>Order #</th><th>Customer</th><th>Phone</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><strong><?= e($o['order_no']) ?></strong></td>
      <td><?= e($o['name']) ?><br><small style="color:#6b7280"><?= e($o['city']) ?></small></td>
      <td><?= e($o['phone']) ?></td>
      <td><?= (int)$o['items_count'] ?></td>
      <td><?= format_price($o['total']) ?></td>
      <td><small><?= e(strtoupper($o['payment_method'] ?? 'cod')) ?><br><?= e(ucfirst($o['payment_status'] ?? 'unpaid')) ?></small></td>
      <td><?= status_badge($o['status']) ?></td>
      <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
      <td><a class="btn btn-sm" href="<?= admin_url('order-view.php?id=' . (int)$o['id']) ?>">View</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?= pagination($total, $per, $page, $base) ?>
  <?php else: ?>
  <p>No orders found.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
