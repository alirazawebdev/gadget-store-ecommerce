<?php
/**
 * Admin logout.
 */
require_once __DIR__ . '/../includes/functions.php';

session_destroy();
redirect(base_url('admin/login.php'));
