<?php /** Frontend footer */ ?>
</main>

<div class="trust-strip">
  <div class="container trust-grid">
    <div>🚚 Nationwide Delivery</div>
    <div>💵 Cash on Delivery</div>
    <div>↩️ 7-Day Easy Returns</div>
    <div>🛡️ Quality Checked Products</div>
  </div>
</div>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="f-col">
      <a class="f-logo" href="<?= base_url() ?>" aria-label="<?= e(site_name()) ?>">
        <img src="<?= base_url('assets/images/logo.svg') ?>" alt="<?= e(site_name()) ?>" width="42" height="42">
        <span><?= e(site_name()) ?></span>
      </a>
      <p><?= e(setting('meta_description', 'Quality gadgets at the best prices in Pakistan. Cash on Delivery nationwide.')) ?></p>
      <div class="socials">
        <?php
        $socials = [
          ['facebook_url',  'Facebook',  '#1877F2', '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>'],
          ['instagram_url', 'Instagram', '#E1306C', '<rect x="2.5" y="2.5" width="19" height="19" rx="5.5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.4" cy="6.6" r="1.4"/>'],
          ['youtube_url',   'YouTube',   '#FF0000', '<rect x="2" y="5" width="20" height="14" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M10.2 9.3l4.8 2.7-4.8 2.7z"/>'],
          ['tiktok_url',    'TikTok',    '#111111', '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>'],
          ['whatsapp_url',  'WhatsApp',  '#25D366', '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8.8 7.6c.3-.7.6-.7.9-.7h.7c.2 0 .5 0 .7.5l1 2.4c.1.3 0 .6-.2.8l-.6.7c.5 1 1.4 1.9 2.4 2.4l.7-.6c.2-.2.5-.3.8-.2l2.4 1c.5.2.5.5.5.7v.7c0 .3 0 .6-.7.9-.5.2-1.2.3-2.1.1-2.8-.6-5.6-3.4-6.2-6.2-.2-.9-.1-1.6.1-2.1z"/>'],
        ];
        foreach ($socials as [$key, $label, $color, $icon]):
          $url = setting($key);
          if ($url && $url !== '#'):
        ?>
          <a class="soc" href="<?= e($url) ?>" target="_blank" rel="noopener" title="<?= e($label) ?>" aria-label="<?= e($label) ?>" style="background:<?= e($color) ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="#fff" aria-hidden="true"><?= $icon ?></svg>
          </a>
        <?php endif; endforeach; ?>
      </div>
    </div>
    <div class="f-col">
      <h4>Quick Links</h4>
      <a href="<?= base_url('page.php?slug=about-us') ?>">About Us</a>
      <a href="<?= base_url('page.php?slug=contact-us') ?>">Contact Us</a>
      <a href="<?= base_url('track-order.php') ?>">Track Your Order</a>
      <a href="<?= base_url('cart.php') ?>">Shopping Cart</a>
    </div>
    <div class="f-col">
      <h4>Policies</h4>
      <a href="<?= base_url('page.php?slug=privacy-policy') ?>">Privacy Policy</a>
      <a href="<?= base_url('page.php?slug=refund-policy') ?>">Refund Policy</a>
      <a href="<?= base_url('page.php?slug=terms-conditions') ?>">Terms &amp; Conditions</a>
    </div>
    <div class="f-col">
      <h4>Contact</h4>
      <?php if (setting('contact_phone')): ?><p>📞 <?= e(setting('contact_phone')) ?></p><?php endif; ?>
      <?php if (setting('contact_email')): ?><p>✉️ <?= e(setting('contact_email')) ?></p><?php endif; ?>
      <?php if (setting('contact_address')): ?><p>📍 <?= e(setting('contact_address')) ?></p><?php endif; ?>
      <p>💵 Cash on Delivery available</p>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">© <?= date('Y') ?> <?= e(site_name()) ?>. All rights reserved.</div>
  </div>
</footer>

<?php
$waDigits = preg_replace('/\D/', '', setting('whatsapp_number'));
if ($waDigits !== '' && $waDigits[0] === '0') $waDigits = '92' . substr($waDigits, 1); // Pakistan numbers
?>
<?php if ($waDigits): ?>
<a class="wa-float" href="https://wa.me/<?= e($waDigits) ?>?text=<?= urlencode('Hello! I want to place an order.') ?>" target="_blank" aria-label="Chat on WhatsApp">💬</a>
<?php endif; ?>

<?php /* Shopify-style sales pop: recent verified purchases (real orders) */ ?>
<?php if (boost_on('boost_salespop')): ?>
<div class="sales-pop" id="salesPop"
     data-src="<?= base_url('ajax/sales-pop.php') ?>"
     data-interval="<?= boost_int('boost_salespop_interval', 15) ?>"
     data-duration="<?= boost_int('boost_salespop_duration', 6) ?>">
  <button class="sp-close" aria-label="Close">✕</button>
  <div class="sp-img"></div>
  <div class="sp-txt"></div>
</div>
<?php endif; ?>

<?php
try {
    $pop = db()->query("SELECT * FROM popups WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();
} catch (Exception $ex) { $pop = null; }
?>
<?php if ($pop): ?>
<div class="popup-overlay" id="salePopup" style="display:none">
  <div class="popup-box">
    <button class="popup-close" id="popupClose">✕</button>
    <?php if ($pop['image']): ?><img src="<?= e(img_url($pop['image'])) ?>" alt="<?= e($pop['title']) ?>"><?php endif; ?>
    <h3><?= e($pop['title']) ?></h3>
    <div class="popup-content"><?= $pop['content'] /* admin-authored HTML */ ?></div>
    <?php if ($pop['button_text']): ?><a class="btn" href="<?= e($pop['button_link'] ?: base_url()) ?>"><?= e($pop['button_text']) ?></a><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
/* Mobile bottom navigation bar (phones only; styled in patch.css). */
$__bnPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$__bnCart = function_exists('cart_count') ? (int)cart_count() : 0;
$__bnTabs = [
  ['home',   'Home',      base_url(),                ['index.php']],
  ['shop',   'Shop',      base_url('category.php'),  ['category.php', 'product.php']],
  ['orders', 'My Orders', base_url('my-orders.php'), ['my-orders.php', 'track-order.php']],
  ['cart',   'Cart',      base_url('cart.php'),      ['cart.php', 'checkout.php']],
];
$__bnIcons = [
  'home'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/></svg>',
  'shop'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
  'orders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l9-5 9 5v8l-9 5-9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg>',
  'cart'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M2 3h3l2.6 12.5h11.2l2.2-8.5H6"/></svg>',
];
?>
<nav class="mobile-bottom-nav" aria-label="Mobile navigation">
<?php foreach ($__bnTabs as $t): $active = in_array($__bnPage, $t[3], true); ?>
  <a href="<?= e($t[2]) ?>" class="mbn-item<?= $active ? ' active' : '' ?>">
    <span class="mbn-ic"><?= $__bnIcons[$t[0]] ?><?php if ($t[0] === 'cart' && $__bnCart > 0): ?><span class="mbn-badge"><?= $__bnCart ?></span><?php endif; ?></span>
    <span class="mbn-label"><?= e($t[1]) ?></span>
  </a>
<?php endforeach; ?>
</nav>

<?= setting('fb_pixel') /* raw: Facebook Pixel script */ ?>
<script src="<?= base_url('assets/js/main.js') ?>?v=<?= (int)(@filemtime(__DIR__ . '/../assets/js/main.js') ?: time()) ?>"></script>
</body>
</html>
