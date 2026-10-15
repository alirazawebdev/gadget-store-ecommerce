<?php
/**
 * payments.php — MANUAL payment layer.
 *
 * Methods: cod | easypaisa | jazzcash | bank
 * The merchant's receiving details (account title, account number, WhatsApp)
 * are entered by the ADMIN in Payment Settings. There is no provider API —
 * the customer transfers money from their own app and submits the
 * transaction ID; the admin verifies it manually in Orders and marks paid.
 *
 * An order is marked PAID only by the admin pressing "Mark as Paid"
 * (or by a genuine provider confirmation if one is ever wired in).
 * Nothing here ever marks an order paid automatically.
 */

require_once __DIR__ . '/functions.php';

/* ---------------- method registry ---------------- */
function pay_methods() {
    return [
        'cod'       => ['label' => 'Cash on Delivery', 'icon' => '💵', 'desc' => 'Pay in cash when your order arrives', 'manual' => false],
        'easypaisa' => ['label' => 'Easypaisa',         'icon' => '📱', 'desc' => 'Transfer to our Easypaisa account, then enter the transaction ID', 'manual' => true],
        'jazzcash'  => ['label' => 'JazzCash',          'icon' => '📲', 'desc' => 'Transfer to our JazzCash account, then enter the transaction ID', 'manual' => true],
        'bank'      => ['label' => 'Bank Transfer',     'icon' => '🏦', 'desc' => 'Transfer to our bank account, then enter the reference number', 'manual' => true],
    ];
}

function pay_setting_key($method) {
    return ['cod' => 'pay_cod_enabled', 'easypaisa' => 'pay_easypaisa_enabled',
            'jazzcash' => 'pay_jazzcash_enabled', 'bank' => 'pay_bank_enabled'][$method] ?? '';
}

function pay_is_enabled($method) {
    $k = pay_setting_key($method);
    return $k !== '' && setting($k, '0') === '1';
}

/** Only admin-enabled methods appear at checkout. Empty = admin disabled everything. */
function pay_enabled_methods() {
    $out = [];
    foreach (pay_methods() as $m => $info) {
        if (pay_is_enabled($m)) $out[$m] = $info;
    }
    return $out;
}

function pay_is_manual($method) {
    $all = pay_methods();
    return !empty($all[$method]['manual']);
}

function pay_cod_fee() {
    return max(0, (float)setting('pay_cod_fee', 0));
}

/**
 * Receiving details entered by the admin (Payment Settings).
 * These are PUBLIC (shown at checkout so the customer knows where to send money).
 */
function pay_method_details($method) {
    switch ($method) {
        case 'easypaisa':
        case 'jazzcash':
            return [
                'title'    => trim(setting("pay_{$method}_title", '')),
                'number'   => trim(setting("pay_{$method}_number", '')),
                'whatsapp' => trim(setting("pay_{$method}_whatsapp", '')),
            ];
        case 'bank':
            return [
                'bank_name' => trim(setting('pay_bank_name', '')),
                'title'     => trim(setting('pay_bank_title', '')),
                'number'    => trim(setting('pay_bank_number', '')),
                'whatsapp'  => trim(setting('pay_bank_whatsapp', '')),
            ];
    }
    return [];
}

/** A manual method is "configured" when the admin filled the receiving account number. */
function pay_method_configured($method) {
    if ($method === 'cod') return true;
    if (!pay_is_manual($method)) return false;
    $d = pay_method_details($method);
    return ($d['number'] ?? '') !== '';
}

/** wa.me link for the admin's WhatsApp number (03XXXXXXXXX -> 92XXXXXXXXX). */
function pay_whatsapp_link($number, $text) {
    $d = preg_replace('/\D+/', '', (string)$number);
    if (strlen($d) === 11 && $d[0] === '0') $d = '92' . substr($d, 1);
    if ($d === '' || strlen($d) < 10) return '';
    return 'https://wa.me/' . $d . '?text=' . rawurlencode($text);
}

/* ---------------- order payment state ---------------- */
function pay_get_order($orderId) {
    $st = db()->prepare("SELECT * FROM orders WHERE id = ?");
    $st->execute([(int)$orderId]);
    return $st->fetch() ?: null;
}

function pay_log_event($orderId, $provider, $eventType, $payload = null) {
    if (is_array($payload) || is_object($payload)) $payload = json_encode($payload);
    db()->prepare("INSERT INTO payment_events (order_id, provider, event_type, payload) VALUES (?, ?, ?, ?)")
      ->execute([(int)$orderId, (string)$provider, (string)$eventType, (string)($payload ?? '')]);
}

/** Mark paid — idempotent. Only the admin (or a verified provider event) calls this. */
function pay_mark_paid($orderId, $provider, $txnRef) {
    $o = pay_get_order($orderId);
    if (!$o) return false;
    if ($o['payment_status'] === 'paid') return true; // already paid — ignore duplicates
    db()->prepare("UPDATE orders SET payment_status = 'paid', transaction_ref = ? WHERE id = ?")
      ->execute([(string)$txnRef, (int)$orderId]);
    pay_log_event($orderId, $provider, 'marked_paid', ['txn_ref' => $txnRef]);
    return true;
}

function pay_mark_failed($orderId, $provider, $reason) {
    $o = pay_get_order($orderId);
    if (!$o || $o['payment_status'] === 'paid') return false;
    db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([(int)$orderId]);
    pay_log_event($orderId, $provider, 'marked_failed', ['reason' => $reason]);
    return true;
}

function pay_status_label($status) {
    return ['unpaid' => 'Unpaid', 'pending' => 'Payment Verification Pending', 'paid' => 'Paid',
            'failed' => 'Payment Failed', 'refunded' => 'Refunded'][$status] ?? ucfirst((string)$status);
}

function pk_phone_normalize($phone) {
    $d = preg_replace('/\D+/', '', (string)$phone);
    if (strlen($d) === 10 && $d[0] === '3') $d = '0' . $d;       // 3XXXXXXXXX -> 03XXXXXXXXX
    if (strlen($d) === 12 && substr($d, 0, 2) === '92') $d = '0' . substr($d, 2); // 923XXXXXXXXX
    return $d;
}

function pk_phone_valid($phone) {
    return (bool)preg_match('/^03\d{9}$/', pk_phone_normalize($phone));
}
