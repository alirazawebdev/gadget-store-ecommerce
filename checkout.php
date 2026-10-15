<?php
/**
 * Checkout — shipping details + manual payment method selection + order creation.
 *
 * MANUAL payment flow (no provider API):
 *  COD                       -> order placed, payment_status = unpaid, COD fee added.
 *  Easypaisa / JazzCash / Bank-> customer sends money to the merchant account
 *                               shown at checkout (details entered by admin in
 *                               Payment Settings), then submits THEIR mobile
 *                               number + the transaction ID + a screenshot of
 *                               the payment receipt (required). Order is created
 *                               as payment_status = pending; the admin verifies
 *                               the payment (incl. screenshot) in Orders and
 *                               marks it paid.
 */
require_once __DIR__ . '/includes/payments.php';

if (!cart_items()) redirect(base_url('cart.php'));

$methods = pay_enabled_methods();
$codFee  = pay_cod_fee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    $pm      = trim($_POST['payment_method'] ?? '');
    $sender  = pk_phone_normalize(trim($_POST['sender_number'] ?? ''));
    $tid     = trim($_POST['transaction_id'] ?? '');

    if (!isset($methods[$pm])) {
        flash('error', 'Please select a valid payment method.');
        redirect(base_url('checkout.php'));
    }
    if ($name === '' || $phone === '' || $address === '' || $city === '') {
        flash('error', 'Please fill in all required fields (name, phone, address, city).');
        redirect(base_url('checkout.php'));
    }
    if (pay_is_manual($pm)) {
        if (!pay_method_configured($pm)) {
            flash('error', 'This payment method is not set up yet. Please choose another method or contact us.');
            redirect(base_url('checkout.php'));
        }
        if (!pk_phone_valid($sender)) {
            flash('error', 'Please enter the valid 11-digit mobile number you sent the payment from (e.g. 03001234567).');
            redirect(base_url('checkout.php'));
        }
        if ($tid === '' || strlen($tid) > 100) {
            flash('error', 'Please enter the transaction ID / reference number from your payment.');
            redirect(base_url('checkout.php'));
        }
        /* payment screenshot — required for manual methods */
        $proofPath = '';
        $pf = $_FILES['payment_proof'] ?? null;
        if (!$pf || ($pf['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($pf['tmp_name'])) {
            flash('error', 'Please upload a screenshot of your payment receipt.');
            redirect(base_url('checkout.php'));
        }
        $ext = strtolower(pathinfo((string)$pf['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true) || ($pf['size'] ?? 0) > 5 * 1024 * 1024 || !@getimagesize($pf['tmp_name'])) {
            flash('error', 'Please upload a valid image screenshot (JPG, PNG or WebP, max 5MB).');
            redirect(base_url('checkout.php'));
        }
        $proofDir = __DIR__ . '/uploads/payment-proofs';
        if (!is_dir($proofDir) && !@mkdir($proofDir, 0755, true)) {
            flash('error', 'Could not save your screenshot. Please try again or contact us.');
            redirect(base_url('checkout.php'));
        }
        $proofName = 'proof_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!@move_uploaded_file($pf['tmp_name'], $proofDir . '/' . $proofName)) {
            flash('error', 'Could not save your screenshot. Please try again or contact us.');
            redirect(base_url('checkout.php'));
        }
        $proofPath = 'uploads/payment-proofs/' . $proofName;
    } else {
        $proofPath = '';
    }

    $items      = cart_items();
    $subtotal   = cart_subtotal();
    $discount   = coupon_discount($subtotal);
    $couponCode = ($discount > 0 && isset($_SESSION['coupon']['code'])) ? $_SESSION['coupon']['code'] : null;
    $dc         = (float)setting('delivery_charge', 0);
    $freeAbove  = (float)setting('free_shipping_above', 0);
    $shipping   = ($freeAbove > 0 && $subtotal >= $freeAbove) ? 0 : $dc;
    $fee        = ($pm === 'cod') ? $codFee : 0;
    $total      = max(0, $subtotal - $discount + $shipping + $fee);

    /* find-or-create customer by phone */
    $st = db()->prepare("SELECT * FROM customers WHERE phone = ?");
    $st->execute([$phone]);
    $cust = $st->fetch();
    if ($cust) {
        $custId = (int)$cust['id'];
        db()->prepare("UPDATE customers SET name = ?, email = ?, address = ?, city = ? WHERE id = ?")
          ->execute([$name, $email, $address, $city, $custId]);
    } else {
        db()->prepare("INSERT INTO customers (name, phone, email, address, city) VALUES (?, ?, ?, ?, ?)")
          ->execute([$name, $phone, $email, $address, $city]);
        $custId = (int)db()->lastInsertId();
    }

    /* order — COD starts unpaid; manual online starts pending (admin verifies) */
    $payStatus = ($pm === 'cod') ? 'unpaid' : 'pending';
    db()->prepare("INSERT INTO orders (customer_id, name, phone, email, address, city, notes, subtotal, discount, shipping, cod_fee, total, coupon_code, payment_method, payment_status, wallet_number, transaction_ref, payment_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
      ->execute([$custId, $name, $phone, $email, $address, $city, $notes, $subtotal, $discount, $shipping, $fee, $total, $couponCode, $pm, $payStatus, pay_is_manual($pm) ? $sender : '', pay_is_manual($pm) ? $tid : '', $proofPath]);
    $oid = (int)db()->lastInsertId();
    $orderNo = 'QG-' . (1000 + $oid);
    db()->prepare("UPDATE orders SET order_no = ? WHERE id = ?")->execute([$orderNo, $oid]);

    $ist = db()->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, qty, total) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($items as $it) {
        $ist->execute([$oid, $it['id'], $it['name'], $it['unit_price'], $it['qty'], $it['line_total']]);
    }

    if ($couponCode) {
        db()->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?")->execute([$couponCode]);
    }

    db()->prepare("UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?")
      ->execute([$total, $custId]);

    /* remember this order for the "My Orders" page (1-year cookie, no login needed) */
    $mine = [];
    if (!empty($_COOKIE['my_orders'])) {
        $dec = json_decode((string)$_COOKIE['my_orders'], true);
        if (is_array($dec)) $mine = $dec;
    }
    $mine[] = $oid;
    $mine = array_slice(array_values(array_unique(array_map('intval', $mine))), -20);
    setcookie('my_orders', json_encode($mine), time() + 365 * 86400, '/');

    if ($pm === 'cod') {
        pay_log_event($oid, 'cod', 'order_placed_cod', ['cod_fee' => $fee]);
        cart_clear();
        redirect(base_url('order-success.php?o=' . urlencode($orderNo)));
    }

    /* manual online payment — order placed, awaiting admin verification */
    pay_log_event($oid, $pm, 'manual_payment_submitted', ['tid' => $tid, 'sender' => $sender]);
    cart_clear();
    redirect(base_url('payment-pending.php?o=' . urlencode($orderNo)));
}

$pageTitle = 'Checkout';
$pageDesc  = '';
$pageKeys  = '';

$items     = cart_items();
$subtotal  = cart_subtotal();
$discount  = coupon_discount($subtotal);
$dc        = (float)setting('delivery_charge', 0);
$freeAbove = (float)setting('free_shipping_above', 0);
$shipping  = ($freeAbove > 0 && $subtotal >= $freeAbove) ? 0 : $dc;
$baseTotal = max(0, $subtotal - $discount + $shipping);

include __DIR__ . '/includes/header.php';
if (function_exists('show_flash')) show_flash();
?>

<?php if (empty($methods)): ?>
<div class="container section">
  <div class="alert alert-error">No payment methods are available right now. Please contact us to place your order.</div>
</div>
<?php else: ?>
<div class="container section">
  <h1 class="page-title">Checkout</h1>
  <div class="checkout-layout">
    <form method="post" class="checkout-form" id="checkoutForm" enctype="multipart/form-data">
      <h2>Shipping Details</h2>
      <div class="form-group"><label>Full Name *</label><input class="form-control" type="text" name="name" required></div>
      <div class="form-group"><label>Phone *</label><input class="form-control" type="text" name="phone" required></div>
      <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email"></div>
      <div class="form-group"><label>Address *</label><textarea class="form-control" name="address" rows="3" required></textarea></div>
      <div class="form-group"><label>City *</label><input class="form-control" type="text" name="city" required></div>
      <div class="form-group"><label>Order Notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>

      <h2 style="margin-top:26px">Payment Method</h2>
      <div class="pay-methods">
        <?php $first = true; foreach ($methods as $m => $info): ?>
        <label class="pay-card pay-card-<?= e($m) ?><?= $first ? ' selected' : '' ?>">
          <input type="radio" name="payment_method" value="<?= e($m) ?>"<?= $first ? ' checked' : '' ?>>
          <span class="pay-ico"><?= $info['icon'] ?></span>
          <span class="pay-txt">
            <b><?= e($info['label']) ?></b>
            <small><?= e($info['desc']) ?></small>
            <?php if ($m === 'cod' && $codFee > 0): ?>
              <small class="pay-fee">+ <?= format_price($codFee) ?> COD fee applies</small>
            <?php endif; ?>
          </span>
          <span class="pay-check">✓</span>
        </label>
        <?php $first = false; endforeach; ?>
      </div>

      <?php foreach ($methods as $m => $info): ?>
        <?php if (!pay_is_manual($m) || !pay_method_configured($m)) continue; ?>
        <?php $d = pay_method_details($m); ?>
        <div class="pay-howto" id="howto-<?= e($m) ?>">
          <h4>How to pay with <?= e($info['label']) ?></h4>
          <div class="pay-detail"><b>Amount to send</b><span class="copyable" id="howtoTotal-<?= e($m) ?>"><?= format_price($baseTotal) ?></span></div>
          <?php if ($m === 'bank' && $d['bank_name'] !== ''): ?>
          <div class="pay-detail"><b>Bank</b><span><?= e($d['bank_name']) ?></span></div>
          <?php endif; ?>
          <div class="pay-detail"><b>Account title</b><span><?= e($d['title'] !== '' ? $d['title'] : '—') ?></span></div>
          <div class="pay-detail"><b><?= $m === 'bank' ? 'Account no / IBAN' : 'Account number' ?></b><span class="copyable"><?= e($d['number']) ?> <button type="button" class="copy-btn" data-copy="<?= e($d['number']) ?>" title="Copy account number">📋 Copy</button></span></div>
          <ol class="pay-steps">
            <li>Send <b id="howtoTotal2-<?= e($m) ?>"><?= format_price($baseTotal) ?></b> to the account above from your <?= e($info['label']) ?><?= $m === 'bank' ? ' / bank' : '' ?> app.</li>
            <li>Copy the <b>transaction ID<?= $m === 'bank' ? ' / reference number' : '' ?></b> from your payment receipt.</li>
            <li>Paste it below and submit — we verify every payment manually before dispatch.</li>
          </ol>
        </div>
      <?php endforeach; ?>

      <div id="manualBox" style="display:none">
        <div class="form-group">
          <label>Your Mobile Number * <small style="color:#777">(the number you sent the payment from)</small></label>
          <input class="form-control" type="tel" name="sender_number" id="senderNumber" placeholder="03001234567" maxlength="11">
        </div>
        <div class="form-group">
          <label>Transaction ID / Reference Number * <small style="color:#777">(from your payment receipt)</small></label>
          <input class="form-control" type="text" name="transaction_id" id="txnId" placeholder="e.g. 12345678901" maxlength="100">
        </div>
        <div class="form-group">
          <label>Payment Screenshot * <small style="color:#777">(upload a screenshot of your payment receipt — required)</small></label>
          <input class="form-control" type="file" name="payment_proof" id="proofFile" accept="image/*">
        </div>
        <div class="alert alert-error" id="manualErr" style="display:none;margin-top:8px">Please enter a valid 11-digit mobile number, your transaction ID, and upload the payment screenshot.</div>
      </div>

      <button class="btn btn-big" type="submit" id="placeBtn" style="margin-top:18px;width:100%">Place Order</button>
      <p style="text-align:center;color:#777;font-size:13px;margin-top:10px">🔒 Online payments are verified manually by our team before dispatch.</p>
    </form>

    <div class="checkout-summary">
      <h2>Your Order</h2>
      <?php foreach ($items as $it): ?>
      <p><?= e($it['name']) ?> &times; <?= (int)$it['qty'] ?> <b><?= format_price($it['line_total']) ?></b></p>
      <?php endforeach; ?>
      <p>Subtotal: <b><?= format_price($subtotal) ?></b></p>
      <?php if ($discount > 0): ?><p>Discount: <b>-<?= format_price($discount) ?></b></p><?php endif; ?>
      <p>Shipping: <b><?= $shipping > 0 ? format_price($shipping) : 'FREE' ?></b></p>
      <p id="codFeeRow" style="display:none">COD Fee: <b><?= format_price($codFee) ?></b></p>
      <p class="grand">Total: <b id="grandTotal"><?= format_price($baseTotal + $codFee) ?></b></p>
    </div>
  </div>
</div>
<script>
(function () {
  var baseTotal = <?= (float)$baseTotal ?>;
  var codFee = <?= (float)$codFee ?>;
  var cards = document.querySelectorAll('.pay-card input[name=payment_method]');
  var manualMethods = <?= json_encode(array_keys(array_filter($methods, function ($i) { return !empty($i['manual']); }))) ?>;
  var manualBox = document.getElementById('manualBox');
  var manualErr = document.getElementById('manualErr');
  var codFeeRow = document.getElementById('codFeeRow');
  var grandTotal = document.getElementById('grandTotal');
  var placeBtn = document.getElementById('placeBtn');
  var senderNumber = document.getElementById('senderNumber');
  var txnId = document.getElementById('txnId');
  var proofFile = document.getElementById('proofFile');

  function fmt(n) { return 'Rs. ' + Math.round(n).toLocaleString('en-PK'); }
  function current() {
    var el = document.querySelector('.pay-card input[name=payment_method]:checked');
    return el ? el.value : 'cod';
  }
  function isManual(m) { return manualMethods.indexOf(m) !== -1; }
  function refresh() {
    var m = current();
    document.querySelectorAll('.pay-card').forEach(function (c) {
      c.classList.toggle('selected', c.querySelector('input').checked);
    });
    document.querySelectorAll('.pay-howto').forEach(function (b) { b.classList.remove('show'); });
    var howto = document.getElementById('howto-' + m);
    if (howto) howto.classList.add('show');
    manualBox.style.display = isManual(m) ? '' : 'none';
    var showFee = (m === 'cod' && codFee > 0);
    codFeeRow.style.display = showFee ? '' : 'none';
    grandTotal.textContent = fmt(baseTotal + (showFee ? codFee : 0));
    placeBtn.textContent = m === 'cod' ? 'Place Order (Cash on Delivery)' : 'Submit Order';
  }
  cards.forEach(function (r) { r.addEventListener('change', refresh); });
  document.getElementById('checkoutForm').addEventListener('submit', function (ev) {
    var m = current();
    if (isManual(m)) {
      var v = (senderNumber.value || '').replace(/\D/g, '');
      var t = (txnId.value || '').trim();
      var f = proofFile && proofFile.files && proofFile.files[0];
      if (!/^03\d{9}$/.test(v) || t === '' || !f) { ev.preventDefault(); manualErr.style.display = ''; return; }
      senderNumber.value = v;
    }
    manualErr.style.display = 'none';
  });
  refresh();
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
