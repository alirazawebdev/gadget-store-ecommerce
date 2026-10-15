<?php
/**
 * Order detail + status change (implements the stock business rule).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/payments.php';
$adminTitle = 'Order Detail';
if (!can('orders')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$id]);
$order = $st->fetch();
if (!$order) {
    redirect(admin_url('orders.php'));
}

$st = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
$st->execute([$id]);
$items = $st->fetchAll();

/* ---------- status history (order_events; needs erp-orders-migration.sql) ---------- */
$events = [];
try {
    $ev = db()->prepare("SELECT * FROM order_events WHERE order_id = ? ORDER BY id ASC");
    $ev->execute([$id]);
    $events = $ev->fetchAll();
    if (!$events) {
        /* baseline: one honest entry marking when tracking began (not backfilled fake history) */
        db()->prepare("INSERT INTO order_events (order_id, from_status, to_status, note, created_by) VALUES (?,?,?,?,?)")
          ->execute([$id, '', $order['status'], 'Order history tracking enabled', $admin['id']]);
        $ev->execute([$id]);
        $events = $ev->fetchAll();
    }
} catch (Exception $ex) { $events = []; }

/* ---------- manual payment verification (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_action'])) {
    $pa = $_POST['pay_action'];
    if ($pa === 'paid' && in_array($order['payment_status'] ?? '', ['pending', 'unpaid'], true)) {
        pay_mark_paid($id, $order['payment_method'] ?? 'manual', $order['transaction_ref'] ?? '');
        log_activity("Order {$order['order_no']} payment manually verified (paid)");
        flash('success', 'Payment marked as paid.');
    } elseif ($pa === 'failed' && ($order['payment_status'] ?? '') === 'pending') {
        pay_mark_failed($id, $order['payment_method'] ?? 'manual', 'Rejected by admin after manual verification');
        log_activity("Order {$order['order_no']} payment marked failed");
        flash('success', 'Payment marked as failed.');
    } else {
        flash('error', 'Payment action not allowed in the current state.');
    }
    redirect(admin_url('order-view.php?id=' . $id));
}

/* ---------- status change (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $new = $_POST['status'];
    $valid = array_keys(order_statuses());
    if (!in_array($new, $valid, true)) {
        flash('error', 'Invalid status.');
        redirect(admin_url('order-view.php?id=' . $id));
    }
    $old = $order['status'];
    if ($new !== $old) {
        /* STOCK RULE — decrement only when moving to confirmed from pending */
        if ($new === 'confirmed' && $old === 'pending') {
            foreach ($items as $it) {
                if (empty($it['product_id'])) continue; // skip if product missing
                $p = db()->prepare("SELECT id FROM products WHERE id = ?");
                $p->execute([$it['product_id']]);
                if (!$p->fetch()) continue;
                db()->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")
                  ->execute([(int)$it['qty'], $it['product_id']]);
                db()->prepare("INSERT INTO stock_log (product_id, change_qty, type, note, created_by) VALUES (?,?,?,?,?)")
                  ->execute([$it['product_id'], -(int)$it['qty'], 'out',
                             'Order ' . $order['order_no'] . ' confirmed', $admin['id']]);
            }
        }
        /* STOCK RULE — restore stock when cancelled/returned from a stocked state */
        elseif (in_array($new, ['cancelled', 'returned'], true)
             && in_array($old, ['confirmed', 'processing', 'dispatched'], true)) {
            foreach ($items as $it) {
                if (empty($it['product_id'])) continue;
                $p = db()->prepare("SELECT id FROM products WHERE id = ?");
                $p->execute([$it['product_id']]);
                if (!$p->fetch()) continue;
                db()->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
                  ->execute([(int)$it['qty'], $it['product_id']]);
                db()->prepare("INSERT INTO stock_log (product_id, change_qty, type, note, created_by) VALUES (?,?,?,?,?)")
                  ->execute([$it['product_id'], (int)$it['qty'], 'in',
                             'Order ' . $order['order_no'] . ' ' . $new . ' — stock restored', $admin['id']]);
            }
        }
        db()->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")
          ->execute([$new, $id]);
        /* record in status history (skipped silently if migration not run yet) */
        try {
            db()->prepare("INSERT INTO order_events (order_id, from_status, to_status, note, created_by) VALUES (?,?,?,?,?)")
              ->execute([$id, $old, $new, trim($_POST['note'] ?? ''), $admin['id']]);
        } catch (Exception $ex) { /* ignore */ }
        log_activity("Order {$order['order_no']} status changed: $old → $new");
        flash('success', "Order status updated to " . order_statuses()[$new] . ".");
    }
    redirect(admin_url('order-view.php?id=' . $id));
}

$adminTitle = 'Order ' . $order['order_no'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-head no-print">
  <h1>Order <?= e($order['order_no']) ?></h1>
  <div>
    <a class="btn btn-gray" href="<?= admin_url('invoice.php?id=' . $id . '&doc=invoice') ?>" target="_blank">🖨 Print Invoice</a>
    <a class="btn btn-gray" href="<?= admin_url('invoice.php?id=' . $id . '&doc=packing') ?>" target="_blank">📦 Packing Slip</a>
    <a class="btn btn-gray" href="<?= admin_url('orders.php') ?>">← Back</a>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>Customer Info</h3>
    <div class="kv"><b>Name</b><span><?= e($order['name']) ?></span></div>
    <div class="kv"><b>Phone</b><span><?= e($order['phone']) ?></span></div>
    <div class="kv"><b>Email</b><span><?= e($order['email']) ?: '—' ?></span></div>
    <div class="kv"><b>Address</b><span><?= e($order['address']) ?></span></div>
    <div class="kv"><b>City</b><span><?= e($order['city']) ?></span></div>
    <div class="kv"><b>Notes</b><span><?= e($order['notes']) ?: '—' ?></span></div>
    <div class="kv"><b>Payment</b><span><?= e(strtoupper($order['payment_method'])) ?> — <?= e(function_exists('pay_status_label') ? pay_status_label($order['payment_status'] ?? 'unpaid') : '') ?></span></div>
    <?php if (!empty($order['transaction_ref'])): ?>
    <div class="kv"><b>Transaction Ref</b><span><?= e($order['transaction_ref']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($order['wallet_number'])): ?>
    <div class="kv"><b>Wallet Number</b><span><?= e($order['wallet_number']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($order['cod_fee'])): ?>
    <div class="kv"><b>COD Fee</b><span><?= e(format_price($order['cod_fee'])) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($order['payment_proof'])): ?>
    <div class="kv"><b>Payment Screenshot</b>
      <span>
        <a href="<?= base_url($order['payment_proof']) ?>" target="_blank">
          <img src="<?= base_url($order['payment_proof']) ?>" alt="Payment screenshot" style="max-width:240px;border:1px solid #e5e7eb;border-radius:8px;display:block">
        </a>
        <small style="color:#6b7280">Click the image to view full size</small>
      </span>
    </div>
    <?php endif; ?>
    <?php if (in_array($order['payment_status'] ?? '', ['pending', 'unpaid'], true)): ?>
    <div class="kv"><b>Verify Payment</b>
      <span>
        <form method="post" action="" style="display:inline" onsubmit="return confirm('Mark this payment as PAID? Only do this after you have actually received the money.')">
          <input type="hidden" name="pay_action" value="paid">
          <button class="btn btn-green btn-sm" type="submit">✓ Mark as Paid</button>
        </form>
        <?php if (($order['payment_status'] ?? '') === 'pending'): ?>
        <form method="post" action="" style="display:inline;margin-left:6px" onsubmit="return confirm('Mark this payment as FAILED?')">
          <input type="hidden" name="pay_action" value="failed">
          <button class="btn btn-red btn-sm" type="submit">✕ Mark as Failed</button>
        </form>
        <?php endif; ?>
      </span>
    </div>
    <?php endif; ?>
    <div class="kv"><b>Placed</b><span><?= e(date('M j, Y h:i A', strtotime($order['created_at']))) ?></span></div>
  </div>
  <div class="card no-print">
    <h3>Change Status</h3>
    <p>Current: <?= status_badge($order['status']) ?></p>
    <form method="post" action="" style="margin-top:10px">
      <div class="form-group">
        <label>New status</label>
        <select name="status" class="form-control">
          <?php foreach (order_statuses() as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Note (optional)</label>
        <input type="text" name="note" class="form-control" maxlength="255" placeholder="e.g. Called customer, confirmed address">
      </div>
      <button class="btn" type="submit">Update Status</button>
    </form>
    <p style="font-size:12px;color:#6b7280;margin-top:10px">
      Stock decreases only when an order moves <b>Pending → Confirmed</b>.
      Cancelling/returning a confirmed order restores the stock automatically.
    </p>
  </div>
</div>

<style>
.oe-timeline{position:relative;margin:6px 0 0;padding-left:22px}
.oe-timeline::before{content:"";position:absolute;left:7px;top:6px;bottom:6px;width:2px;background:#e5e7eb}
.oe-item{position:relative;padding:0 0 18px}
.oe-item:last-child{padding-bottom:2px}
.oe-dot{position:absolute;left:-21px;top:3px;width:12px;height:12px;border-radius:50%;background:#f97316;border:2px solid #fff;box-shadow:0 0 0 2px #f97316}
.oe-title{font-weight:700;color:#111;font-size:14px}
.oe-from{font-weight:500;color:#6b7280;font-size:12px}
.oe-note{color:#374151;font-size:13px;margin-top:2px}
.oe-time{color:#9ca3af;font-size:12px;margin-top:2px}
</style>

<div class="grid-2">
  <div class="card">
    <h3>Order Items</h3>
    <table class="table">
      <tr><th>#</th><th>Product</th><th style="text-align:right">Price</th><th style="text-align:right">Qty</th><th style="text-align:right">Total</th></tr>
      <?php $n = 1; foreach ($items as $it): ?>
      <tr>
        <td><?= $n++ ?></td>
        <td><?= e($it['product_name']) ?></td>
        <td style="text-align:right"><?= format_price($it['price']) ?></td>
        <td style="text-align:right"><?= (int)$it['qty'] ?></td>
        <td style="text-align:right"><?= format_price($it['total']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr><td colspan="4" style="text-align:right">Subtotal</td><td style="text-align:right"><?= format_price($order['subtotal']) ?></td></tr>
      <?php if ((float)$order['discount'] > 0): ?>
      <tr><td colspan="4" style="text-align:right">Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></td><td style="text-align:right">− <?= format_price($order['discount']) ?></td></tr>
      <?php endif; ?>
      <tr><td colspan="4" style="text-align:right">Shipping</td><td style="text-align:right"><?= (float)$order['shipping'] > 0 ? format_price($order['shipping']) : 'FREE' ?></td></tr>
      <tr><td colspan="4" style="text-align:right"><strong>Grand Total</strong></td><td style="text-align:right"><strong><?= format_price($order['total']) ?></strong></td></tr>
    </table>
  </div>
  <div class="card">
    <h3>Status History</h3>
    <?php if ($events): ?>
    <div class="oe-timeline">
      <?php foreach ($events as $ev): ?>
      <div class="oe-item">
        <div class="oe-dot"></div>
        <div class="oe-title"><?= e(order_statuses()[$ev['to_status']] ?? $ev['to_status']) ?>
          <?php if ($ev['from_status'] !== ''): ?><span class="oe-from">from <?= e(order_statuses()[$ev['from_status']] ?? $ev['from_status']) ?></span><?php endif; ?>
        </div>
        <?php if ($ev['note'] !== ''): ?><div class="oe-note"><?= e($ev['note']) ?></div><?php endif; ?>
        <div class="oe-time"><?= e(date('M j, Y h:i A', strtotime($ev['created_at']))) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:#6b7280;font-size:13px">History tracking needs <b>erp-orders-migration.sql</b> run once in phpMyAdmin.</p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
