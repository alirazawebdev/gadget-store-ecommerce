<?php
/**
 * Core helpers — included on every frontend & admin page.
 * Starts the session; do NOT call session_start() again.
 */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------- output / escaping ---------------- */
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ---------------- URLs ---------------- */
function base_url($path = '') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(dirname($script), '/');
    if (substr($dir, -6) === '/admin') $dir = substr($dir, 0, -6);
    if (substr($dir, -5) === '/ajax')  $dir = substr($dir, 0, -5);
    $base = $proto . '://' . $host . ($dir === '' || $dir === '/' ? '' : $dir);
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function admin_url($path = '') {
    return base_url('admin/' . ltrim($path, '/'));
}

/* ---------------- settings ---------------- */
function setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query("SELECT skey, svalue FROM settings") as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Exception $ex) { /* table missing during install */ }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function site_name() {
    return setting('site_name', 'Gadget Store');
}

function format_price($n) {
    return 'Rs. ' . number_format((float)$n, 0);
}

/* ---------------- redirects / flash messages ---------------- */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function flash($type, $msg) {
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}

function show_flash() {
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        $cls = ($f['t'] === 'success') ? 'success' : 'error';
        echo '<div class="alert alert-' . $cls . '">' . e($f['m']) . '</div>';
    }
    unset($_SESSION['flash']);
}

/* ---------------- slugs ---------------- */
function slugify($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function unique_slug($table, $base, $ignoreId = null) {
    $slug = slugify($base);
    if ($slug === '') $slug = 'item';
    $try = $slug; $i = 1;
    while (true) {
        $sql = "SELECT id FROM $table WHERE slug = ?";
        $params = [$try];
        if ($ignoreId) { $sql .= " AND id != ?"; $params[] = (int)$ignoreId; }
        $st = db()->prepare($sql);
        $st->execute($params);
        if (!$st->fetch()) return $try;
        $i++;
        $try = $slug . '-' . $i;
    }
}

/* ---------------- admin auth / permissions ---------------- */
const PERMISSIONS = [
    'dashboard'  => 'Dashboard',
    'orders'     => 'Orders',
    'products'   => 'Products',
    'categories' => 'Categories',
    'customers'  => 'Customers',
    'sliders'    => 'Sliders & Banners',
    'popups'     => 'Popups',
    'coupons'    => 'Coupons',
    'reviews'    => 'Reviews',
    'pages'      => 'Pages / CMS',
    'settings'   => 'Settings & SEO',
    'users'      => 'Users & Roles',
    'reports'    => 'Reports',
    'messages'   => 'Messages',
];

function current_user() {
    return $_SESSION['admin_user'] ?? null;
}

function is_admin() {
    return current_user() !== null;
}

function require_admin() {
    if (!is_admin()) redirect(base_url('admin/login.php'));
}

function can($perm) {
    $u = current_user();
    if (!$u) return false;
    if (($u['role_name'] ?? '') === 'Super Admin') return true;
    $perms = json_decode($u['permissions'] ?? '[]', true);
    if (!is_array($perms)) $perms = [];
    if (in_array('all', $perms, true)) return true;
    return in_array($perm, $perms, true);
}

function log_activity($action) {
    $u = current_user();
    if (!$u) return;
    try {
        db()->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)")
           ->execute([$u['id'], $action]);
    } catch (Exception $ex) { /* ignore */ }
}

/* ---------------- uploads ---------------- */
function upload_image($file, $subdir) {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) return null;
    $dir = rtrim(UPLOAD_DIR, '/') . '/' . trim($subdir, '/') . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return trim($subdir, '/') . '/' . $name;
    }
    return null;
}

function img_url($path) {
    if (!$path) return base_url('assets/images/placeholder.svg');
    if (strpos($path, 'http') === 0) return $path;
    if (strpos($path, 'assets/') === 0) return base_url($path);
    return base_url('uploads/' . ltrim($path, '/'));
}

/* ---------------- shop helpers ---------------- */
function product_url($p) {
    return base_url('product.php?slug=' . urlencode($p['slug']));
}

function category_url($c) {
    return base_url('category.php?slug=' . urlencode($c['slug']));
}

function sale_price($p) {
    return ((float)$p['sale_price'] > 0) ? (float)$p['sale_price'] : (float)$p['price'];
}

function discount_percent($p) {
    if ((float)$p['sale_price'] > 0 && (float)$p['price'] > 0) {
        return (int)round((1 - (float)$p['sale_price'] / (float)$p['price']) * 100);
    }
    return 0;
}

function product_images($pid) {
    $st = db()->prepare("SELECT image FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
    $st->execute([(int)$pid]);
    return array_column($st->fetchAll(), 'image');
}

function product_thumb($p) {
    $imgs = product_images($p['id']);
    return $imgs ? img_url($imgs[0]) : base_url('assets/images/placeholder.svg');
}

function active_categories() {
    return db()->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name")->fetchAll();
}

/* ---------------- cart (session based) ---------------- */
function cart_add($pid, $qty = 1) {
    $pid = (int)$pid;
    $qty = max(1, (int)$qty);
    $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
}

function cart_set($pid, $qty) {
    $pid = (int)$pid;
    $qty = (int)$qty;
    if ($qty <= 0) unset($_SESSION['cart'][$pid]);
    else $_SESSION['cart'][$pid] = $qty;
}

function cart_remove($pid) {
    unset($_SESSION['cart'][(int)$pid]);
}

function cart_clear() {
    unset($_SESSION['cart'], $_SESSION['coupon']);
}

function cart_count() {
    return array_sum($_SESSION['cart'] ?? []);
}

function cart_items() {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) return [];
    $ids = array_map('intval', array_keys($cart));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM products WHERE id IN ($ph) AND status = 'active'");
    $st->execute($ids);
    $items = [];
    foreach ($st->fetchAll() as $p) {
        $p['qty'] = $cart[$p['id']];
        $p['unit_price'] = sale_price($p);
        $p['line_total'] = $p['unit_price'] * $p['qty'];
        $items[] = $p;
    }
    return $items;
}

function cart_subtotal() {
    $t = 0;
    foreach (cart_items() as $i) $t += $i['line_total'];
    return $t;
}

function coupon_discount($subtotal) {
    $c = $_SESSION['coupon'] ?? null;
    if (!$c) return 0;
    $st = db()->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active'");
    $st->execute([$c['code']]);
    $cp = $st->fetch();
    if (!$cp) { unset($_SESSION['coupon']); return 0; }
    if ($cp['expires_at'] && strtotime($cp['expires_at']) < time()) { unset($_SESSION['coupon']); return 0; }
    if ($subtotal < (float)$cp['min_order']) return 0;
    if ($cp['usage_limit'] > 0 && $cp['used_count'] >= $cp['usage_limit']) { unset($_SESSION['coupon']); return 0; }
    if ($cp['type'] === 'percent') return (int)round($subtotal * (float)$cp['value'] / 100);
    return (int)min((float)$cp['value'], $subtotal);
}

/* ---------------- orders ---------------- */
function order_statuses() {
    return [
        'pending'    => 'Pending',
        'confirmed'  => 'Confirmed',
        'processing' => 'Processing',
        'dispatched' => 'Dispatched',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
        'returned'   => 'Returned',
    ];
}

function status_badge($s) {
    $labels = order_statuses();
    return '<span class="badge badge-' . e($s) . '">' . e($labels[$s] ?? $s) . '</span>';
}

/* ---------------- pagination ---------------- */
function pagination($total, $perPage, $page, $baseUrl) {
    $pages = (int)ceil($total / max(1, $perPage));
    if ($pages <= 1) return '';
    $sep = (strpos($baseUrl, '?') === false) ? '?' : '&';
    $h = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $cls = ($i == $page) ? ' class="active"' : '';
        $h .= '<a' . $cls . ' href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    return $h . '</div>';
}

/* ---------------- storefront: marquee + product card ---------------- */

/**
 * Repeat $arr enough times (even count) for a seamless CSS marquee loop.
 * The track animates 0 -> -50%, so copies must be even.
 */
function marquee_items($arr, $min = 12) {
    $n = count($arr);
    if ($n === 0) return [];
    $copies = max(2, (int)ceil($min / $n));
    if ($copies % 2 === 1) $copies++;
    $out = [];
    for ($i = 0; $i < $copies; $i++) $out = array_merge($out, $arr);
    return $out;
}

/** Single product card HTML (used by grids + marquees). */
function product_card_html($p) {
    $disc = discount_percent($p);
    $h  = '<div class="product-card">';
    $h .= '<a href="' . product_url($p) . '"><img src="' . e(product_thumb($p)) . '" alt="' . e($p['name']) . '" loading="lazy"></a>';
    if ($disc > 0) $h .= '<span class="sale-badge">-' . (int)$disc . '%</span>';
    foreach (boost_badges($p) as $bdg) $h .= '<span class="smart-badge ' . $bdg[0] . '">' . e($bdg[1]) . '</span>';
    $h .= '<div class="p-info">';
    $h .= '<h3><a href="' . product_url($p) . '">' . e($p['name']) . '</a></h3>';
    $h .= '<div class="p-price">';
    if ($disc > 0) $h .= '<span class="old">' . format_price($p['price']) . '</span>';
    $h .= '<span class="new">' . format_price(sale_price($p)) . '</span>';
    $h .= '</div>';
    $h .= '<form class="add-cart-form" method="post" action="' . base_url('ajax/cart.php') . '">';
    $h .= '<input type="hidden" name="action" value="add">';
    $h .= '<input type="hidden" name="id" value="' . (int)$p['id'] . '">';
    $h .= '<div class="qty-step"><button type="button" class="qty-minus">&minus;</button><input type="number" name="qty" value="1" min="1"><button type="button" class="qty-plus">+</button></div>';
    $h .= '<button class="btn btn-add" type="submit">Add to Cart</button>';
    $h .= '</form></div></div>';
    return $h;
}

/* ---------------- sales boost (Shopify-style urgency widgets) ---------------- */
function boost_on($key) {
    return setting($key, '1') === '1';
}
function boost_int($key, $default = 0) {
    return (int)setting($key, (string)$default);
}

/* Cached ID sets for smart badges (computed once per request). */
function boost_badge_sets() {
    static $sets = null;
    if ($sets !== null) return $sets;
    $sets = ['best' => [], 'trend' => []];
    if (!boost_on('boost_badges')) return $sets;
    try {
        $db = db();
        $rows = $db->query(
            "SELECT oi.product_id, SUM(oi.qty) AS q FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status NOT IN ('cancelled','returned') AND oi.product_id > 0
             GROUP BY oi.product_id ORDER BY q DESC LIMIT 8"
        )->fetchAll();
        foreach ($rows as $r) $sets['best'][(int)$r['product_id']] = true;
        $rows = $db->query("SELECT id FROM products WHERE status = 'active' ORDER BY views DESC LIMIT 8")->fetchAll();
        foreach ($rows as $r) $sets['trend'][(int)$r['id']] = true;
    } catch (Exception $ex) { /* ignore */ }
    return $sets;
}

/* Returns list of [css-class, label] smart badges for a product. */
function boost_badges($p) {
    $out = [];
    if (!boost_on('boost_badges')) return $out;
    $sets = boost_badge_sets();
    $pid = (int)($p['id'] ?? 0);
    if (isset($sets['best'][$pid])) {
        $out[] = ['bdg-best', 'Bestseller'];
    } elseif (isset($sets['trend'][$pid])) {
        $out[] = ['bdg-trend', 'Trending'];
    }
    $isNew = !empty($p['is_new']);
    if (!$isNew && !empty($p['created_at'])) {
        $days = boost_int('boost_badges_new_days', 14);
        $ts = strtotime($p['created_at']);
        if ($ts && (time() - $ts) < $days * 86400) $isNew = true;
    }
    if ($isNew) $out[] = ['bdg-new', 'New'];
    return $out;
}

/* Stable "live viewers" count per product (changes hourly, not every refresh). */
function boost_viewers($pid) {
    $min = boost_int('boost_viewers_min', 12);
    $max = boost_int('boost_viewers_max', 48);
    if ($max <= $min) $max = $min + 10;
    $seed = abs(crc32($pid . '|' . date('Y-m-d-H')));
    return $min + ($seed % ($max - $min + 1));
}

/* % of stock sold, computed from real stock_log data. Null when unknown. */
function boost_sold_percent($p) {
    $stock = (int)($p['stock'] ?? 0);
    try {
        $st = db()->prepare("SELECT COALESCE(SUM(ABS(change_qty)),0) FROM stock_log WHERE product_id = ? AND type = 'out'");
        $st->execute([(int)$p['id']]);
        $sold = (int)$st->fetchColumn();
    } catch (Exception $ex) { return null; }
    $total = $sold + $stock;
    if ($total <= 0 || $sold <= 0) return null;
    return (int)round($sold / $total * 100);
}
