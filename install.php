<?php
/**
 * Web installer — upload the ZIP, extract, visit install.php in your browser.
 * Creates the database, imports database.sql, writes config and creates admin.
 */
$lockFile = __DIR__ . '/config/installed.lock';
if (file_exists($lockFile)) {
    die('<h2>Already installed.</h2><p>Delete <b>config/installed.lock</b> to reinstall, then remove install.php.</p>');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost   = trim($_POST['db_host'] ?? 'localhost');
    $dbName   = trim($_POST['db_name'] ?? 'gadget_store');
    $dbUser   = trim($_POST['db_user'] ?? '');
    $dbPass   = $_POST['db_pass'] ?? '';
    $aName    = trim($_POST['admin_name'] ?? '');
    $aEmail   = trim($_POST['admin_email'] ?? '');
    $aPass    = $_POST['admin_pass'] ?? '';
    $siteName = trim($_POST['site_name'] ?? 'Gadget Store');

    if (!$dbName || !$dbUser || !$aName || !$aEmail || !$aPass) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=$dbHost;charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            $sql = file_get_contents(__DIR__ . '/database.sql');
            $sql = preg_replace('/^CREATE DATABASE.*?;$/mi', '', $sql);
            $sql = preg_replace('/^USE .*?;$/mi', '', $sql);
            $statements = array_filter(array_map('trim', explode(";\n", $sql)));
            foreach ($statements as $st) {
                if ($st !== '') $pdo->exec($st);
            }

            $hash = password_hash($aPass, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (role_id, name, email, password, status) VALUES (1, ?, ?, ?, 'active')")
                ->execute([$aName, $aEmail, $hash]);
            $pdo->prepare("UPDATE settings SET svalue = ? WHERE skey = 'site_name'")
                ->execute([$siteName]);

            $cfg  = "<?php\n";
            $cfg .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $cfg .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
            $cfg .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $cfg .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            $cfg .= "define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');\n";
            $cfg .= "define('UPLOAD_URL', 'uploads/');\n";
            $cfg .= "define('ITEMS_PER_PAGE', 24);\n";
            $cfg .= "define('CURRENCY', 'Rs.');\n";
            file_put_contents(__DIR__ . '/config/config.php', $cfg);
            file_put_contents($lockFile, date('c'));
            $success = true;
        } catch (Exception $ex) {
            $error = 'Install failed: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install — Gadget Store</title>
<style>
body{font-family:Arial,sans-serif;background:#f1f3f6;padding:30px}
.box{background:#fff;max-width:520px;margin:0 auto;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
h2{margin-top:0}.fg{margin-bottom:12px}label{display:block;font-weight:bold;font-size:13px;margin-bottom:5px}
input{width:100%;padding:9px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box}
button{background:#f26522;color:#fff;border:none;padding:12px 24px;border-radius:6px;font-size:15px;cursor:pointer;width:100%;font-weight:bold}
.err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:6px;margin-bottom:14px}
.ok{background:#dcfce7;color:#166534;padding:14px;border-radius:6px;margin-bottom:14px}
h3{margin:18px 0 10px;color:#f26522}
</style>
</head>
<body>
<div class="box">
<h2>🛍️ Gadget Store — Installer</h2>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?>
  <div class="ok"><b>Installed successfully!</b><br>
  <a href="index.php">Visit your store</a> · <a href="admin/login.php">Open admin panel</a></div>
  <p style="color:#b91c1c"><b>Important:</b> delete <b>install.php</b> and <b>BUILD_SPEC.md</b> from your hosting now.</p>
<?php else: ?>
  <form method="post">
    <h3>Database</h3>
    <div class="fg"><label>DB Host *</label><input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required></div>
    <div class="fg"><label>DB Name *</label><input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'gadget_store') ?>" required></div>
    <div class="fg"><label>DB User *</label><input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required></div>
    <div class="fg"><label>DB Password</label><input type="password" name="db_pass"></div>
    <h3>Admin account</h3>
    <div class="fg"><label>Your name *</label><input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required></div>
    <div class="fg"><label>Email *</label><input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required></div>
    <div class="fg"><label>Password *</label><input type="password" name="admin_pass" required></div>
    <h3>Store</h3>
    <div class="fg"><label>Store name *</label><input name="site_name" value="<?= htmlspecialchars($_POST['site_name'] ?? 'Gadget Store') ?>" required></div>
    <button type="submit">Install Now</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
