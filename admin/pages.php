<?php
/**
 * CMS pages list.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Pages';
if (!can('pages')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- delete (POST only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    $st = db()->prepare("SELECT title FROM pages WHERE id = ?");
    $st->execute([$did]);
    if ($row = $st->fetch()) {
        db()->prepare("DELETE FROM pages WHERE id = ?")->execute([$did]);
        log_activity("Deleted page: {$row['title']} (id $did)");
        flash('success', 'Page deleted.');
    }
    redirect(admin_url('pages.php'));
}

$pages = db()->query("SELECT * FROM pages ORDER BY title")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>CMS Pages</h1>
  <a class="btn" href="<?= admin_url('page-form.php') ?>">+ Add Page</a>
</div>

<div class="card">
  <?php if ($pages): ?>
  <table class="table">
    <tr><th>Title</th><th>Slug</th><th>Status</th><th>Updated</th><th></th></tr>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td><strong><?= e($p['title']) ?></strong></td>
      <td><small style="color:#6b7280">/<?= e($p['slug']) ?></small></td>
      <td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
      <td><?= e(date('M j, Y', strtotime($p['updated_at']))) ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn-sm" href="<?= admin_url('page-form.php?id=' . (int)$p['id']) ?>">Edit</a>
        <a class="btn btn-sm btn-gray" href="<?= base_url('page.php?slug=' . urlencode($p['slug'])) ?>" target="_blank">View</a>
        <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this page?')">
          <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>No pages yet. Create About Us, Contact, Privacy Policy, Shipping & Returns here.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
