<?php
/**
 * Sale popups management. Only the latest active popup shows on the site.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Popups';
if (!can('popups')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = db()->prepare("SELECT * FROM popups WHERE id = ?");
    $st->execute([$editId]);
    $edit = $st->fetch();
    if (!$edit) redirect(admin_url('popups.php'));
}

/* ---------- save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_popup'])) {
    $pid         = (int)($_POST['id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $content     = trim($_POST['content'] ?? ''); // raw HTML allowed
    $button_text = trim($_POST['button_text'] ?? '');
    $button_link = trim($_POST['button_link'] ?? '');
    $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $image = null;
    if ($pid > 0) {
        $old = db()->prepare("SELECT image FROM popups WHERE id = ?");
        $old->execute([$pid]);
        $image = $old->fetchColumn();
    }
    $up = upload_image($_FILES['image'] ?? [], 'popups');
    if ($up) {
        if (!empty($image)) {
            $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($image, '/');
            if (is_file($f)) @unlink($f);
        }
        $image = $up;
    }

    if ($pid > 0) {
        db()->prepare("UPDATE popups SET title=?, image=?, content=?, button_text=?, button_link=?, status=? WHERE id=?")
          ->execute([$title, $image, $content, $button_text, $button_link, $status, $pid]);
        log_activity("Updated popup: $title (id $pid)");
        flash('success', 'Popup updated.');
    } else {
        db()->prepare("INSERT INTO popups (title, image, content, button_text, button_link, status) VALUES (?,?,?,?,?,?)")
          ->execute([$title, $image, $content, $button_text, $button_link, $status]);
        log_activity("Added popup: $title");
        flash('success', 'Popup added.');
    }
    redirect(admin_url('popups.php'));
}

/* ---------- delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    $st = db()->prepare("SELECT title, image FROM popups WHERE id = ?");
    $st->execute([$did]);
    if ($row = $st->fetch()) {
        if (!empty($row['image'])) {
            $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($row['image'], '/');
            if (is_file($f)) @unlink($f);
        }
        db()->prepare("DELETE FROM popups WHERE id = ?")->execute([$did]);
        log_activity("Deleted popup: {$row['title']} (id $did)");
        flash('success', 'Popup deleted.');
    }
    redirect(admin_url('popups.php'));
}

$popups = db()->query("SELECT * FROM popups ORDER BY id DESC")->fetchAll();
$v = function ($k, $d = '') use ($edit) {
    return $edit ? $edit[$k] : $d;
};
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Sale Popups</h1>
  <?php if ($edit): ?><a class="btn btn-gray" href="<?= admin_url('popups.php') ?>">+ New Popup</a><?php endif; ?>
</div>

<div class="card">
  <p style="color:#6b7280;font-size:13px">ℹ️ Only the <strong>latest active</strong> popup is shown on the site homepage.</p>
</div>

<div class="grid-2">
  <div class="card">
    <h3><?= $edit ? 'Edit Popup' : 'Add Popup' ?></h3>
    <form method="post" action="" enctype="multipart/form-data">
      <input type="hidden" name="save_popup" value="1">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" class="form-control" value="<?= e($v('title')) ?>">
      </div>
      <div class="form-group">
        <label>Image</label>
        <?php if ($edit && $edit['image']): ?>
        <img src="<?= e(img_url($edit['image'])) ?>" style="max-width:200px;border-radius:6px;display:block;margin-bottom:8px">
        <?php endif; ?>
        <input type="file" name="image" class="form-control" accept="image/*">
      </div>
      <div class="form-group">
        <label>Content (HTML allowed)</label>
        <textarea name="content" class="form-control"><?= e($v('content')) ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Button Text</label>
          <input type="text" name="button_text" class="form-control" value="<?= e($v('button_text', 'Shop Now')) ?>">
        </div>
        <div class="form-group">
          <label>Button Link</label>
          <input type="text" name="button_link" class="form-control" value="<?= e($v('button_link')) ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <button class="btn" type="submit"><?= $edit ? 'Save Changes' : 'Add Popup' ?></button>
    </form>
  </div>

  <div class="card">
    <h3>All Popups</h3>
    <?php if ($popups): ?>
    <table class="table">
      <tr><th></th><th>Title</th><th>Status</th><th></th></tr>
      <?php foreach ($popups as $p): ?>
      <tr>
        <td><?php if ($p['image']): ?><img class="thumb" src="<?= e(img_url($p['image'])) ?>" alt=""><?php endif; ?></td>
        <td><strong><?= e($p['title']) ?: '—' ?></strong></td>
        <td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm" href="<?= admin_url('popups.php?edit=' . (int)$p['id']) ?>">Edit</a>
          <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this popup?')">
            <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No popups yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
