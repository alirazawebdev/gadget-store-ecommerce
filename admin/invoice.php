<?php
/**
 * Printable Invoice / Packing Slip (standalone, print-optimized).
 * Usage: invoice.php?id=123&doc=invoice  |  invoice.php?id=123&doc=packing
 */
require_once __DIR__ . '/includes/auth.php';
if (!can('orders')) die('Permission denied.');

$id  = (int)($_GET['id'] ?? 0);
$doc = (($_GET['doc'] ?? 'invoice') === 'packing') ? 'packing' : 'invoice';

$st = db()->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$id]);
$order = $st->fetch();
if (!$order) die('Order not found.');

$st = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
$st->execute([$id]);
$items = $st->fetchAll();

$coName  = site_name();
$coPhone = setting('contact_phone');
$coEmail = setting('contact_email');
$coAddr  = setting('contact_address');
$logo    = setting('site_logo') ? img_url(setting('site_logo')) : '';

/* Amount in words (Pakistani numbering: crore / lakh). */
function amount_in_words($num) {
    $num = (int)round($num);
    if ($num === 0) return 'Zero';
    $w = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten',
          'Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $t = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $two = function ($n) use ($w, $t) {
        if ($n < 20) return $w[$n];
        return $t[(int)($n / 10)] . ($n % 10 ? ' ' . $w[$n % 10] : '');
    };
    $three = function ($n) use ($w, $two) {
        $s = '';
        if ($n >= 100) { $s .= $w[(int)($n / 100)] . ' Hundred'; $n %= 100; }
        if ($n > 0) $s .= ($s ? ' ' : '') . $two($n);
        return $s;
    };
    $parts = [];
    $cr = (int)($num / 10000000); $num %= 10000000;
    $lk = (int)($num / 100000);   $num %= 100000;
    $th = (int)($num / 1000);     $num %= 1000;
    if ($cr) $parts[] = $three($cr) . ' Crore';
    if ($lk) $parts[] = $two($lk) . ' Lakh';
    if ($th) $parts[] = $two($th) . ' Thousand';
    if ($num) $parts[] = $three($num);
    return implode(' ', $parts);
}

$title = $doc === 'packing' ? 'PACKING SLIP' : 'INVOICE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title . ' — ' . $order['order_no']) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;background:#f3f4f6;font-size:14px;line-height:1.5}
  .sheet{max-width:800px;margin:24px auto;background:#fff;box-shadow:0 2px 14px rgba(0,0,0,.1)}
  .doc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:28px 32px;border-bottom:4px solid #f97316}
  .co{display:flex;gap:14px;align-items:center}
  .co img{height:56px;width:56px;object-fit:contain}
  .co h1{font-size:22px;color:#f97316}
  .co p{font-size:12px;color:#555}
  .doc-title{text-align:right}
  .doc-title h2{font-size:26px;letter-spacing:2px;color:#111}
  .doc-title p{font-size:12px;color:#555}
  .meta{display:flex;justify-content:space-between;gap:16px;padding:20px 32px;background:#fafafa;border-bottom:1px solid #eee}
  .meta h4{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px}
  .meta p{font-size:13px}
  table.items{width:100%;border-collapse:collapse}
  table.items th{background:#1a1a1a;color:#fff;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px;text-align:left}
  table.items th.r,table.items td.r{text-align:right}
  table.items td{padding:10px 12px;border-bottom:1px solid #eee;font-size:13px;vertical-align:top}
  .totals{padding:16px 32px;display:flex;justify-content:flex-end}
  .totals table{width:300px;border-collapse:collapse;font-size:13px}
  .totals td{padding:6px 8px}
  .totals td:last-child{text-align:right}
  .grand td{font-size:16px;font-weight:800;border-top:2px solid #1a1a1a;color:#f97316}
  .words{padding:0 32px 8px;font-size:12px;color:#555}
  .foot{padding:20px 32px 28px;display:flex;justify-content:space-between;align-items:flex-end;gap:16px}
  .terms{font-size:11px;color:#777;max-width:60%}
  .sign{text-align:center;font-size:12px;color:#555}
  .sign .line{width:180px;border-top:1px solid #999;margin:44px auto 6px}
  .toolbar{max-width:800px;margin:0 auto;padding:14px 0;display:flex;gap:10px}
  .toolbar button{padding:10px 22px;border:none;border-radius:8px;background:#f97316;color:#fff;font-weight:700;cursor:pointer;font-size:14px}
  .toolbar a{padding:10px 18px;color:#555;text-decoration:none;font-size:14px;align-self:center}
  @page{size:A4;margin:10mm}
  @media print{
    body{background:#fff}
    .no-print{display:none!important}
    .sheet{margin:0;box-shadow:none;max-width:none}
  }
</style>
</head>
<body>

<div class="toolbar no-print">
  <button onclick="window.print()">🖨 Print</button>
  <a href="<?= admin_url('order-view.php?id=' . $id) ?>">← Back to order</a>
</div>

<div class="sheet">
  <div class="doc-head">
    <div class="co">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="logo"><?php endif; ?>
      <div>
        <h1><?= e($coName) ?></h1>
        <p><?= e($coAddr) ?><br><?= e($coPhone) ?><?php if ($coEmail): ?> · <?= e($coEmail) ?><?php endif; ?></p>
      </div>
    </div>
    <div class="doc-title">
      <h2><?= $title ?></h2>
      <p><b><?= e($order['order_no']) ?></b><br><?= e(date('M j, Y', strtotime($order['created_at']))) ?></p>
    </div>
  </div>

  <div class="meta">
    <div>
      <h4>Bill / Ship To</h4>
      <p><b><?= e($order['name']) ?></b><br>
      <?= e($order['address']) ?><br>
      <?= e($order['city']) ?><br>
      <?= e($order['phone']) ?><?php if ($order['email']): ?><br><?= e($order['email']) ?><?php endif; ?></p>
    </div>
    <div style="text-align:right">
      <h4>Order Info</h4>
      <p>Payment: <b><?= e(strtoupper($order['payment_method'])) ?></b> (<?= e(ucfirst($order['payment_status'] ?? 'unpaid')) ?>)<br>
      Status: <b><?= e(ucfirst($order['status'])) ?></b><?php if ($order['coupon_code']): ?><br>Coupon: <b><?= e($order['coupon_code']) ?></b><?php endif; ?>
      <?php if ($order['notes']): ?><br>Notes: <?= e($order['notes']) ?><?php endif; ?></p>
    </div>
  </div>

  <table class="items">
    <tr>
      <th style="width:36px">#</th><th>Item</th><th class="r" style="width:70px">Qty</th>
      <?php if ($doc === 'invoice'): ?><th class="r" style="width:110px">Price</th><th class="r" style="width:120px">Total</th>
      <?php else: ?><th class="r" style="width:90px">Packed ✓</th><?php endif; ?>
    </tr>
    <?php $n = 1; foreach ($items as $it): ?>
    <tr>
      <td><?= $n++ ?></td>
      <td><?= e($it['product_name']) ?></td>
      <td class="r"><?= (int)$it['qty'] ?></td>
      <?php if ($doc === 'invoice'): ?>
      <td class="r"><?= format_price($it['price']) ?></td>
      <td class="r"><?= format_price($it['total']) ?></td>
      <?php else: ?>
      <td class="r">☐</td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($doc === 'invoice'): ?>
  <div class="totals">
    <table>
      <tr><td>Subtotal</td><td><?= format_price($order['subtotal']) ?></td></tr>
      <?php if ((float)$order['discount'] > 0): ?>
      <tr><td>Discount</td><td>− <?= format_price($order['discount']) ?></td></tr>
      <?php endif; ?>
      <tr><td>Shipping</td><td><?= (float)$order['shipping'] > 0 ? format_price($order['shipping']) : 'FREE' ?></td></tr>
      <tr class="grand"><td>Grand Total</td><td><?= format_price($order['total']) ?></td></tr>
    </table>
  </div>
  <div class="words"><i>Amount in words: <?= e(amount_in_words($order['total'])) ?> Rupees Only</i></div>
  <?php endif; ?>

  <div class="foot">
    <div class="terms">
      <?php if ($doc === 'invoice'): ?>
      Thank you for shopping with <?= e($coName) ?>! Please check your items on delivery.
      For any issue call <?= e($coPhone) ?>.
      <?php else: ?>
      Please verify all items against this slip before dispatch. Tick each item once packed.
      <?php endif; ?>
    </div>
    <div class="sign"><div class="line"></div>Authorized Signature</div>
  </div>
</div>

</body>
</html>
