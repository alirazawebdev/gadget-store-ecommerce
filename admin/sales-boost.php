<?php
/**
 * Sales Boost — Shopify-style urgency & conversion widgets.
 * Every widget has an on/off switch + its own settings. No coding needed.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Sales Boost';
if (!can('products')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

function sb_save($k, $v) {
    db()->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = ?")
      ->execute([$k, $v, $v]);
}

$toggles = ['boost_lowstock', 'boost_viewers', 'boost_countdown', 'boost_stockbar',
            'boost_stickybar', 'boost_salespop', 'boost_badges'];
$numbers = ['boost_lowstock_default' => 5, 'boost_viewers_min' => 12, 'boost_viewers_max' => 48,
            'boost_salespop_interval' => 15, 'boost_salespop_duration' => 6,
            'boost_badges_new_days' => 14];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($toggles as $k) sb_save($k, isset($_POST[$k]) ? '1' : '0');
    foreach ($numbers as $k => $dflt) {
        $v = (int)($_POST[$k] ?? $dflt);
        if ($v < 0) $v = $dflt;
        sb_save($k, (string)$v);
    }
    log_activity('Updated Sales Boost settings');
    flash('success', 'Sales Boost settings saved.');
    redirect(admin_url('sales-boost.php'));
}

$on  = function ($k) { return setting($k, '1') === '1'; };
$num = function ($k, $d) { return (int)setting($k, (string)$d); };

$features = [
    ['boost_lowstock', 'Low Stock Urgency',
     'Shows "Only X left in stock — order soon!" with a pulsing red alert when stock is low.',
     [['boost_lowstock_default', 'Show warning when stock is at or below', 'pcs']]],
    ['boost_viewers', 'Live Viewers Counter',
     'Shows "N people are viewing this right now" with a green pulse dot. Count is stable per hour.',
     [['boost_viewers_min', 'Minimum viewers', ''], ['boost_viewers_max', 'Maximum viewers', '']]],
    ['boost_countdown', 'Sale Countdown Timer',
     'Shows "Sale ends in HH:MM:SS" on the product page. Uses each product\'s <b>Flash sale end date</b> (set in Products → Edit). Only shows when a sale price is active.',
     []],
    ['boost_stockbar', 'Stock Progress Bar',
     '"Selling fast! X% claimed" bar — calculated from your <b>real</b> stock log (sold vs remaining).',
     []],
    ['boost_stickybar', 'Sticky Add-to-Cart Bar',
     'When the customer scrolls past the buy button, a bar sticks to the bottom with product image, price and Add to Cart.',
     []],
    ['boost_salespop', 'Sales Pop Notifications',
     'Bottom-left popup: "Ahmed from Lahore purchased Wireless Earbuds — 12 minutes ago". Uses <b>real recent orders only</b> (last 30 days). Shows nothing until you have orders.',
     [['boost_salespop_interval', 'Show a popup every', 'seconds'], ['boost_salespop_duration', 'Each popup stays for', 'seconds']]],
    ['boost_badges', 'Smart Badges',
     'Automatic badges on product cards & galleries: <b>Bestseller</b> (top 8 by units sold), <b>Trending</b> (top 8 by views), <b>New</b> (recently added).',
     [['boost_badges_new_days', '"New" badge for products added in last', 'days']]],
];

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>⚡ Sales Boost</h1>
  <p>Shopify-style urgency &amp; trust widgets. Turn each feature on/off — changes apply instantly, no coding needed.</p>
</div>

<div class="card" style="margin-bottom:18px">
  <b>Per-product settings</b> live in <a href="<?= admin_url('products.php') ?>">Products → Edit</a>:
  <b>Low stock alert</b> (low-stock warning limit) and <b>Flash sale end date/time</b> (countdown timer).
</div>

<form method="post">
<?php foreach ($features as [$key, $title, $desc, $fields]): ?>
  <div class="card" style="margin-bottom:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h3 style="margin:0 0 4px"><?= e($title) ?></h3>
        <p style="margin:0;color:#666;font-size:13px;max-width:640px"><?= $desc ?></p>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-weight:700;white-space:nowrap">
        <input type="checkbox" name="<?= $key ?>" value="1" <?= $on($key) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:#f26522">
        <?= $on($key) ? '<span style="color:#16a34a">ON</span>' : '<span style="color:#999">OFF</span>' ?>
      </label>
    </div>
    <?php if ($fields): ?>
    <div class="form-row" style="margin-top:12px">
      <?php foreach ($fields as [$fkey, $flabel, $suffix]): ?>
      <div class="form-group">
        <label><?= e($flabel) ?></label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="number" name="<?= $fkey ?>" class="form-control" style="max-width:140px"
                 value="<?= $num($fkey, $numbers[$fkey]) ?>">
          <?php if ($suffix): ?><span style="color:#666;font-size:13px"><?= e($suffix) ?></span><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<button class="btn" type="submit" style="font-size:16px;padding:12px 34px">💾 Save All Settings</button>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
