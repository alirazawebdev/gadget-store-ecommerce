<?php
/**
 * Sales reports — date range, revenue, profit, daily chart.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Reports';
if (!can('reports')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$to   = $_GET['to'] ?? date('Y-m-d');
if (!strtotime($from)) $from = date('Y-m-d', strtotime('-29 days'));
if (!strtotime($to)) $to = date('Y-m-d');
$from = date('Y-m-d', strtotime($from));
$to = date('Y-m-d', strtotime($to));
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

$st = db()->prepare(
    "SELECT COALESCE(SUM(total),0) AS rev, COUNT(*) AS cnt FROM orders
     WHERE status NOT IN ('cancelled','returned') AND DATE(created_at) BETWEEN ? AND ?"
);
$st->execute([$from, $to]);
$s = $st->fetch();
$revenue = (float)$s['rev'];
$orderCount = (int)$s['cnt'];
$avgOrder = $orderCount > 0 ? $revenue / $orderCount : 0;

$st = db()->prepare(
    "SELECT COALESCE(SUM((oi.price - COALESCE(p.purchase_price,0)) * oi.qty),0) AS profit
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE o.status NOT IN ('cancelled','returned') AND DATE(o.created_at) BETWEEN ? AND ?"
);
$st->execute([$from, $to]);
$profit = (float)$st->fetchColumn();

/* daily breakdown (fill gaps) */
$days = [];
$d = $from;
while ($d <= $to) {
    $days[$d] = ['rev' => 0, 'cnt' => 0];
    $d = date('Y-m-d', strtotime($d . ' +1 day'));
}
$st = db()->prepare(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c, COALESCE(SUM(total),0) AS t FROM orders
     WHERE status NOT IN ('cancelled','returned') AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY d ORDER BY d"
);
$st->execute([$from, $to]);
foreach ($st->fetchAll() as $r) {
    if (isset($days[$r['d']])) $days[$r['d']] = ['rev' => (float)$r['t'], 'cnt' => (int)$r['c']];
}
$chLabels = [];
$chData = [];
foreach ($days as $d => $v) {
    $chLabels[] = date('M j', strtotime($d));
    $chData[] = $v['rev'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Sales Reports</h1>
</div>

<div class="card">
  <form method="get" action="" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <div class="form-group" style="margin:0">
      <label>From</label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label>To</label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <button class="btn" type="submit">Generate</button>
  </form>
</div>

<div class="stat-grid">
  <div class="stat"><div class="num"><?= format_price($revenue) ?></div><div class="lbl">Revenue</div></div>
  <div class="stat"><div class="num"><?= $orderCount ?></div><div class="lbl">Orders</div></div>
  <div class="stat"><div class="num"><?= format_price($avgOrder) ?></div><div class="lbl">Avg. Order Value</div></div>
  <div class="stat"><div class="num"><?= format_price($profit) ?></div><div class="lbl">Est. Profit <small style="font-weight:400">(sale − purchase)</small></div></div>
</div>

<div class="card">
  <h3>Daily Revenue — <?= e(date('M j, Y', strtotime($from))) ?> to <?= e(date('M j, Y', strtotime($to))) ?></h3>
  <canvas id="revChart" height="90"></canvas>
</div>

<div class="card">
  <h3>Daily Breakdown</h3>
  <table class="table">
    <tr><th>Date</th><th>Orders</th><th style="text-align:right">Revenue</th></tr>
    <?php foreach ($days as $d => $v): ?>
    <tr>
      <td><?= e(date('D, M j, Y', strtotime($d))) ?></td>
      <td><?= $v['cnt'] ?></td>
      <td style="text-align:right"><?= format_price($v['rev']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
new Chart(document.getElementById('revChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chLabels) ?>,
    datasets: [{ label: 'Revenue (Rs.)', data: <?= json_encode($chData) ?>,
                 borderColor: '#f26522', backgroundColor: 'rgba(242,101,34,.15)', fill: true, tension: .3 }]
  },
  options: { scales: { y: { beginAtZero: true } } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
