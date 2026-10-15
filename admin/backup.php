<?php
/**
 * Database backup — generate & download full SQL dump in pure PHP.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Backup';
if (!can('settings')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_backup'])) {
    $tables = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $dump = "-- Gadget Store database backup\n";
    $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $t) {
        $create = db()->query("SHOW CREATE TABLE `$t`")->fetch();
        $dump .= "DROP TABLE IF EXISTS `$t`;\n" . $create['Create Table'] . ";\n\n";

        foreach (db()->query("SELECT * FROM `$t`") as $row) {
            $cols = [];
            foreach (array_keys($row) as $c) { $cols[] = "`$c`"; }
            $vals = [];
            foreach (array_values($row) as $v) {
                $vals[] = $v === null ? 'NULL' : db()->quote((string)$v);
            }
            $dump .= "INSERT INTO `$t` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        $dump .= "\n";
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    log_activity('Downloaded database backup');
    $fname = 'backup-' . date('Ymd-His') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . strlen($dump));
    echo $dump;
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Database Backup</h1>
</div>

<div class="card">
  <h3>Download Full Backup</h3>
  <p style="font-size:14px;color:#6b7280;margin-bottom:14px">
    Generates a complete <code>.sql</code> dump of all tables (structure + data) and downloads it.
    Keep regular backups in a safe place.
  </p>
  <form method="post" action="">
    <button class="btn" type="submit" name="do_backup" value="1">⬇ Download Backup (.sql)</button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
