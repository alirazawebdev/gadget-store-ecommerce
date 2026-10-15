<?php
/**
 * Admin login — standalone (no auth.php).
 */
require_once __DIR__ . '/../includes/functions.php';

if (is_admin()) {
    redirect(admin_url('index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $st = db()->prepare("SELECT u.*, r.name AS role_name, r.permissions
                         FROM users u LEFT JOIN roles r ON r.id = u.role_id
                         WHERE u.email = ?");
    $st->execute([$email]);
    $user = $st->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        if ($user['status'] !== 'active') {
            $error = 'Your account is inactive. Please contact the administrator.';
        } else {
            $_SESSION['admin_user'] = $user;
            log_activity('Logged in');
            redirect(admin_url('index.php'));
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | <?= e(site_name()) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <h2>⚙️ Admin Login</h2>
    <p><?= e(site_name()) ?> — control panel</p>
    <?php show_flash(); ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required autofocus
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn" style="width:100%">Login</button>
    </form>
    <p style="margin-top:16px;text-align:center"><a href="<?= base_url() ?>">← Back to site</a></p>
  </div>
</div>
</body>
</html>
