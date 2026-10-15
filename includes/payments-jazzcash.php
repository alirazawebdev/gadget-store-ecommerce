<?php
/**
 * payments-jazzcash.php — JazzCash (Mobilink Microfinance Bank) Mobile Wallet API.
 *
 * NOTE: exact endpoints + pp_SecureHash algorithm are being verified against
 * official JazzCash documentation. Until then these functions return an honest
 * "not configured" error instead of faking a transaction.
 */

function pay_jazzcash_endpoints() {
    return pay_mode() === 'live'
        ? ['do' => 'https://payments.jazzcash.com.pk/ApplicationAPI/API/Payment/DoTransaction',
           'inquiry' => 'https://payments.jazzcash.com.pk/ApplicationAPI/API/Payment/InquireTransaction']
        : ['do' => 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/Payment/DoTransaction',
           'inquiry' => 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/Payment/InquireTransaction'];
}

/**
 * Send a Mobile Wallet (MWALLET) debit prompt to the customer's phone.
 * @return ['ok'=>bool, 'txn_ref'=>string, 'error'=>string]
 */
function pay_jazzcash_initiate($order, $mobile) {
    // TODO: fill from official docs (pending research) — pp_SecureHash algorithm.
    pay_log_event($order['id'], 'jazzcash', 'initiate_blocked', ['reason' => 'API spec pending verification']);
    return ['ok' => false, 'error' => 'JazzCash integration is being finalized. Please choose Cash on Delivery for now.'];
}

/** Check real transaction status with JazzCash. */
function pay_jazzcash_inquire($order) {
    return ['paid' => false, 'failed' => false, 'error' => 'JazzCash inquiry not configured yet'];
}

/** Verify a JazzCash IPN callback signature. */
function pay_jazzcash_verify_ipn() {
    return ['verified' => false, 'error' => 'JazzCash IPN verification not configured yet'];
}
