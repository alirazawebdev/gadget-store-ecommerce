<?php
/**
 * Staff activity log.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Activity Log';
if (!can('users')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;

$total = (int)db()->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$offset = ($page - 1) * $per;

$logs = db()->query(
    "SELECT a.*, u.name AS user_name FROM activity_log a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC LIMIT $per OFFSET $offset"
)->fetchAll();

$base = admin_url('activity-log.php');

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Activity Log (<?= $total ?>)</h1>
</div>

<div class="card">
  <?php if ($logs): ?>
  <table class="table">
    <tr><th>Date</th><th>User</th><th>Action</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td style="white-space:nowrap"><?= e(date('M j, Y h:i A', strtotime($l['created_at']))) ?></td>
      <td><?= e($l['user_name']) ?: '<small style="color:#6b7280">system</small>' ?></td>
      <td><?= e($l['action']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?= pagination($total, $per, $page, $base) ?>
  <?php else: ?>
  <p>No activity recorded yet.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
