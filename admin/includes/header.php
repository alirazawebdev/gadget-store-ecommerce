<?php
/**
 * Admin layout header + grouped sidebar + topbar. Requires auth.php first ($admin, $currentPage).
 */
$menuGroups = [
    'Main' => [
        'index'      => ['Dashboard', 'dashboard', '📊'],
        'orders'     => ['Orders', 'orders', '🧾'],
        'payment-settings' => ['Payments', 'settings', '💳'],
        'products'   => ['Products', 'products', '📦'],
        'categories' => ['Categories', 'categories', '🗂️'],
        'customers'  => ['Customers', 'customers', '👥'],
    ],
    'Marketing' => [
        'coupons'     => ['Coupons', 'coupons', '🎟️'],
        'sales-boost' => ['Sales Boost', 'products', '🚀'],
        'sliders'     => ['Sliders', 'sliders', '🖼️'],
        'popups'      => ['Popups', 'popups', '🪟'],
        'reviews'     => ['Reviews', 'reviews', '⭐'],
        'testimonials'=> ['Testimonials', 'reviews', '💭'],
    ],
    'Content' => [
        'pages' => ['Pages / CMS', 'pages', '📄'],
        'seo'   => ['SEO', 'settings', '🔍'],
    ],
    'Insights' => [
        'reports'   => ['Reports', 'reports', '📈'],
        'stock-log' => ['Stock Log', 'reports', '📉'],
        'messages'  => ['Messages', 'messages', '✉️'],
    ],
    'System' => [
        'users'        => ['Users & Roles', 'users', '👤'],
        'import'       => ['Import CSV', 'products', '📥'],
        'backup'       => ['Backup', 'settings', '💾'],
        'activity-log' => ['Activity Log', 'users', '📝'],
        'settings'     => ['Settings', 'settings', '⚙️'],
    ],
];
$cssVer = @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: time();
$initials = strtoupper(substr((string)($admin['name'] ?? 'A'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($adminTitle ?? 'Admin') ?> | <?= e(site_name()) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= (int)$cssVer ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="admin-wrap">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><span class="b-logo">G</span><span><?= e(site_name()) ?></span></div>
    <nav class="side-nav">
      <?php foreach ($menuGroups as $gLabel => $items): ?>
        <?php
          $visible = array_filter($items, function ($m) { return can($m[1]); });
          if (!$visible) continue;
        ?>
        <div class="nav-group">
          <div class="nav-label"><?= e($gLabel) ?></div>
          <?php foreach ($visible as $file => $m): ?>
            <a href="<?= admin_url($file . '.php') ?>" class="nav-link <?= $currentPage === $file ? 'active' : '' ?>">
              <span class="ico"><?= $m[2] ?></span><?= e($m[0]) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <div class="u-row">
        <span class="avatar"><?= e($initials) ?></span>
        <span><?= e($admin['name']) ?><br><small style="color:var(--muted2)"><?= e($admin['role_name'] ?? '—') ?></small></span>
      </div>
      <div class="lnks">
        <a href="<?= base_url() ?>" target="_blank">🌐 View Site</a>
        <a href="<?= admin_url('logout.php') ?>">⏻ Logout</a>
      </div>
    </div>
  </aside>
  <div class="admin-main">
    <div class="topbar no-print">
      <button class="burger" id="burger" aria-label="Menu">☰</button>
      <h1><?= e($adminTitle ?? 'Dashboard') ?></h1>
      <form class="tb-search" method="get" action="<?= admin_url('orders.php') ?>">
        <input type="text" name="q" placeholder="Search orders by ID, phone, name…">
        <button type="submit" aria-label="Search">🔍</button>
      </form>
      <div class="tb-right">
        <span class="tb-chip"><span class="avatar"><?= e($initials) ?></span><?= e($admin['name']) ?></span>
      </div>
    </div>
    <div class="content">
