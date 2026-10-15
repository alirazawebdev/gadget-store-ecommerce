<?php
/**
 * My Orders — customers see all their orders, live status, and copyable order numbers.
 * No login needed: orders are remembered in a cookie at checkout,
 * or looked up by phone number.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'My Orders';
$pageDesc  = 'View all your orders and their live status.';
$pageKeys  = '';

$phoneInput = trim($_GET['phone'] ?? '');
$ids = [];
if (!empty($_COOKIE['my_orders'])) {
    $dec = json_decode((string)$_COOKIE['my_orders'], true);
    if (is_array($dec)) $ids = array_values(array_unique(array_map('intval', $dec)));
}

/* phone lookup: find all orders for this phone number (digits-normalized) */
if ($phoneInput !== '') {
    $digits = preg_replace('/\D/', '', $phoneInput);
    if ($digits !== '') {
        try {
            $st = db()->prepare(
                "SELECT id FROM orders
                 WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '(', ''), ')', '') = ?
                 ORDER BY id DESC LIMIT 50"
            );
            $st->execute([$digits]);
            $found = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            $ids = array_values(array_unique(array_merge($found, $ids)));
            /* remember them so they show up next visit without re-entering */
            $ids = array_slice($ids, 0, 50);
            setcookie('my_orders', json_encode($ids), time() + 365 * 86400, '/');
        } catch (Exception $ex) { /* ignore */ }
    }
}

$orders = [];
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    try {
        $orders = db()->query("SELECT * FROM orders WHERE id IN ($in) ORDER BY id DESC")->fetchAll();
        $itemsByOrder = [];
        if ($orders) {
            $oIds = implode(',', array_map(function ($o) { return (int)$o['id']; }, $orders));
            foreach (db()->query("SELECT * FROM order_items WHERE order_id IN ($oIds)") as $it) {
                $itemsByOrder[(int)$it['order_id']][] = $it;
            }
        }
    } catch (Exception $ex) { $orders = []; }
}

/* status timeline steps */
$steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing',
          'dispatched' => 'Dispatched', 'delivered' => 'Delivered'];
$stepKeys = array_keys($steps);

include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h2 class="sec-title">My Orders</h2>
  <p class="sec-sub">Track all your orders and their live status — updated automatically</p>

  <?php if (!$orders): ?>
    <div class="myo-empty">
      <div class="myo-empty-icon">📦</div>
      <h3>No orders found</h3>
      <?php if ($phoneInput !== ''): ?>
        <p>No orders found for <b><?= e($phoneInput) ?></b>. Please check the number and try again.</p>
      <?php else: ?>
        <p>Orders you place on this device appear here automatically — no need to enter anything.</p>
      <?php endif; ?>
      <a class="btn" href="<?= base_url('category.php') ?>" style="margin-top:14px">Start Shopping</a>
      <details class="myo-find" <?= ($phoneInput !== '' && !$orders) ? 'open' : '' ?>>
        <summary>Looking for orders placed on another device?</summary>
        <form class="myo-phone-form" method="get" action="<?= base_url('my-orders.php') ?>">
          <input type="tel" name="phone" placeholder="03XX-XXXXXXX" value="<?= e($phoneInput) ?>" required>
          <button class="btn" type="submit">Find My Orders</button>
        </form>
      </details>
    </div>
  <?php else: ?>
    <form class="myo-phone-form myo-phone-inline" method="get" action="<?= base_url('my-orders.php') ?>">
      <input type="tel" name="phone" placeholder="Find orders by another phone number" value="<?= e($phoneInput) ?>">
      <button class="btn btn-outline" type="submit">Search</button>
    </form>

    <?php foreach ($orders as $o): ?>
      <?php
        $items = $itemsByOrder[(int)$o['id']] ?? [];
        $st = $o['status'];
        $isBad = in_array($st, ['cancelled', 'returned'], true);
        $curIdx = array_search($st, $stepKeys, true);
        if ($curIdx === false) $curIdx = 0;
      ?>
      <div class="myo-card">
        <div class="myo-head">
          <div>
            <div class="myo-ordno">
              <span>Order <b><?= e($o['order_no']) ?></b></span>
              <button type="button" class="copy-btn" data-copy="<?= e($o['order_no']) ?>" title="Copy order number">📋 Copy</button>
            </div>
            <div class="myo-date">Placed on <?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?> &middot; <?= count($items) ?> item(s)</div>
          </div>
          <div class="myo-total">
            <small>Total</small>
            <b><?= format_price($o['total']) ?></b>
            <?= status_badge($st) ?>
            <small class="myo-pay"><?= e((pay_methods()[$o['payment_method']]['label'] ?? 'COD') . ' · ' . pay_status_label($o['payment_status'] ?? 'unpaid')) ?></small>
          </div>
        </div>

        <?php if ($isBad): ?>
          <div class="myo-cancelled">This order was <b><?= e($st) ?></b>. Contact us if you have any questions.</div>
        <?php else: ?>
          <div class="myo-steps">
            <?php foreach ($stepKeys as $i => $k): ?>
              <div class="myo-step <?= $i < $curIdx ? 'done' : ($i === $curIdx ? 'current' : '') ?>">
                <span class="myo-dot"><?= $i < $curIdx ? '✓' : ($i + 1) ?></span>
                <span class="myo-lbl"><?= e($steps[$k]) ?></span>
                <?php if ($i < count($stepKeys) - 1): ?><span class="myo-line"></span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="myo-items">
          <?php foreach ($items as $it): ?>
            <div class="myo-item">
              <span class="myo-iname"><?= e($it['product_name']) ?> <small>× <?= (int)$it['qty'] ?></small></span>
              <span class="myo-iprice"><?= format_price($it['total']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="myo-foot">
          <span>🚚 <?= $o['shipping'] > 0 ? 'Shipping: ' . format_price($o['shipping']) : 'Free shipping' ?></span>
          <span>💵 Cash on Delivery</span>
          <a href="<?= base_url('track-order.php') ?>">Need help? Contact us</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
