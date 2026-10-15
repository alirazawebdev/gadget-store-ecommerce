<?php
/**
 * Customers list — searchable, repeat-customer badge.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Customers';
if (!can('customers')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db()->prepare("SELECT COUNT(*) FROM customers $whereSql");
$total->execute($params);
$total = (int)$total->fetchColumn();

$offset = ($page - 1) * $per;
$st = db()->prepare("SELECT * FROM customers $whereSql ORDER BY total_spent DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$customers = $st->fetchAll();

$base = admin_url('customers.php') . '?' . http_build_query(array_filter(['q' => $search]));

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Customers (<?= $total ?>)</h1>
</div>

<div class="card">
  <form method="get" action="" style="display:flex;gap:10px">
    <input type="text" name="q" class="form-control" style="width:300px" placeholder="Search name, phone, email, city..."
           value="<?= e($search) ?>">
    <button class="btn" type="submit">Search</button>
    <?php if ($search !== ''): ?>
    <a class="btn btn-gray" href="<?= admin_url('customers.php') ?>">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if ($customers): ?>
  <table class="table">
    <tr><th>Name</th><th>Phone</th><th>City</th><th>Orders</th><th>Total Spent</th><th></th></tr>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td><strong><?= e($c['name']) ?></strong>
        <?php if ((int)$c['total_orders'] > 1): ?><span class="badge badge-confirmed">Repeat</span><?php endif; ?>
      </td>
      <td><?= e($c['phone']) ?></td>
      <td><?= e($c['city']) ?></td>
      <td><?= (int)$c['total_orders'] ?></td>
      <td><?= format_price($c['total_spent']) ?></td>
      <td><a class="btn btn-sm" href="<?= admin_url('customer-view.php?id=' . (int)$c['id']) ?>">View</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?= pagination($total, $per, $page, $base) ?>
  <?php else: ?>
  <p>No customers found.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
