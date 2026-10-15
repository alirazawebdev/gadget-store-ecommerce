<?php
/**
 * Contact inbox — new messages first.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Messages';
if (!can('messages')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- actions (POST only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['msg_id'])) {
    $mid = (int)$_POST['msg_id'];
    if ($_POST['action'] === 'read') {
        db()->prepare("UPDATE messages SET status = 'read' WHERE id = ?")->execute([$mid]);
    } elseif ($_POST['action'] === 'delete') {
        db()->prepare("DELETE FROM messages WHERE id = ?")->execute([$mid]);
        log_activity("Deleted contact message (id $mid)");
        flash('success', 'Message deleted.');
    }
    redirect(admin_url('messages.php'));
}

$msgs = db()->query(
    "SELECT * FROM messages ORDER BY (status = 'new') DESC, created_at DESC"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Contact Messages</h1>
</div>

<div class="card">
  <?php if ($msgs): ?>
  <table class="table">
    <tr><th>Name</th><th>Contact</th><th>Message</th><th>Status</th><th>Date</th><th></th></tr>
    <?php foreach ($msgs as $m): ?>
    <tr>
      <td><strong><?= e($m['name']) ?></strong></td>
      <td><?= e($m['phone']) ?><br><small style="color:#6b7280"><?= e($m['email']) ?></small></td>
      <td><?= e(substr($m['message'], 0, 150)) ?><?= strlen($m['message']) > 150 ? '…' : '' ?></td>
      <td><span class="badge badge-<?= e($m['status']) ?>"><?= e(ucfirst($m['status'])) ?></span></td>
      <td><?= e(date('M j, Y h:i A', strtotime($m['created_at']))) ?></td>
      <td style="white-space:nowrap">
        <form method="post" action="" style="display:inline">
          <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
          <?php if ($m['status'] === 'new'): ?>
          <button class="btn btn-sm btn-green" type="submit" name="action" value="read">Mark Read</button>
          <?php endif; ?>
          <button class="btn btn-sm btn-danger" type="submit" name="action" value="delete"
                  onclick="return confirm('Delete this message?')">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>No messages yet.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
