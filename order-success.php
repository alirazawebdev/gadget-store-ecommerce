<?php
require_once __DIR__ . '/includes/payments.php';

$o = trim($_GET['o'] ?? '');
$st = db()->prepare("SELECT * FROM orders WHERE order_no = ?");
$st->execute([$o]);
$order = $st->fetch();
if (!$order) redirect(base_url());

$pmLabel = pay_methods()[$order['payment_method']]['label'] ?? ucfirst($order['payment_method']);
$payLabel = pay_status_label($order['payment_status'] ?? 'unpaid');

$pageTitle = 'Order Placed Successfully';
$pageDesc  = '';
$pageKeys  = '';

include __DIR__ . '/includes/header.php';
?>
<div class="container section success-page">
  <h1>&#127881; Thank You, <?= e($order['name']) ?>!</h1>
  <p>Your order has been placed successfully.</p>
  <div class="order-box">
    <p>Order No: <b><?= e($order['order_no']) ?></b></p>
    <p>Total: <b><?= format_price($order['total']) ?></b></p>
    <p>Payment: <b><?= e($pmLabel) ?> (<?= e($payLabel) ?>)</b></p>
    <?php if (!empty($order['transaction_ref'])): ?>
    <p>Transaction ID: <b><?= e($order['transaction_ref']) ?></b></p>
    <?php endif; ?>
    <p>Status: <?= status_badge($order['status']) ?></p>
  </div>
  <?php if (($order['payment_status'] ?? '') === 'unpaid' && $order['payment_method'] === 'cod'): ?>
  <p>Our team will call you shortly to confirm your order. Please keep <?= format_price($order['total']) ?> ready in cash.</p>
  <?php elseif (($order['payment_status'] ?? '') === 'paid'): ?>
  <p>Your payment was received successfully. Thank you for shopping with us!</p>
  <?php elseif (($order['payment_status'] ?? '') === 'pending'): ?>
  <p>We've received your order and payment details. Our team will verify your payment manually and dispatch your order soon.</p>
  <?php else: ?>
  <p>Our team will call you shortly to confirm your order.</p>
  <?php endif; ?>
  <a class="btn" href="<?= base_url('my-orders.php') ?>">View My Orders</a>
  <a class="btn" href="<?= base_url('track-order.php') ?>">Track Your Order</a>
  <a class="btn" href="<?= base_url() ?>">Continue Shopping</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
