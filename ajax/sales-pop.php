<?php
/**
 * Sales-pop data endpoint — recent VERIFIED purchases (real orders only).
 * Returns JSON array: [{name, city, product, img, when}]. Empty array = no popups shown.
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$out = [];
try {
    if (!boost_on('boost_salespop')) {
        echo json_encode($out);
        exit;
    }
    $rows = db()->query(
        "SELECT o.name, o.city, oi.product_name, oi.product_id, o.created_at
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.status NOT IN ('cancelled', 'returned')
           AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY o.created_at DESC
         LIMIT 12"
    )->fetchAll();

    // product thumbnails (one query: first image per product)
    $thumbs = [];
    $ids = array_unique(array_map(function ($r) { return (int)$r['product_id']; }, $rows));
    $ids = array_filter($ids);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        foreach (db()->query("SELECT product_id, image FROM product_images WHERE product_id IN ($in) ORDER BY sort_order, id") as $pr) {
            $pid2 = (int)$pr['product_id'];
            if (!isset($thumbs[$pid2])) $thumbs[$pid2] = $pr['image'];
        }
    }

    foreach ($rows as $r) {
        $full = trim((string)$r['name']);
        $first = explode(' ', $full)[0] ?? 'Someone';
        $mins = max(1, (int)round((time() - strtotime($r['created_at'])) / 60));
        if ($mins < 60) {
            $when = $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($mins < 1440) {
            $h = (int)round($mins / 60);
            $when = $h . ' hour' . ($h > 1 ? 's' : '') . ' ago';
        } else {
            $d = (int)round($mins / 1440);
            $when = $d . ' day' . ($d > 1 ? 's' : '') . ' ago';
        }
        $pid = (int)$r['product_id'];
        $out[] = [
            'name'    => $first,
            'city'    => trim((string)$r['city']) !== '' ? $r['city'] : 'Pakistan',
            'product' => mb_substr($r['product_name'], 0, 42),
            'img'     => isset($thumbs[$pid]) && $thumbs[$pid] ? img_url($thumbs[$pid]) : '',
            'when'    => $when,
        ];
    }
} catch (Exception $ex) {
    $out = [];
}

echo json_encode($out);
