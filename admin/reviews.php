<?php
/**
 * Product reviews moderation — pending first.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Reviews';
if (!can('reviews')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- actions (POST only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['review_id'])) {
    $rid = (int)$_POST['review_id'];
    $act = $_POST['action'];
    $st = db()->prepare("SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id WHERE r.id = ?");
    $st->execute([$rid]);
    $r = $st->fetch();
    if ($r) {
        if ($act === 'approve' || $act === 'reject') {
            $ns = $act === 'approve' ? 'approved' : 'rejected';
            db()->prepare("UPDATE reviews SET status = ? WHERE id = ?")->execute([$ns, $rid]);
            log_activity("Review $ns for '{$r['product_name']}' by {$r['name']}");
            flash('success', "Review $ns.");
        } elseif ($act === 'delete') {
            db()->prepare("DELETE FROM reviews WHERE id = ?")->execute([$rid]);
            log_activity("Deleted review by {$r['name']} on '{$r['product_name']}'");
            flash('success', 'Review deleted.');
        }
    }
    redirect(admin_url('reviews.php'));
}

$reviews = db()->query(
    "SELECT r.*, p.name AS product_name FROM reviews r
     LEFT JOIN products p ON p.id = r.product_id
     ORDER BY (r.status = 'pending') DESC, r.created_at DESC"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Product Reviews</h1>
</div>

<div class="card">
  <?php if ($reviews): ?>
  <table class="table">
    <tr><th>Product</th><th>Reviewer</th><th>Rating</th><th>Comment</th><th>Status</th><th>Date</th><th></th></tr>
    <?php foreach ($reviews as $r): ?>
    <tr>
      <td><?= e($r['product_name']) ?: '—' ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
      <td><?= e(substr($r['comment'], 0, 120)) ?></td>
      <td><span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
      <td style="white-space:nowrap">
        <form method="post" action="" style="display:inline">
          <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
          <?php if ($r['status'] !== 'approved'): ?>
          <button class="btn btn-sm btn-green" type="submit" name="action" value="approve">Approve</button>
          <?php endif; ?>
          <?php if ($r['status'] !== 'rejected'): ?>
          <button class="btn btn-sm btn-gray" type="submit" name="action" value="reject">Reject</button>
          <?php endif; ?>
          <button class="btn btn-sm btn-danger" type="submit" name="action" value="delete"
                  onclick="return confirm('Delete this review?')">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>No reviews yet.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
