<?php
/**
 * payment-pending.php — confirmation screen for MANUAL online payments.
 *
 * The customer already transferred the money from their own app and submitted
 * the transaction ID at checkout. This page shows the receiving details again
 * and explains that the team verifies every payment manually before dispatch.
 * There is nothing to poll — no provider API is involved.
 */
require_once __DIR__ . '/includes/payments.php';

$o = trim($_GET['o'] ?? '');
$st = db()->prepare("SELECT * FROM orders WHERE order_no = ?");
$st->execute([$o]);
$order = $st->fetch();
if (!$order || !pay_is_manual($order['payment_method'] ?? '')) redirect(base_url());

if (($order['payment_status'] ?? '') === 'paid') redirect(base_url('order-success.php?o=' . urlencode($o)));

$all = pay_methods();
$info = $all[$order['payment_method']] ?? ['label' => ucfirst($order['payment_method']), 'icon' => '💳'];
$d = pay_method_details($order['payment_method']);

$waText = "Assalam-o-Alaikum, I placed order {$order['order_no']} of " . format_price($order['total']) .
          ". My transaction ID is {$order['transaction_ref']}. Please verify my payment.";
$waLink = pay_whatsapp_link($d['whatsapp'] ?? '', $waText);

$pageTitle = 'Payment Received — Verification Pending';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:560px">
  <div class="pay-pending-box">
    <div class="pp-ico"><?= $info['icon'] ?></div>
    <h1>Order received!</h1>
    <p>Your order <b><?= e($order['order_no']) ?></b> of <b><?= format_price($order['total']) ?></b> is placed.
       Our team will <b>manually verify</b> your <?= e($info['label']) ?> payment and then dispatch your order.</p>

    <?php if (pay_method_configured($order['payment_method'])): ?>
    <div style="text-align:left;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:16px;margin:18px 0">
      <div class="pay-detail"><b>Amount sent</b><span class="copyable"><?= format_price($order['total']) ?></span></div>
      <?php if ($order['payment_method'] === 'bank' && ($d['bank_name'] ?? '') !== ''): ?>
      <div class="pay-detail"><b>Bank</b><span><?= e($d['bank_name']) ?></span></div>
      <?php endif; ?>
      <div class="pay-detail"><b>Account title</b><span><?= e($d['title'] !== '' ? $d['title'] : '—') ?></span></div>
      <div class="pay-detail"><b><?= $order['payment_method'] === 'bank' ? 'Account no / IBAN' : 'Account number' ?></b><span class="copyable"><?= e($d['number']) ?></span></div>
      <div class="pay-detail"><b>Your transaction ID</b><span class="copyable"><?= e($order['transaction_ref']) ?></span></div>
    </div>
    <?php endif; ?>

    <ol class="pp-steps">
      <li>If you haven't sent the payment yet, transfer <b><?= format_price($order['total']) ?></b> to the account above.</li>
      <li>We verify every transaction ID manually (usually within a few hours).</li>
      <li>Once verified, your order is dispatched — track it anytime below.</li>
    </ol>

    <?php if ($waLink): ?>
    <p><a class="pay-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener">💬 Confirm on WhatsApp</a></p>
    <p class="pp-note">Tap to send your order details to us on WhatsApp for faster verification.</p>
    <?php endif; ?>

    <p class="pp-note">Order No: <b><?= e($order['order_no']) ?></b></p>
    <p style="margin-top:14px"><a href="<?= base_url('my-orders.php') ?>">View My Orders</a> · <a href="<?= base_url('track-order.php') ?>">Track Order</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
