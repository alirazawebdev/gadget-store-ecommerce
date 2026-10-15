<?php
/**
 * Shopping cart — update qty, remove items, apply coupon, summary.
 */
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        foreach ((array)($_POST['qty'] ?? []) as $pid => $q) {
            cart_set((int)$pid, (int)$q);
        }
        flash('success', 'Cart updated.');
        redirect(base_url('cart.php'));
    } elseif ($action === 'remove') {
        cart_remove((int)($_POST['id'] ?? 0));
        flash('success', 'Item removed from cart.');
        redirect(base_url('cart.php'));
    } elseif ($action === 'coupon') {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            unset($_SESSION['coupon']);
            flash('success', 'Coupon removed.');
        } else {
            $st = db()->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? AND status = 'active'");
            $st->execute([strtoupper($code)]);
            $cp = $st->fetch();
            if (!$cp) {
                flash('error', 'Invalid coupon code.');
            } elseif (!empty($cp['expires_at']) && strtotime($cp['expires_at']) < time()) {
                flash('error', 'This coupon has expired.');
            } elseif ((float)$cp['min_order'] > 0 && cart_subtotal() < (float)$cp['min_order']) {
                flash('error', 'This coupon needs a minimum order of ' . format_price($cp['min_order']) . '.');
            } elseif ((int)$cp['usage_limit'] > 0 && (int)$cp['used_count'] >= (int)$cp['usage_limit']) {
                flash('error', 'This coupon has reached its usage limit.');
            } else {
                $_SESSION['coupon'] = ['code' => $cp['code']];
                flash('success', 'Coupon applied: ' . $cp['code']);
            }
        }
        redirect(base_url('cart.php'));
    }
}

$pageTitle = 'Shopping Cart';
$pageDesc  = '';
$pageKeys  = '';

$items    = cart_items();
$subtotal = cart_subtotal();
$discount = coupon_discount($subtotal);
$dc       = (float)setting('delivery_charge', 0);
$freeAbove = (float)setting('free_shipping_above', 0);
$shipping = ($freeAbove > 0 && $subtotal >= $freeAbove) ? 0 : $dc;
if (!$items) $shipping = 0;
$total    = max(0, $subtotal - $discount + $shipping);
$coupon   = $_SESSION['coupon']['code'] ?? null;


/* recommended products (not already in cart) */
$rec = [];
if ($items) {
    $ids = array_map(function ($it) { return (int)$it['id']; }, $items);
    $in  = implode(',', $ids);
    $rec = db()->query("SELECT * FROM products WHERE status = 'active' AND featured = 1 AND id NOT IN ($in) ORDER BY id DESC LIMIT 4")->fetchAll();
    $need = 4 - count($rec);
    if ($need > 0) {
        $more = db()->query("SELECT * FROM products WHERE status = 'active' AND id NOT IN ($in) ORDER BY id DESC LIMIT $need")->fetchAll();
        $rec = array_merge($rec, $more);
    }
}

/* free-shipping progress */
$shipPct = 0; $shipAway = 0; $shipFree = false;
if ($freeAbove > 0 && $items) {
    if ($subtotal >= $freeAbove) { $shipFree = true; $shipPct = 100; }
    else { $shipAway = $freeAbove - $subtotal; $shipPct = (int)round($subtotal / $freeAbove * 100); }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <div class="cart-head">
    <h1 class="page-title">Your Cart <span class="cart-n">(<?= cart_count() ?> item(s))</span></h1>
    <a class="link-more" href="<?= base_url() ?>">Continue Shopping &rarr;</a>
  </div>

  <?php if (!$items): ?>
    <div class="cart-empty">
      <div class="empty-ic">&#128722;</div>
      <h2>Your cart is empty</h2>
      <p>Looks like you haven't added anything yet. Let's find something you'll love!</p>
      <a class="btn" href="<?= base_url() ?>">Continue Shopping</a>
    </div>
  <?php else: ?>
    <?php if ($freeAbove > 0): ?>
    <div class="ship-bar">
      <div class="ship-msg">
        <?php if ($shipFree): ?>&#127881; <b>You've unlocked FREE shipping!</b>
        <?php else: ?>&#128666; You're <b><?= format_price($shipAway) ?></b> away from <b>FREE</b> shipping!<?php endif; ?>
      </div>
      <div class="ship-track"><div class="ship-fill" style="width:<?= $shipPct ?>%"></div></div>
    </div>
    <?php endif; ?>

    <div class="cart-shop">
      <div class="cart-items">
        <div class="ci-head"><span>Product</span><span>Price</span><span>Quantity</span><span>Total</span><span></span></div>
        <?php foreach ($items as $it): ?>
        <div class="ci-row">
          <div class="ci-prod">
            <a href="<?= product_url($it) ?>"><img src="<?= e(product_thumb($it)) ?>" alt="<?= e($it['name']) ?>"></a>
            <div><a class="ci-name" href="<?= product_url($it) ?>"><?= e($it['name']) ?></a></div>
          </div>
          <div class="ci-price"><?= format_price($it['unit_price']) ?></div>
          <div class="ci-qty">
            <form method="post" action="<?= base_url('cart.php') ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="qty[<?= (int)$it['id'] ?>]" value="<?= max(0, (int)$it['qty'] - 1) ?>">
              <button class="qbtn" type="submit" <?= (int)$it['qty'] <= 1 ? 'disabled' : '' ?>>&minus;</button>
            </form>
            <span class="qnum"><?= (int)$it['qty'] ?></span>
            <form method="post" action="<?= base_url('cart.php') ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] + 1 ?>">
              <button class="qbtn" type="submit">+</button>
            </form>
          </div>
          <div class="ci-total"><?= format_price($it['line_total']) ?></div>
          <div class="ci-rm">
            <form method="post" action="<?= base_url('cart.php') ?>" onsubmit="return confirm('Remove this item?')">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="rmbtn" type="submit" aria-label="Remove">&#128465;</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="coupon-row">
          <?php if ($coupon): ?>
            <span class="cpn-on">Coupon applied: <b><?= e($coupon) ?></b></span>
            <form method="post" action="<?= base_url('cart.php') ?>">
              <input type="hidden" name="action" value="coupon">
              <input type="hidden" name="code" value="">
              <button class="btn btn-sm" type="submit">Remove</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= base_url('cart.php') ?>" class="cpn-form">
              <input type="hidden" name="action" value="coupon">
              <input type="text" name="code" placeholder="Coupon code">
              <button class="btn btn-sm" type="submit">Apply</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <aside class="cart-sum">
        <h3>Order Summary</h3>
        <div class="sum-row"><span>Subtotal (<?= cart_count() ?> items)</span><b><?= format_price($subtotal) ?></b></div>
        <div class="sum-row"><span>Shipping</span><b><?= $shipping > 0 ? format_price($shipping) : 'FREE' ?></b></div>
        <?php if ($discount > 0): ?><div class="sum-row disc"><span>Discount</span><b>&minus;<?= format_price($discount) ?></b></div><?php endif; ?>
        <div class="sum-total"><span>Total</span><b><?= format_price($total) ?></b></div>
        <a class="sum-checkout" href="<?= base_url('checkout.php') ?>">Proceed to Checkout &rarr;</a>
        <div class="sum-or">or <a href="<?= base_url() ?>">Continue Shopping</a></div>
        <div class="sum-secure">&#128274; <b>Secure Checkout</b><small>Your information is safe</small></div>
      </aside>
    </div>

    <?php if ($rec): ?>
    <h2 class="sec-title" style="margin-top:10px">Recommended for You</h2>
    <p class="sec-sub">Frequently bought together</p>
    <div class="product-grid">
      <?php foreach ($rec as $rp): ?>
        <?= product_card_html($rp) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
