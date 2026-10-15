<?php
/**
 * Cart endpoint.
 * - AJAX requests (X-Requested-With: XMLHttpRequest) get JSON {count, subtotal, subtotal_html}.
 * - Normal form posts (JS disabled / JS failed) are redirected back — never raw JSON.
 */
require_once __DIR__ . '/../includes/functions.php';

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$qty    = max(1, (int)($_POST['qty'] ?? 1));
$buyNow = isset($_POST['buy_now']);

if ($id > 0) {
    if ($action === 'add') {
        $st = db()->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
        $st->execute([$id]);
        if ($st->fetch()) cart_add($id, $qty);
    } elseif ($action === 'update') {
        cart_set($id, $qty);
    } elseif ($action === 'remove') {
        cart_remove($id);
    }
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'count'         => cart_count(),
        'subtotal'      => cart_subtotal(),
        'subtotal_html' => format_price(cart_subtotal()),
    ]);
    exit;
}

/* ----- normal (non-AJAX) form post fallback ----- */
if ($buyNow) {
    redirect(base_url('checkout.php'));
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if ($back === '') $back = base_url('cart.php');

if ($action === 'add') flash('success', 'Product added to cart.');
redirect($back);
