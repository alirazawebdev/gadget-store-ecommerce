<?php
/**
 * Order tracking — order no + phone lookup with status timeline.
 */
require_once __DIR__ . '/includes/functions.php';

$order = null;
$searched = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    $o  = trim($_POST['order_no'] ?? '');
    $ph = trim($_POST['phone'] ?? '');
    $st = db()->prepare("SELECT * FROM orders WHERE order_no = ? AND phone = ?");
    $st->execute([$o, $ph]);
    $order = $st->fetch();
    if (!$order) flash('error', 'Order not found. Please check your order number and phone number.');
}

$pageTitle = 'Track Your Order';
$pageDesc  = '';
$pageKeys  = '';

include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title">Track Your Order</h1>
  <form method="post" class="track-form">
    <div class="form-group"><label>Order Number</label><input class="form-control" type="text" name="order_no" placeholder="e.g. QG-1001" required></div>
    <div class="form-group"><label>Phone Number</label><input class="form-control" type="text" name="phone" required></div>
    <button class="btn" type="submit">Track</button>
  </form>

  <?php if ($searched && $order): ?>
  <div class="track-result">
    <p>Order No: <b><?= e($order['order_no']) ?></b></p>
    <p>Total: <b><?= format_price($order['total']) ?></b></p>
    <p>Status: <?= status_badge($order['status']) ?></p>
    <?php if (in_array($order['status'], ['cancelled', 'returned'], true)): ?>
      <div class="alert alert-error">This order was <?= e($order['status']) ?>. Please contact us for details.</div>
    <?php else: ?>
      <?php
      $steps = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered'];
      $keys = array_keys($steps);
      $cur = array_search($order['status'], $keys, true);
      ?>
      <div class="timeline">
        <?php foreach ($steps as $k => $label): ?>
        <div class="t-step<?= array_search($k, $keys, true) <= $cur ? ' done' : '' ?>">
          <span class="t-dot"></span><span><?= e($label) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
