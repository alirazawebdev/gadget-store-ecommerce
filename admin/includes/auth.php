<?php
/**
 * Admin guard — include FIRST in every admin page (except login.php).
 * Enforces login, refreshes the user row, sets $admin and $currentPage.
 */
require_once __DIR__ . '/../../includes/functions.php';

require_admin();

$st = db()->prepare("SELECT u.*, r.name AS role_name, r.permissions
                     FROM users u LEFT JOIN roles r ON r.id = u.role_id
                     WHERE u.id = ? AND u.status = 'active'");
$st->execute([current_user()['id']]);
$me = $st->fetch();
if (!$me) {
    session_destroy();
    redirect(base_url('admin/login.php'));
}
$_SESSION['admin_user'] = $me;
$admin = $me;
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
