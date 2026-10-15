<?php
/**
 * Homepage sliders / banners management.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Sliders';
if (!can('sliders')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = db()->prepare("SELECT * FROM sliders WHERE id = ?");
    $st->execute([$editId]);
    $edit = $st->fetch();
    if (!$edit) redirect(admin_url('sliders.php'));
}

/* ---------- save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_slider'])) {
    $sid        = (int)($_POST['id'] ?? 0);
    $title      = trim($_POST['title'] ?? '');
    $subtitle   = trim($_POST['subtitle'] ?? '');
    $link       = trim($_POST['link'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $status     = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $image = $mobile_image = null;
    if ($sid > 0) {
        $old = db()->prepare("SELECT image, mobile_image FROM sliders WHERE id = ?");
        $old->execute([$sid]);
        $row = $old->fetch();
        $image = $row['image'] ?? null;
        $mobile_image = $row['mobile_image'] ?? '';
    }
    $up = upload_image($_FILES['image'] ?? [], 'sliders');
    if ($up) {
        if (!empty($image)) {
            $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($image, '/');
            if (is_file($f)) @unlink($f);
        }
        $image = $up;
    }
    $upm = upload_image($_FILES['mobile_image'] ?? [], 'sliders');
    if ($upm) {
        if (!empty($mobile_image)) {
            $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($mobile_image, '/');
            if (is_file($f)) @unlink($f);
        }
        $mobile_image = $upm;
    }

    if ($sid === 0 && !$image) {
        flash('error', 'Banner image is required for a new slider.');
        redirect(admin_url('sliders.php'));
    }

    if ($sid > 0) {
        db()->prepare("UPDATE sliders SET title=?, subtitle=?, image=?, mobile_image=?, link=?, sort_order=?, status=? WHERE id=?")
          ->execute([$title, $subtitle, $image, $mobile_image, $link, $sort_order, $status, $sid]);
        log_activity("Updated slider: $title (id $sid)");
        flash('success', 'Slider updated.');
    } else {
        db()->prepare("INSERT INTO sliders (title, subtitle, image, mobile_image, link, sort_order, status) VALUES (?,?,?,?,?,?,?)")
          ->execute([$title, $subtitle, $image, $mobile_image, $link, $sort_order, $status]);
        log_activity("Added slider: $title");
        flash('success', 'Slider added.');
    }
    redirect(admin_url('sliders.php'));
}

/* ---------- delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    $st = db()->prepare("SELECT title, image, mobile_image FROM sliders WHERE id = ?");
    $st->execute([$did]);
    if ($row = $st->fetch()) {
        foreach (['image', 'mobile_image'] as $col) {
            if (!empty($row[$col])) {
                $f = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($row[$col], '/');
                if (is_file($f)) @unlink($f);
            }
        }
        db()->prepare("DELETE FROM sliders WHERE id = ?")->execute([$did]);
        log_activity("Deleted slider: {$row['title']} (id $did)");
        flash('success', 'Slider deleted.');
    }
    redirect(admin_url('sliders.php'));
}

$sliders = db()->query("SELECT * FROM sliders ORDER BY sort_order, id")->fetchAll();
$v = function ($k, $d = '') use ($edit) {
    return $edit ? $edit[$k] : $d;
};
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Homepage Sliders</h1>
  <?php if ($edit): ?><a class="btn btn-gray" href="<?= admin_url('sliders.php') ?>">+ New Slider</a><?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h3><?= $edit ? 'Edit Slider' : 'Add Slider' ?></h3>
    <form method="post" action="" enctype="multipart/form-data">
      <input type="hidden" name="save_slider" value="1">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" class="form-control" value="<?= e($v('title')) ?>">
      </div>
      <div class="form-group">
        <label>Subtitle</label>
        <input type="text" name="subtitle" class="form-control" value="<?= e($v('subtitle')) ?>">
      </div>
      <div class="form-group">
        <label>Banner Image<?= $edit ? '' : ' *' ?></label>
        <?php if ($edit && $edit['image']): ?>
        <img src="<?= e(img_url($edit['image'])) ?>" style="max-width:100%;border-radius:6px;display:block;margin-bottom:8px">
        <?php endif; ?>
        <input type="file" name="image" class="form-control" accept="image/*" <?= $edit ? '' : 'required' ?>>
        <small style="color:#6b7280">Wide full-screen banner recommended (e.g. 1920×600).</small>
      </div>
      <div class="form-group">
        <label>Mobile Banner Image (optional)</label>
        <?php if ($edit && !empty($edit['mobile_image'])): ?>
        <img src="<?= e(img_url($edit['mobile_image'])) ?>" style="max-width:220px;border-radius:6px;display:block;margin-bottom:8px">
        <?php endif; ?>
        <input type="file" name="mobile_image" class="form-control" accept="image/*">
        <small style="color:#6b7280">Portrait banner for phones (e.g. 800×1000). If empty, the desktop banner is used.</small>
      </div>
      <div class="form-group">
        <label>Link (optional)</label>
        <input type="text" name="link" class="form-control" placeholder="/category.php?slug=..." value="<?= e($v('link')) ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= e($v('sort_order', '0')) ?>">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <button class="btn" type="submit"><?= $edit ? 'Save Changes' : 'Add Slider' ?></button>
    </form>
  </div>

  <div class="card">
    <h3>All Sliders</h3>
    <?php if ($sliders): ?>
    <table class="table">
      <tr><th></th><th>Title</th><th>Order</th><th>Status</th><th></th></tr>
      <?php foreach ($sliders as $s): ?>
      <tr>
        <td><img class="thumb" style="width:90px" src="<?= e(img_url($s['image'])) ?>" alt=""></td>
        <td><strong><?= e($s['title']) ?: '—' ?></strong><br><small style="color:#6b7280"><?= e($s['subtitle']) ?></small></td>
        <td><?= (int)$s['sort_order'] ?></td>
        <td><span class="badge badge-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm" href="<?= admin_url('sliders.php?edit=' . (int)$s['id']) ?>">Edit</a>
          <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this slider?')">
            <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No sliders yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
