<?php
/**
 * SEO hub — global meta + tracking codes + per-item SEO overview.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'SEO';
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
    foreach (['site_title', 'meta_description', 'meta_keywords', 'ga_code', 'gsc_code', 'fb_pixel'] as $k) {
        save_setting($k, trim($_POST[$k] ?? ''));
    }
    log_activity('Updated SEO & tracking settings');
    flash('success', 'SEO settings saved.');
    redirect(admin_url('seo.php'));
}

$products   = db()->query("SELECT id, name, meta_title FROM products ORDER BY id DESC LIMIT 50")->fetchAll();
$categories = db()->query("SELECT id, name, meta_title FROM categories ORDER BY sort_order, name")->fetchAll();
$pages      = db()->query("SELECT id, title, meta_title FROM pages ORDER BY title")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>SEO & Tracking</h1>
</div>

<form method="post" action="">
<div class="card">
  <h3>Global SEO</h3>
  <div class="form-group">
    <label>Site Title</label>
    <input type="text" name="site_title" class="form-control" value="<?= e(setting('site_title', setting('site_name'))) ?>">
  </div>
  <div class="form-group">
    <label>Meta Description <small style="color:#6b7280">(default for pages without their own)</small></label>
    <textarea name="meta_description" class="form-control" style="min-height:70px"><?= e(setting('meta_description')) ?></textarea>
  </div>
  <div class="form-group">
    <label>Meta Keywords</label>
    <input type="text" name="meta_keywords" class="form-control" value="<?= e(setting('meta_keywords')) ?>">
  </div>
</div>

<div class="card">
  <h3>Tracking Codes</h3>
  <p style="font-size:13px;color:#6b7280;margin-bottom:12px">Paste the raw script / meta tags exactly as provided by Google / Facebook.</p>
  <div class="form-group">
    <label>Google Analytics (gtag.js)</label>
    <textarea name="ga_code" class="form-control" style="min-height:90px" placeholder="&lt;script async src=&quot;https://www.googletagmanager.com/gtag/js?id=G-XXXX&quot;&gt;&lt;/script&gt;..."><?= e(setting('ga_code')) ?></textarea>
  </div>
  <div class="form-group">
    <label>Google Search Console Verification</label>
    <textarea name="gsc_code" class="form-control" style="min-height:60px" placeholder="&lt;meta name=&quot;google-site-verification&quot; content=&quot;...&quot; /&gt;"><?= e(setting('gsc_code')) ?></textarea>
  </div>
  <div class="form-group">
    <label>Facebook Pixel</label>
    <textarea name="fb_pixel" class="form-control" style="min-height:90px" placeholder="&lt;script&gt;!function(f,b,e,v,n,t,s)...&lt;/script&gt;"><?= e(setting('fb_pixel')) ?></textarea>
  </div>
</div>

<button class="btn" type="submit">Save SEO Settings</button>
</form>

<div class="grid-2" style="margin-top:20px">
  <div class="card">
    <h3>Products — Meta Titles</h3>
    <table class="table">
      <tr><th>Product</th><th>Meta Title</th><th></th></tr>
      <?php foreach ($products as $p): ?>
      <tr>
        <td><?= e(substr($p['name'], 0, 40)) ?></td>
        <td><?= $p['meta_title'] ? '<span class="badge badge-approved">Set</span>' : '<span class="badge badge-pending">Missing</span>' ?></td>
        <td><a class="btn btn-sm" href="<?= admin_url('product-form.php?id=' . (int)$p['id']) ?>">Edit SEO</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div>
    <div class="card">
      <h3>Categories — Meta Titles</h3>
      <table class="table">
        <tr><th>Category</th><th>Meta Title</th><th></th></tr>
        <?php foreach ($categories as $c): ?>
        <tr>
          <td><?= e($c['name']) ?></td>
          <td><?= $c['meta_title'] ? '<span class="badge badge-approved">Set</span>' : '<span class="badge badge-pending">Missing</span>' ?></td>
          <td><a class="btn btn-sm" href="<?= admin_url('categories.php?edit=' . (int)$c['id']) ?>">Edit SEO</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div class="card">
      <h3>Pages — Meta Titles</h3>
      <table class="table">
        <tr><th>Page</th><th>Meta Title</th><th></th></tr>
        <?php foreach ($pages as $pg): ?>
        <tr>
          <td><?= e($pg['title']) ?></td>
          <td><?= $pg['meta_title'] ? '<span class="badge badge-approved">Set</span>' : '<span class="badge badge-pending">Missing</span>' ?></td>
          <td><a class="btn btn-sm" href="<?= admin_url('page-form.php?id=' . (int)$pg['id']) ?>">Edit SEO</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
