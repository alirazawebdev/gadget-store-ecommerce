<?php
/**
 * Admin dashboard — ShopMax-style overview with real store data.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Dashboard';
if (!can('dashboard')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$notCancelled = "status NOT IN ('cancelled','returned')";

$totalRevenue = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $notCancelled")->fetchColumn();
$totalOrders  = (int)db()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalCustomers = (int)db()->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$pendingOrders  = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$todayRev = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $notCancelled AND DATE(created_at) = CURDATE()")->fetchColumn();
$lowStockCount = (int)db()->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock <= low_stock_alert")->fetchColumn();

/* 7-day sales */
$sales = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $sales[$d] = 0;
}
$rows = db()->query(
    "SELECT DATE(created_at) AS d, SUM(total) AS t FROM orders
     WHERE $notCancelled AND created_at >= CURDATE() - INTERVAL 6 DAY
     GROUP BY d"
)->fetchAll();
foreach ($rows as $r) {
    if (isset($sales[$r['d']])) $sales[$r['d']] = (float)$r['t'];
}
$chLabels = []; $chData = [];
foreach ($sales as $d => $t) {
    $chLabels[] = date('D', strtotime($d));
    $chData[] = $t;
}

/* orders by status */
$byStatus = db()->query(
    "SELECT status, COUNT(*) AS c FROM orders GROUP BY status ORDER BY c DESC"
)->fetchAll();
$stLabels = []; $stData = [];
$stColors = ['pending' => '#f5a623', 'confirmed' => '#3b82f6', 'processing' => '#a855f7',
             'dispatched' => '#22d3ee', 'delivered' => '#22c55e', 'cancelled' => '#f05252', 'returned' => '#8f97c4'];
$stBg = [];
foreach ($byStatus as $s) {
    $stLabels[] = ucfirst(str_replace('_', ' ', $s['status']));
    $stData[] = (int)$s['c'];
    $stBg[] = $stColors[$s['status']] ?? '#5b6478';
}

/* top products with revenue */
$top = db()->query(
    "SELECT oi.product_name, SUM(oi.qty) AS q, SUM(oi.total) AS rev
     FROM order_items oi
     GROUP BY oi.product_id, oi.product_name
     ORDER BY q DESC LIMIT 5"
)->fetchAll();

$recent = db()->query(
    "SELECT o.*, COUNT(oi.id) AS items_count
     FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll();

$low = db()->query(
    "SELECT id, name, sku, stock, low_stock_alert FROM products
     WHERE status = 'active' AND stock <= low_stock_alert
     ORDER BY stock ASC LIMIT 8"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="welcome">
  <div>
    <h2>Welcome back, <?= e(strtok((string)$admin['name'], ' ')) ?>! <span class="wave">👋</span></h2>
    <p>Here's what's happening with your store today — <?= e(date('l, F j, Y')) ?></p>
  </div>
  <div class="qa-row">
    <a class="qa" href="<?= admin_url('product-form.php') ?>">➕ New Product</a>
    <a class="qa" href="<?= admin_url('orders.php') ?>">🧾 View Orders</a>
    <a class="qa" href="<?= admin_url('reports.php') ?>">📈 Reports</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card" style="--sc:#22c55e">
    <span class="stat-ico">💰</span>
    <div><div class="num"><?= format_price($totalRevenue) ?></div><div class="lbl">Total Sales</div></div>
  </div>
  <div class="stat-card" style="--sc:#4f8cff">
    <span class="stat-ico">🧾</span>
    <div><div class="num"><?= number_format($totalOrders) ?></div><div class="lbl">Total Orders</div></div>
  </div>
  <div class="stat-card" style="--sc:#a855f7">
    <span class="stat-ico">👥</span>
    <div><div class="num"><?= number_format($totalCustomers) ?></div><div class="lbl">Customers</div></div>
  </div>
  <div class="stat-card" style="--sc:#f5a623">
    <span class="stat-ico">⏳</span>
    <div><div class="num"><?= number_format($pendingOrders) ?></div><div class="lbl">Pending Orders</div></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>📊 Sales Overview <span class="more">Last 7 days</span></h3>
    <div class="chart-box"><canvas id="salesChart"></canvas></div>
  </div>
  <div class="card">
    <h3>🍩 Orders by Status</h3>
    <div class="chart-box sm"><canvas id="statusChart"></canvas></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>🏆 Top Products <a class="more" href="<?= admin_url('reports.php') ?>">View All</a></h3>
    <?php if ($top): ?>
    <div class="table-wrap"><table class="table">
      <tr><th>Product</th><th class="r">Sold</th><th class="r">Revenue</th></tr>
      <?php foreach ($top as $t): ?>
      <tr>
        <td><?= e($t['product_name']) ?></td>
        <td class="r"><strong><?= (int)$t['q'] ?></strong></td>
        <td class="r"><?= format_price($t['rev']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php else: ?><p class="status">No sales yet.</p><?php endif; ?>
  </div>
  <div class="card">
    <h3>🕐 Recent Orders <a class="more" href="<?= admin_url('orders.php') ?>">View All</a></h3>
    <?php if ($recent): ?>
    <div class="table-wrap"><table class="table">
      <tr><th>Order</th><th>Customer</th><th class="r">Total</th><th>Status</th></tr>
      <?php foreach ($recent as $o): ?>
      <tr>
        <td><a href="<?= admin_url('order-view.php?id=' . (int)$o['id']) ?>"><strong><?= e($o['order_no']) ?></strong></a></td>
        <td><?= e($o['name']) ?></td>
        <td class="r"><?= format_price($o['total']) ?></td>
        <td><?= status_badge($o['status']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php else: ?><p class="status">No orders yet.</p><?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>💵 Today's Snapshot</h3>
    <div class="kv">
      <dt>Today's revenue</dt><dd><strong><?= format_price($todayRev) ?></strong></dd>
      <dt>Low stock products</dt><dd><strong style="color:<?= $lowStockCount ? '#f5a623' : '#22c55e' ?>"><?= (int)$lowStockCount ?></strong></dd>
      <dt>Avg. order value</dt><dd><strong><?= format_price($totalOrders ? $totalRevenue / $totalOrders : 0) ?></strong></dd>
    </div>
  </div>
  <?php if ($low): ?>
  <div class="card">
    <h3>⚠️ Low Stock Alert <a class="more" href="<?= admin_url('products.php') ?>">Manage</a></h3>
    <div class="table-wrap"><table class="table">
      <tr><th>Product</th><th class="r">Stock</th><th></th></tr>
      <?php foreach ($low as $p): ?>
      <tr>
        <td><?= e($p['name']) ?></td>
        <td class="r"><strong style="color:#f05252"><?= (int)$p['stock'] ?></strong></td>
        <td class="r"><a class="btn btn-sm" href="<?= admin_url('product-form.php?id=' . (int)$p['id']) ?>">Restock</a></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
  </div>
  <?php endif; ?>
</div>

<script>
Chart.defaults.color = '#8f97c4';
Chart.defaults.borderColor = 'rgba(255,255,255,.07)';
Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";

new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chLabels) ?>,
    datasets: [{
      label: 'Revenue (Rs.)',
      data: <?= json_encode($chData) ?>,
      borderColor: '#f26522', borderWidth: 3, tension: .45, fill: true,
      backgroundColor: (c) => {
        const g = c.chart.ctx.createLinearGradient(0, 0, 0, 280);
        g.addColorStop(0, 'rgba(242,101,34,.45)'); g.addColorStop(1, 'rgba(242,101,34,0)');
        return g;
      },
      pointBackgroundColor: '#fff', pointBorderColor: '#f26522', pointRadius: 4
    }]
  },
  options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs.' + (v >= 1000 ? (v/1000) + 'k' : v) } } } }
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($stLabels) ?>,
    datasets: [{ data: <?= json_encode($stData) ?>, backgroundColor: <?= json_encode($stBg) ?>, borderWidth: 3, borderColor: '#141b3d' }]
  },
  options: { maintainAspectRatio: false, cutout: '62%',
    plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 12 } } } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
