<?php
/**
 * payment-ipn.php — provider callback (IPN / webhook) entry point.
 * URL to register with the provider, e.g.:
 *   https://yourstore.com/payment-ipn.php?provider=jazzcash
 *
 * The callback is verified with the provider's official signature method
 * BEFORE anything is trusted. The order is marked paid only on a verified
 * "success" message, and marking is idempotent (replays are harmless).
 * Always answers HTTP 200 so providers stop retrying verified events.
 */
require_once __DIR__ . '/includes/payments.php';

$provider = trim($_GET['provider'] ?? '');
$verifyFn = ['jazzcash' => 'pay_jazzcash_verify_ipn',
             'easypaisa' => 'pay_easypaisa_verify_ipn',
             'card' => 'pay_card_verify_webhook'][$provider] ?? null;

http_response_code(200);
header('Content-Type: text/plain');

if (!$verifyFn || !function_exists($verifyFn)) {
    pay_log_event(0, $provider, 'ipn_unknown_provider', $_POST);
    echo 'IGNORED';
    exit;
}

$res = $verifyFn(); // ['verified'=>bool, 'order_no'=>?, 'txn_ref'=>?, 'success'=>bool, 'error'=>?]
pay_log_event(0, $provider, 'ipn_received', ['verified' => !empty($res['verified'])]);

if (empty($res['verified'])) {
    echo 'INVALID SIGNATURE';
    exit;
}

$st = db()->prepare("SELECT * FROM orders WHERE order_no = ?");
$st->execute([(string)($res['order_no'] ?? '')]);
$order = $st->fetch();
if (!$order) {
    pay_log_event(0, $provider, 'ipn_unknown_order', $res);
    echo 'UNKNOWN ORDER';
    exit;
}

pay_log_event($order['id'], $provider, 'ipn_verified', $res);
if (!empty($res['success'])) {
    pay_mark_paid($order['id'], $provider, $res['txn_ref'] ?? '');
    echo 'OK';
} elseif (!empty($res['failed'])) {
    pay_mark_failed($order['id'], $provider, $res['error'] ?? 'provider reported failure');
    echo 'RECORDED';
} else {
    // Verified but not a final event (e.g. informational) — acknowledge only.
    echo 'ACK';
}
