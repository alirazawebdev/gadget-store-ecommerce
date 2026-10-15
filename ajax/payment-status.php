<?php
/**
 * ajax/payment-status.php — polled by payment-pending.php.
 * If the order is still pending, asks the provider for the real transaction
 * status and updates the order ONLY from a genuine provider response.
 * Response: {"payment_status":"pending|paid|failed|unpaid","label":"..."}
 */
require_once __DIR__ . '/../includes/payments.php';
header('Content-Type: application/json');

$o = trim($_GET['o'] ?? '');
$out = ['payment_status' => 'unknown', 'label' => 'Unknown'];

$st = db()->prepare("SELECT * FROM orders WHERE order_no = ?");
$st->execute([$o]);
$order = $st->fetch();
if ($order) {
    $pm = $order['payment_method'];
    if ($order['payment_status'] === 'pending' && in_array($pm, ['easypaisa', 'jazzcash', 'card'], true)) {
        $fn = ['jazzcash' => 'pay_jazzcash_inquire',
               'easypaisa' => 'pay_easypaisa_inquire',
               'card' => 'pay_card_inquire'][$pm];
        $res = $fn($order);
        pay_log_event($order['id'], $pm, 'status_inquiry', $res);
        if (!empty($res['paid'])) {
            pay_mark_paid($order['id'], $pm, $res['txn_ref'] ?? $order['transaction_ref']);
            $order['payment_status'] = 'paid';
        } elseif (!empty($res['failed'])) {
            pay_mark_failed($order['id'], $pm, $res['error'] ?? 'declined');
            $order['payment_status'] = 'failed';
        }
    }
    $out = ['payment_status' => $order['payment_status'], 'label' => pay_status_label($order['payment_status'])];
}
echo json_encode($out);
