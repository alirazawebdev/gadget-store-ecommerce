<?php
/**
 * Add / edit CMS page (HTML content allowed).
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Pages';
if (!can('pages')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$editId = (int)($_GET['id'] ?? 0);
$page = null;
if ($editId > 0) {
    $st = db()->prepare("SELECT * FROM pages WHERE id = ?");
    $st->execute([$editId]);
    $page = $st->fetch();
    if (!$page) redirect(admin_url('pages.php'));
}
$isEdit = $page !== null;

/* ---------- save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $content    = trim($_POST['content'] ?? ''); // raw HTML allowed
    $status     = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_desc  = trim($_POST['meta_description'] ?? '');
    $meta_keys  = trim($_POST['meta_keywords'] ?? '');

    if ($title === '') {
        flash('error', 'Page title is required.');
        redirect(admin_url('page-form.php' . ($isEdit ? '?id=' . $editId : '')));
    }

    if ($isEdit) {
        $slug = ($title !== $page['title']) ? unique_slug('pages', $title, $editId) : $page['slug'];
        db()->prepare("UPDATE pages SET title=?, slug=?, content=?, meta_title=?, meta_description=?, meta_keywords=?, status=?, updated_at=NOW() WHERE id=?")
          ->execute([$title, $slug, $content, $meta_title, $meta_desc, $meta_keys, $status, $editId]);
        log_activity("Updated page: $title (id $editId)");
        flash('success', 'Page updated.');
        redirect(admin_url('page-form.php?id=' . $editId));
    } else {
        $slug = unique_slug('pages', $title);
        db()->prepare("INSERT INTO pages (title, slug, content, meta_title, meta_description, meta_keywords, status) VALUES (?,?,?,?,?,?,?)")
          ->execute([$title, $slug, $content, $meta_title, $meta_desc, $meta_keys, $status]);
        $nid = (int)db()->lastInsertId();
        log_activity("Added page: $title (id $nid)");
        flash('success', 'Page added.');
        redirect(admin_url('page-form.php?id=' . $nid));
    }
}

$adminTitle = $isEdit ? 'Edit Page' : 'Add Page';
$v = function ($k, $d = '') use ($page) {
    return $page ? $page[$k] : $d;
};
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1><?= $isEdit ? 'Edit Page' : 'Add Page' ?></h1>
  <a class="btn btn-gray" href="<?= admin_url('pages.php') ?>">← Back</a>
</div>

<div class="card">
  <form method="post" action="">
    <div class="form-row">
      <div class="form-group">
        <label>Title *</label>
        <input type="text" name="title" class="form-control" required value="<?= e($v('title')) ?>">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>
    <?php if ($isEdit): ?>
    <p style="font-size:13px;color:#6b7280">URL: <a href="<?= base_url('page.php?slug=' . urlencode($page['slug'])) ?>" target="_blank"><?= base_url('page.php?slug=' . urlencode($page['slug'])) ?></a></p>
    <?php endif; ?>
    <div class="form-group">
      <label>Content <small style="color:#6b7280">(HTML allowed — write or paste formatted content)</small></label>
      <textarea name="content" class="form-control" style="min-height:280px"><?= e($v('content')) ?></textarea>
    </div>
    <h3 style="margin-top:18px">SEO</h3>
    <div class="form-group">
      <label>Meta Title</label>
      <input type="text" name="meta_title" class="form-control" value="<?= e($v('meta_title')) ?>">
    </div>
    <div class="form-group">
      <label>Meta Description</label>
      <textarea name="meta_description" class="form-control" style="min-height:70px"><?= e($v('meta_description')) ?></textarea>
    </div>
    <div class="form-group">
      <label>Meta Keywords</label>
      <input type="text" name="meta_keywords" class="form-control" value="<?= e($v('meta_keywords')) ?>">
    </div>
    <button class="btn" type="submit"><?= $isEdit ? 'Save Changes' : 'Add Page' ?></button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
