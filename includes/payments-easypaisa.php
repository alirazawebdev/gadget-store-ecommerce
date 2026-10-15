<?php
/**
 * payments-easypaisa.php — Easypaisa (Telenor Microfinance Bank) merchant API.
 *
 * NOTE: exact endpoints + hash algorithm are being verified against official
 * Easypaisa merchant documentation. Until then these functions return an honest
 * "not configured" error instead of faking a transaction.
 */

function pay_easypaisa_initiate($order, $mobile) {
    // TODO: fill from official docs (pending research).
    pay_log_event($order['id'], 'easypaisa', 'initiate_blocked', ['reason' => 'API spec pending verification']);
    return ['ok' => false, 'error' => 'Easypaisa integration is being finalized. Please choose Cash on Delivery for now.'];
}

function pay_easypaisa_inquire($order) {
    return ['paid' => false, 'failed' => false, 'error' => 'Easypaisa inquiry not configured yet'];
}

function pay_easypaisa_verify_ipn() {
    return ['verified' => false, 'error' => 'Easypaisa callback verification not configured yet'];
}
