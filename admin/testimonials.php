<?php
/**
 * Customer testimonials management — shown in the homepage slider.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Testimonials';
if (!can('reviews')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- actions (POST only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];
    if ($act === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $text   = trim($_POST['review_text'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $sort   = (int)($_POST['sort_order'] ?? 0);
        if ($name === '' || $text === '') {
            flash('error', 'Name and review text are required.');
        } else {
            $photo = null;
            if (!empty($_FILES['photo']['name'])) {
                $photo = upload_image($_FILES['photo'], 'testimonials');
                if ($photo === null) flash('error', 'Photo upload failed (jpg/png/webp only).');
            }
            if ($id > 0) {
                $old = db()->prepare("SELECT photo FROM testimonials WHERE id = ?");
                $old->execute([$id]);
                $oldPhoto = $old->fetchColumn();
                if ($photo) {
                    db()->prepare("UPDATE testimonials SET name=?, photo=?, rating=?, review_text=?, status=?, sort_order=? WHERE id=?")
                        ->execute([$name, $photo, $rating, $text, $status, $sort, $id]);
                } else {
                    db()->prepare("UPDATE testimonials SET name=?, rating=?, review_text=?, status=?, sort_order=? WHERE id=?")
                        ->execute([$name, $rating, $text, $status, $sort, $id]);
                }
                log_activity("Updated testimonial by '$name'");
                flash('success', 'Testimonial updated.');
            } else {
                db()->prepare("INSERT INTO testimonials (name, photo, rating, review_text, status, sort_order) VALUES (?,?,?,?,?,?)")
                    ->execute([$name, $photo, $rating, $text, $status, $sort]);
                log_activity("Added testimonial by '$name'");
                flash('success', 'Testimonial added.');
            }
        }
        redirect(admin_url('testimonials.php'));
    } elseif ($act === 'toggle' && isset($_POST['testimonial_id'])) {
        $tid = (int)$_POST['testimonial_id'];
        db()->prepare("UPDATE testimonials SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$tid]);
        flash('success', 'Status updated.');
        redirect(admin_url('testimonials.php'));
    } elseif ($act === 'delete' && isset($_POST['testimonial_id'])) {
        $tid = (int)$_POST['testimonial_id'];
        db()->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$tid]);
        log_activity("Deleted testimonial #$tid");
        flash('success', 'Testimonial deleted.');
        redirect(admin_url('testimonials.php'));
    }
}

/* ---------- edit form data ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare("SELECT * FROM testimonials WHERE id = ?");
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

$testimonials = db()->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Customer Testimonials</h1>
  <p>These appear in the auto-scrolling reviews slider above the footer on the homepage.</p>
</div>

<div class="card">
  <h3><?= $edit ? 'Edit Testimonial' : 'Add Testimonial' ?></h3>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Customer Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Rating</label>
        <select name="rating" class="form-control">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= (($edit['rating'] ?? 5) == $i) ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Review Text *</label>
      <textarea name="review_text" class="form-control" rows="3" required><?= e($edit['review_text'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Customer Photo <small>(optional — shows first letter if empty)</small></label>
        <input type="file" name="photo" class="form-control" accept="image/*">
        <?php if (!empty($edit['photo'])): ?>
          <div style="margin-top:8px"><img src="<?= e(img_url($edit['photo'])) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:50%"></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="active" <?= (($edit['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active (shows on site)</option>
          <option value="inactive" <?= (($edit['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive (hidden)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Sort Order</label>
        <input type="number" name="sort_order" class="form-control" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
      </div>
    </div>
    <button class="btn" type="submit"><?= $edit ? 'Update' : 'Add' ?> Testimonial</button>
    <?php if ($edit): ?><a class="btn btn-outline" href="<?= admin_url('testimonials.php') ?>">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h3>All Testimonials (<?= count($testimonials) ?>)</h3>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Photo</th><th>Name</th><th>Rating</th><th>Review</th><th>Order</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($testimonials as $t): ?>
        <tr>
          <td>
            <?php if ($t['photo']): ?>
              <img src="<?= e(img_url($t['photo'])) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:50%">
            <?php else: ?>
              <span style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:#f26522;color:#fff;font-weight:700"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span>
            <?php endif; ?>
          </td>
          <td><?= e($t['name']) ?></td>
          <td><?= str_repeat('★', (int)$t['rating']) ?><span style="color:#ddd"><?= str_repeat('★', 5 - (int)$t['rating']) ?></span></td>
          <td style="max-width:280px"><?= e(mb_substr($t['review_text'], 0, 90)) ?><?= mb_strlen($t['review_text']) > 90 ? '…' : '' ?></td>
          <td><?= (int)$t['sort_order'] ?></td>
          <td><span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= e($t['status']) ?></span></td>
          <td class="actions">
            <a class="btn btn-sm" href="<?= admin_url('testimonials.php?edit=' . (int)$t['id']) ?>">Edit</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="testimonial_id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-sm btn-outline" type="submit"><?= $t['status'] === 'active' ? 'Hide' : 'Show' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this testimonial?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="testimonial_id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$testimonials): ?>
        <tr><td colspan="7" style="text-align:center;color:#888">No testimonials yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
