<?php
/**
 * CMS page — renders admin-authored content; contact form on contact-us.
 */
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$st = db()->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'active'");
$st->execute([$slug]);
$pg = $st->fetch();
if (!$pg) redirect(base_url());

if ($slug === 'contact-us' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cname  = trim($_POST['name'] ?? '');
    $cphone = trim($_POST['phone'] ?? '');
    $cemail = trim($_POST['email'] ?? '');
    $cmsg   = trim($_POST['message'] ?? '');
    if ($cname === '' || $cmsg === '') {
        flash('error', 'Please enter your name and message.');
    } else {
        db()->prepare("INSERT INTO messages (name, phone, email, message, status) VALUES (?, ?, ?, ?, 'new')")
          ->execute([$cname, $cphone, $cemail, $cmsg]);
        flash('success', 'Thank you! Your message has been sent.');
    }
    redirect(base_url('page.php?slug=contact-us'));
}

$pageTitle = $pg['meta_title'] ?: $pg['title'];
$pageDesc  = $pg['meta_description'] ?: '';
$pageKeys  = $pg['meta_keywords'] ?: '';

include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title"><?= e($pg['title']) ?></h1>
  <div class="page-content"><?= $pg['content'] /* admin-authored HTML, intentionally raw */ ?></div>

  <?php if ($slug === 'contact-us'): ?>
  <div class="contact-form-wrap">
    <h2>Send Us a Message</h2>
    <form method="post">
      <div class="form-group"><label>Name *</label><input class="form-control" type="text" name="name" required></div>
      <div class="form-group"><label>Phone</label><input class="form-control" type="text" name="phone"></div>
      <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email"></div>
      <div class="form-group"><label>Message *</label><textarea class="form-control" name="message" rows="5" required></textarea></div>
      <button class="btn" type="submit">Send Message</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
