<?php
/**
 * XML sitemap — home, active categories, products, pages.
 */
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= e(base_url()) ?></loc></url>
  <?php foreach (active_categories() as $c): ?>
  <url><loc><?= e(category_url($c)) ?></loc></url>
  <?php endforeach; ?>
  <?php foreach (db()->query("SELECT slug FROM products WHERE status = 'active' ORDER BY id")->fetchAll() as $r): ?>
  <url><loc><?= e(base_url('product.php?slug=' . urlencode($r['slug']))) ?></loc></url>
  <?php endforeach; ?>
  <?php foreach (db()->query("SELECT slug FROM pages WHERE status = 'active' ORDER BY id")->fetchAll() as $r): ?>
  <url><loc><?= e(base_url('page.php?slug=' . urlencode($r['slug']))) ?></loc></url>
  <?php endforeach; ?>
</urlset>
