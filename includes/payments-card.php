<?php
/**
 * payments-card.php — Debit/Credit card via Safepay (Pakistan).
 *
 * VERIFIED against:
 *  - Official Safepay Express Checkout guide (official-docs mirror:
 *    safepay-docs.netlify.app/build-your-integration/express-checkout/)
 *  - Official @sfpy/node-core SDK source (unpkg, "Official NodeJS Core to
 *    create SDKs for Safepay APIs") — the checkout-URL construction below is
 *    replicated verbatim from its Checkout.js.
 *
 * Flow:
 *  1. POST {api}/order/payments/v3/        -> data.tracker.token
 *  2. POST {api}/client/passport/v1/token  -> data (tbt auth token, 1h life)
 *  3. Redirect shopper to the Hosted Checkout URL (built exactly like the SDK)
 *  4. payment.succeeded webhook (HMAC-SHA512 verified) marks the order Paid
 *  5. Fallback: GET {api}/reporter/api/v1/payments/{tracker} when the shopper
 *     returns to our site
 */

/** 'sandbox' | 'production' per admin mode. */
function pay_safepay_env() {
    return pay_mode() === 'live' ? 'production' : 'sandbox';
}

function pay_safepay_api_base() {
    return pay_safepay_env() === 'production'
        ? 'https://api.getsafepay.com'
        : 'https://sandbox.api.getsafepay.com';
}

/** Embedded (hosted checkout) base — verbatim from official @sfpy/node-core. */
function pay_safepay_embedded_base() {
    return pay_safepay_env() === 'production'
        ? 'https://getsafepay.com/embedded/'
        : 'https://sandbox.api.getsafepay.com/embedded/';
}

/** Authenticated JSON request to the Safepay API. Returns decoded array or null. */
function pay_safepay_request($method, $url, $body = null) {
    $creds = pay_creds('card');
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $creds['secret_key'],
                'Content-Type: application/json'];
    $opts = [CURLOPT_RETURNTRANSFER => true,
             CURLOPT_TIMEOUT        => 30,
             CURLOPT_HTTPHEADER     => $headers];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body ?: new stdClass());
    }
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) return null;
    $d = json_decode((string)$raw, true);
    return is_array($d) ? $d : null;
}

/**
 * Checkout URL builder — verbatim logic from official SDK Checkout.js:
 *   baseUrl + "?" + urlencoded query of {environment, ...params}
 * (params with undefined values are dropped).
 */
function pay_safepay_checkout_url($tracker, $tbt, $redirectUrl, $cancelUrl, $userId = null) {
    $params = ['environment' => pay_safepay_env(),
               'tracker'     => $tracker,
               'tbt'         => $tbt,
               'source'      => 'hosted',
               'redirect_url'=> $redirectUrl,
               'cancel_url'  => $cancelUrl];
    if ($userId) $params['user_id'] = $userId;
    return pay_safepay_embedded_base() . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Start a Safepay card payment for an order.
 * @return ['ok'=>bool, 'redirect'=>url, 'tracker'=>token, 'txn_ref'=>token, 'error'=>string]
 */
function pay_card_start($order) {
    $creds  = pay_creds('card');
    $public = trim((string)($creds['public_key'] ?? ''));
    $secret = trim((string)($creds['secret_key'] ?? ''));
    $intent = strtoupper(trim((string)setting('pay_safepay_intent', 'CYBERSOURCE')));
    if (!in_array($intent, ['CYBERSOURCE', 'MPGS'], true)) $intent = 'CYBERSOURCE';
    if (!$public || !$secret) {
        pay_log_event($order['id'], 'card', 'initiate_blocked', ['reason' => 'keys missing']);
        return ['ok' => false, 'error' => 'Card payment is not configured yet. Please choose Cash on Delivery.'];
    }

    $api = pay_safepay_api_base();

    // Step 1: create the payment session (tracker)
    $r1 = pay_safepay_request('POST', $api . '/order/payments/v3/', [
        'merchant_api_key' => $public,
        'intent'           => $intent,
        'mode'             => 'payment',
        'currency'         => 'PKR',
        'amount'           => (int)round(((float)$order['total']) * 100), // paisa
        // keep metadata minimal: extra unknown fields get rejected
        'metadata'         => ['order_id' => $order['order_no']],
    ]);
    $tracker = $r1['data']['tracker']['token'] ?? null;
    if (!$tracker) {
        pay_log_event($order['id'], 'card', 'session_failed', []);
        return ['ok' => false, 'error' => 'Card payment could not be started. Please try again or choose Cash on Delivery.'];
    }

    // Step 2: short-lived passport (tbt) auth token
    $r2 = pay_safepay_request('POST', $api . '/client/passport/v1/token',
        ['merchant_api_key' => $public]);
    $tbt = $r2['data'] ?? null;
    if (!is_string($tbt) || !$tbt) {
        pay_log_event($order['id'], 'card', 'passport_failed', []);
        return ['ok' => false, 'error' => 'Card payment could not be started. Please try again or choose Cash on Delivery.'];
    }

    // Step 3: hosted checkout URL (official SDK formula)
    $url = pay_safepay_checkout_url(
        $tracker, $tbt,
        base_url('payment-pending.php?o=' . urlencode($order['order_no'])),
        base_url('checkout.php?pay=cancelled')
    );
    pay_log_event($order['id'], 'card', 'redirect_issued', ['tracker' => $tracker]);
    return ['ok' => true, 'redirect' => $url, 'tracker' => $tracker, 'txn_ref' => $tracker];
}

/**
 * Fallback status check via the reporter API (used when the shopper returns
 * to our site). Only a TRACKER_ENDED state marks Paid; anything else stays
 * pending (the webhook is the definitive failure signal).
 */
function pay_card_inquire($order) {
    $tracker = trim((string)($order['transaction_ref'] ?? ''));
    if (!$tracker) return ['paid' => false, 'failed' => false, 'error' => 'no tracker'];
    $d = pay_safepay_request('GET',
        pay_safepay_api_base() . '/reporter/api/v1/payments/' . urlencode($tracker));
    $state = '';
    if (isset($d['data']['tracker']['state'])) $state = (string)$d['data']['tracker']['state'];
    elseif (isset($d['data']['state']))        $state = (string)$d['data']['state'];
    pay_log_event($order['id'], 'card', 'inquire', ['state' => $state]);
    if ($state === 'TRACKER_ENDED') return ['paid' => true, 'failed' => false, 'txn_ref' => $tracker];
    return ['paid' => false, 'failed' => false, 'state' => $state];
}

/**
 * Verify a Safepay webhook: X-SFPY-SIGNATURE must equal
 * HEX( HMAC-SHA512( raw_body, webhook_shared_secret ) ).
 * Returns ['verified'=>bool, 'order_no'=>?, 'txn_ref'=>?, 'success'=>bool, 'failed'=>bool, 'error'=>?]
 */
function pay_card_verify_webhook() {
    $raw = file_get_contents('php://input');
    $sig = '';
    foreach (['HTTP_X_SFPY_SIGNATURE'] as $k) {
        if (!empty($_SERVER[$k])) { $sig = trim((string)$_SERVER[$k]); break; }
    }
    $secret = trim((string)setting('pay_safepay_webhook_secret', ''));
    if (!$secret || !$sig) {
        return ['verified' => false, 'error' => 'missing signature or webhook secret'];
    }
    $expected = hash_hmac('sha512', (string)$raw, $secret);
    if (!hash_equals(strtolower($expected), strtolower($sig))) {
        return ['verified' => false, 'error' => 'signature mismatch'];
    }

    $p = json_decode((string)$raw, true) ?: [];
    $data = $p['data'] ?? [];
    // tracker may arrive as a plain string or as an object with a token
    $tracker = is_array($data['tracker'] ?? null)
        ? (string)($data['tracker']['token'] ?? '')
        : (string)($data['tracker'] ?? '');
    // order id: tolerate both metadata shapes seen in the wild
    $meta = $data['metadata'] ?? [];
    $orderNo = (string)($meta['order_id'] ?? ($meta['data']['order_id'] ?? ''));
    $type  = (string)($p['type'] ?? '');
    $state = (string)($data['state'] ?? '');
    if (!$state && is_array($data['tracker'] ?? null)) $state = (string)($data['tracker']['state'] ?? '');

    $success = ($type === 'payment.succeeded') && ($state === 'TRACKER_ENDED');
    $failed  = ($type === 'payment.failed');
    return ['verified' => true,
            'order_no' => $orderNo,
            'txn_ref'  => $tracker,
            'success'  => $success,
            'failed'   => $failed,
            'error'    => $failed ? 'payment failed at gateway' : null];
}
