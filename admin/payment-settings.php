<?php
/**
 * Payment Settings — MANUAL payment methods.
 *
 * The admin enables/disables each method with its own toggle and enters the
 * RECEIVING details (account title, account number, WhatsApp) for each one.
 * Only enabled methods appear at checkout; disabled ones disappear entirely.
 * There is no provider API — customers transfer money from their own app and
 * submit the transaction ID; the admin verifies it in Orders.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Payment Settings';
if (!can('settings')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

function pay_save($k, $v) {
    db()->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = ?")
      ->execute([$k, $v, $v]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toggles = ['pay_cod_enabled', 'pay_easypaisa_enabled', 'pay_jazzcash_enabled', 'pay_bank_enabled'];
    foreach ($toggles as $k) pay_save($k, isset($_POST[$k]) ? '1' : '0');

    pay_save('pay_cod_fee', (string)max(0, (float)($_POST['pay_cod_fee'] ?? 0)));

    $text = ['pay_easypaisa_title', 'pay_easypaisa_number', 'pay_easypaisa_whatsapp',
             'pay_jazzcash_title', 'pay_jazzcash_number', 'pay_jazzcash_whatsapp',
             'pay_bank_name', 'pay_bank_title', 'pay_bank_number', 'pay_bank_whatsapp'];
    foreach ($text as $k) pay_save($k, trim($_POST[$k] ?? ''));

    log_activity('Updated payment settings');
    flash('success', 'Payment settings saved.');
    redirect(admin_url('payment-settings.php'));
}

$v = function ($k, $d = '') { return setting($k, $d); };
$on = function ($k) { return setting($k, '0') === '1'; };
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Payment Settings</h1>
  <p style="color:var(--muted2);margin:6px 0 0">Manual payments: turn each method on/off and enter <b>your receiving account details</b>. Customers send money to these accounts from their own app and submit the transaction ID — you verify it in <b>Orders</b>.</p>
</div>

<?php if (function_exists('show_flash')) show_flash(); ?>

<form method="post" action="">

<div class="card">
  <h3>💵 Cash on Delivery</h3>
  <div class="form-group">
    <label><input type="checkbox" name="pay_cod_enabled" value="1" <?= $on('pay_cod_enabled') ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:#f26522"> Enable COD at checkout</label>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>COD Fee (Rs.) — added to the order total when the customer chooses COD</label>
      <input type="number" min="0" step="1" name="pay_cod_fee" class="form-control" value="<?= e($v('pay_cod_fee','0')) ?>">
    </div>
  </div>
</div>

<div class="card">
  <h3>📱 Easypaisa</h3>
  <div class="form-group">
    <label><input type="checkbox" name="pay_easypaisa_enabled" value="1" <?= $on('pay_easypaisa_enabled') ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:#f26522"> Enable Easypaisa at checkout</label>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Account Title (holder name)</label><input type="text" name="pay_easypaisa_title" class="form-control" value="<?= e($v('pay_easypaisa_title')) ?>" placeholder="e.g. Muhammad Ali"></div>
    <div class="form-group"><label>Easypaisa Account Number</label><input type="text" name="pay_easypaisa_number" class="form-control" value="<?= e($v('pay_easypaisa_number')) ?>" placeholder="e.g. 03451234567"></div>
  </div>
  <div class="form-group">
    <label>WhatsApp Number <small style="color:var(--muted2)">(customers get a "Confirm on WhatsApp" button)</small></label>
    <input type="text" name="pay_easypaisa_whatsapp" class="form-control" value="<?= e($v('pay_easypaisa_whatsapp')) ?>" placeholder="e.g. 03001234567">
  </div>
</div>

<div class="card">
  <h3>📲 JazzCash</h3>
  <div class="form-group">
    <label><input type="checkbox" name="pay_jazzcash_enabled" value="1" <?= $on('pay_jazzcash_enabled') ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:#f26522"> Enable JazzCash at checkout</label>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Account Title (holder name)</label><input type="text" name="pay_jazzcash_title" class="form-control" value="<?= e($v('pay_jazzcash_title')) ?>" placeholder="e.g. Muhammad Ali"></div>
    <div class="form-group"><label>JazzCash Account Number</label><input type="text" name="pay_jazzcash_number" class="form-control" value="<?= e($v('pay_jazzcash_number')) ?>" placeholder="e.g. 03001234567"></div>
  </div>
  <div class="form-group">
    <label>WhatsApp Number <small style="color:var(--muted2)">(customers get a "Confirm on WhatsApp" button)</small></label>
    <input type="text" name="pay_jazzcash_whatsapp" class="form-control" value="<?= e($v('pay_jazzcash_whatsapp')) ?>" placeholder="e.g. 03001234567">
  </div>
</div>

<div class="card">
  <h3>🏦 Bank Account</h3>
  <div class="form-group">
    <label><input type="checkbox" name="pay_bank_enabled" value="1" <?= $on('pay_bank_enabled') ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:#f26522"> Enable bank transfer at checkout</label>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Bank Name</label><input type="text" name="pay_bank_name" class="form-control" value="<?= e($v('pay_bank_name')) ?>" placeholder="e.g. Meezan Bank"></div>
    <div class="form-group"><label>Account Title</label><input type="text" name="pay_bank_title" class="form-control" value="<?= e($v('pay_bank_title')) ?>" placeholder="e.g. Muhammad Ali"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Account Number / IBAN</label><input type="text" name="pay_bank_number" class="form-control" value="<?= e($v('pay_bank_number')) ?>" placeholder="e.g. PK36MEZN0001234567890123"></div>
    <div class="form-group"><label>WhatsApp Number</label><input type="text" name="pay_bank_whatsapp" class="form-control" value="<?= e($v('pay_bank_whatsapp')) ?>" placeholder="e.g. 03001234567"></div>
  </div>
</div>

<button class="btn" type="submit">Save Payment Settings</button>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
