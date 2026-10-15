<?php
/**
 * Customer detail + their orders.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Customer';
if (!can('customers')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM customers WHERE id = ?");
$st->execute([$id]);
$c = $st->fetch();
if (!$c) redirect(admin_url('customers.php'));

$orders = db()->prepare(
    "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
     FROM orders o WHERE o.customer_id = ? ORDER BY o.created_at DESC"
);
$orders->execute([$id]);
$orders = $orders->fetchAll();

$adminTitle = 'Customer: ' . $c['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1><?= e($c['name']) ?>
    <?php if ((int)$c['total_orders'] > 1): ?><span class="badge badge-confirmed">Repeat Customer</span><?php endif; ?>
  </h1>
  <a class="btn btn-gray" href="<?= admin_url('customers.php') ?>">← Back</a>
</div>

<div class="card">
  <h3>Info</h3>
  <div class="kv"><b>Phone</b><span><?= e($c['phone']) ?></span></div>
  <div class="kv"><b>Email</b><span><?= e($c['email']) ?: '—' ?></span></div>
  <div class="kv"><b>Address</b><span><?= e($c['address']) ?: '—' ?></span></div>
  <div class="kv"><b>City</b><span><?= e($c['city']) ?: '—' ?></span></div>
  <div class="kv"><b>Total Orders</b><span><?= (int)$c['total_orders'] ?></span></div>
  <div class="kv"><b>Total Spent</b><span><?= format_price($c['total_spent']) ?></span></div>
  <div class="kv"><b>Customer Since</b><span><?= e(date('M j, Y', strtotime($c['created_at']))) ?></span></div>
</div>

<div class="card">
  <h3>Order History (<?= count($orders) ?>)</h3>
  <?php if ($orders): ?>
  <table class="table">
    <tr><th>Order #</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><strong><?= e($o['order_no']) ?></strong></td>
      <td><?= (int)$o['items_count'] ?></td>
      <td><?= format_price($o['total']) ?></td>
      <td><?= status_badge($o['status']) ?></td>
      <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
      <td><a class="btn btn-sm" href="<?= admin_url('order-view.php?id=' . (int)$o['id']) ?>">View</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>No orders yet.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
