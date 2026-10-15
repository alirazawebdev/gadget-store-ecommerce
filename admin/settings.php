<?php
/**
 * General site settings.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Settings';
if (!can('settings')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

function save_setting($k, $v) {
    db()->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = ?")
      ->execute([$k, $v, $v]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['site_name', 'announcement_text', 'currency', 'delivery_charge', 'free_shipping_above',
             'whatsapp_number', 'contact_email', 'contact_phone', 'contact_address',
             'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'whatsapp_url'];
    foreach ($keys as $k) {
        save_setting($k, trim($_POST[$k] ?? ''));
    }
    $up = upload_image($_FILES['site_logo'] ?? [], 'banners');
    if ($up) {
        $old = setting('site_logo');
        if ($old) {
            $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($old, '/');
            if (is_file($f)) @unlink($f);
        }
        save_setting('site_logo', $up);
    }
    log_activity('Updated general settings');
    flash('success', 'Settings saved.');
    redirect(admin_url('settings.php'));
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>General Settings</h1>
</div>

<form method="post" action="" enctype="multipart/form-data">
<div class="card">
  <h3>Store Info</h3>
  <div class="form-row">
    <div class="form-group">
      <label>Site Name</label>
      <input type="text" name="site_name" class="form-control" value="<?= e(setting('site_name', 'Gadget Store')) ?>">
    </div>
    <div class="form-group">
      <label>Currency Symbol</label>
      <input type="text" name="currency" class="form-control" value="<?= e(setting('currency', 'Rs.')) ?>">
    </div>
  </div>
  <div class="form-group">
    <label>Site Logo</label>
    <?php if (setting('site_logo')): ?>
    <img src="<?= e(img_url(setting('site_logo'))) ?>" style="max-height:70px;display:block;margin-bottom:8px">
    <?php endif; ?>
    <input type="file" name="site_logo" class="form-control" accept="image/*">
  </div>
  <div class="form-group">
    <label>Announcement Bar Text <small style="color:#6b7280">(scrolling strip at top of site)</small></label>
    <input type="text" name="announcement_text" class="form-control" value="<?= e(setting('announcement_text')) ?>">
  </div>
</div>

<div class="card">
  <h3>Shipping</h3>
  <div class="form-row">
    <div class="form-group">
      <label>Delivery Charge (Rs.)</label>
      <input type="number" step="0.01" min="0" name="delivery_charge" class="form-control" value="<?= e(setting('delivery_charge', '200')) ?>">
    </div>
    <div class="form-group">
      <label>Free Shipping Above (Rs.) <small style="color:#6b7280">(0 = disabled)</small></label>
      <input type="number" step="0.01" min="0" name="free_shipping_above" class="form-control" value="<?= e(setting('free_shipping_above', '0')) ?>">
    </div>
  </div>
</div>

<div class="card">
  <h3>Contact & Social</h3>
  <div class="form-row">
    <div class="form-group">
      <label>WhatsApp Number</label>
      <input type="text" name="whatsapp_number" class="form-control" value="<?= e(setting('whatsapp_number')) ?>" placeholder="923001234567">
    </div>
    <div class="form-group">
      <label>Contact Email</label>
      <input type="email" name="contact_email" class="form-control" value="<?= e(setting('contact_email')) ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Contact Phone</label>
      <input type="text" name="contact_phone" class="form-control" value="<?= e(setting('contact_phone')) ?>">
    </div>
    <div class="form-group">
      <label>Address</label>
      <input type="text" name="contact_address" class="form-control" value="<?= e(setting('contact_address')) ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Facebook URL</label>
      <input type="text" name="facebook_url" class="form-control" value="<?= e(setting('facebook_url')) ?>">
    </div>
    <div class="form-group">
      <label>Instagram URL</label>
      <input type="text" name="instagram_url" class="form-control" value="<?= e(setting('instagram_url')) ?>">
    </div>
  </div>
  <div class="form-group">
    <label>YouTube URL</label>
    <input type="text" name="youtube_url" class="form-control" value="<?= e(setting('youtube_url')) ?>">
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>TikTok URL</label>
      <input type="text" name="tiktok_url" class="form-control" value="<?= e(setting('tiktok_url')) ?>">
    </div>
    <div class="form-group">
      <label>WhatsApp URL <small>(e.g. https://wa.me/923001234567)</small></label>
      <input type="text" name="whatsapp_url" class="form-control" value="<?= e(setting('whatsapp_url')) ?>">
    </div>
  </div>
</div>

<button class="btn" type="submit">Save Settings</button>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
