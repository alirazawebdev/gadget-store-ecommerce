# Payment Gateway API Research — JazzCash, Easypaisa, Safepay
*Researched 2026-09-20 by subagent. Method: public web search (official doc excerpts + working-integration sources). `browser.open` was unavailable this turn, so full official PDFs could not be fetched directly — every claim below cites the source snippet it came from, and anything not verifiable is marked **UNVERIFIED**. The most authoritative documents are the integration packs JazzCash/Easypaisa issue at merchant onboarding; those should be re-checked before go-live.*

---

## 1) JazzCash REST API v2 — Mobile Wallet (pp_TxnType = MWALLET)

### 1.1 Endpoints
| Call | Sandbox | Live |
|---|---|---|
| DoTransaction | `https://sandbox.jazzcash.com.pk/ApplicationAPI/API/Payment/DoTransaction` | **UNVERIFIED** from today's sources (commonly cited as `https://payments.jazzcash.com.pk/ApplicationAPI/API/Payment/DoTransaction` — confirm from merchant doc pack) |
| Inquiry / status | **UNVERIFIED** (path `.../API/Payment/Inquiry` appears in community code but I could not verify it from an official source today) | **UNVERIFIED** |

- Sandbox DoTransaction URL verified via a real working integration repo's `.env` (not official, but a live consumer): https://github.com/nazirhussain1/codeswear — `JAZZCASH_GATEWAY_URL=https://sandbox.jazzcash.com.pk/ApplicationAPI/API/Payment/DoTransaction`
- Official merchant guide (Integration Guide for Merchants v3.9, hosted on official sandbox portal) covers the same API family; full REST v2 doc pack is issued to merchants at onboarding.
- Source: https://sandbox.jazzcash.com.pk/SandboxDocumentation/Content/documentation/PMCL%20-%20Payment%20Gateway%20Integration%20Guide%20for%20Merchants%20Ver%203.9-PMCL.pdf

### 1.2 Request fields for MWALLET DoTransaction (verified from official guide)
From the official guide's parameter table:
- `pp_Version` — fixed `1.1` (mandatory)
- `pp_TxnType` — `MWALLET` for Mobile Account (mandatory; other values: `OTC`)
- `pp_Language` — fixed `EN` (mandatory)
- `pp_MerchantID` — merchant id issued by JazzCash (mandatory)
- `pp_SubMerchantID` — optional, only if merchant handles child merchants
- `pp_Password` — system-generated merchant password (mandatory)
- `pp_BankID` — only for DD transactions, else empty
- `pp_ProductID` — only for DD transactions, else empty
- `pp_TxnRefNo` — unique merchant-generated reference (mandatory)
- `pp_Amount` — mandatory; **multiply amount by 100** (no decimals; decimal position assumed per currency). Verified from official Mobile Wallet Refund API guide sample: `"pp_Amount": "25000", // the product amount must be multiplied by 100`
- `pp_TxnCurrency` — fixed `PKR`
- `pp_TxnDateTime` — format `yyyyMMddHHmmss`
- `pp_TxnExpiryDateTime` — format `yyyyMMddHHmmss`
- `pp_BillReference` — merchant bill/order reference
- `pp_Description` — transaction description
- `pp_ReturnURL` — where the gateway redirects the customer after payment (hosted flow)
- `pp_SecureHash` — see §1.3
- `ppmpf_1` … `ppmpf_5` — optional pass-through fields

MWALLET-specific customer fields (community-verified, in the official hosted guide the gateway collects them on its page; for REST the merchant sends them):
- `pp_MobileNumber` — customer's JazzCash wallet number, e.g. `03123456789` (seen in sameerwasim/jazzcash-checkout WALLET usage: https://github.com/sameerwasim/jazzcash-checkout)
- `pp_CNIC` — last 6 digits of customer's CNIC in community examples (`pp_CNIC: "345678"`) — **exact expected format UNVERIFIED from official source**
- ⚠️ These two field names are **not** in the official guide excerpt I could verify — treat as community-confirmed, re-verify against the REST v2 doc pack.

### 1.3 pp_SecureHash — EXACT algorithm
From the **official** guide (sandbox.jazzcash.com.pk), Section 13:
1. Start with the **Shared Secret** (the Integrity Salt issued at merchant registration) — it is **always first**.
2. Then concatenate **all transaction request fields in alphabetical order of the field name** (ascending ASCII sort; shorter string first when one is a substring of another).
3. **No separators between fields, no null terminators.**
4. Apply **SHA256** (plain SHA-256, **not** HMAC per the official text) and hex-encode the output. Uppercase hex seen in samples.

```
pp_SecureHash = HEX( SHA256( IntegritySalt
    + value_of_alphabetically_first_field
    + value_of_alphabetically_second_field
    + ... ) )
```

Example (official guide): shared secret `0F5DD14AE2E38C7EBD8814D29CF6F6F0`, then fields alphabetically, no separators.

⚠️ **Critical conflict to resolve before coding:** community REST integrations (e.g. https://medium.com/@charslanmirza/implementing-jazzcash-payment-gateway-in-flutter-a-step-by-step-guide-3f289d63ea31, and the WooCommerce plugin https://github.com/muhammad-hamid-jamil/wc-gateways-pakistan) compute it as **HMAC-SHA256 keyed with the Integrity Salt** over `salt + "&" + value1 + "&" + value2 ...` (sorted, `&`-separated):
```
toBeHashed = integritySalt + "&" + sortedValues.join("&")
pp_SecureHash = HEX( HMAC-SHA256(key=integritySalt, message=toBeHashed) )
```
The official hosted-page guide says plain SHA256 with no separators; the community REST flow says HMAC-SHA256 with `&` separators. **Which one the REST v2 MWALLET endpoint expects is UNVERIFIED from today's sources — the merchant REST v2 doc pack (issued at onboarding) is authoritative. Test in sandbox with both if the pack is unavailable.**

### 1.4 Response fields & codes
Response includes `pp_ResponseCode`, `pp_ResponseMessage`, `pp_TxnRefNo`, `pp_SecureHash` (verify on receipt with the same algorithm), plus transaction ids.
Verified codes (official guide Appendix I):
- `000` — Success ("Thank you for Using JazzCash, your transaction was successful.")
- `157` — **Transaction is pending (for Mwallet and MIgs)** ← the normal MWALLET "waiting for customer approval" state
- `112` — Transaction Cancelled by User
- `115` — Invalid hash received
- `101` — Invalid merchant credentials
- `116` — Transaction Expired
- `134` — Transaction has been timed out
- `109` — Transaction does not exist
- `131` — Transaction is Refunded
- `124` — Order placed, waiting for OTC financials

### 1.5 Customer approval flow (MWALLET)
- REST flow: merchant calls DoTransaction with `pp_TxnType=MWALLET` (+ `pp_MobileNumber`, `pp_CNIC`). JazzCash **pushes an approval prompt to the customer's JazzCash mobile account**; the customer approves with their MPIN in the JazzCash app.
- The synchronous response is typically `157` (pending) — the money is NOT yet moved.
- Merchant must **poll the Inquiry API** with `pp_TxnRefNo` until a final code (`000` success, `112` cancelled, etc.). Recommended poll interval/backoff is not documented in verified sources — choose e.g. every 10s for ~3–5 min, then fall back to async reconciliation. (Community library supports request types PAY / WALLET / INQUIRY / REFUND: https://github.com/sameerwasim/jazzcash-checkout)
- Hosted-page alternative (official guide, p.9–10): customer is redirected to the PMCL Payment Gateway, enters MSISDN; "in case of linked mobile wallets confirmation will be sent by CPS"; then returns to `pp_ReturnURL`.

### 1.6 IPN / callback
- **No official webhook/IPN for REST MWALLET found in verified sources.** The documented server-side confirmation path is **Inquiry polling**. Mark IPN as **UNVERIFIED**.
- Hosted flow uses `pp_ReturnURL` (customer browser redirect) — must still verify `pp_SecureHash` and re-query Inquiry before marking Paid; never trust redirect params alone.

---

## 2) Easypaisa Merchant API — Mobile Account (MA) debit (Telenor Microfinance Bank)

Easypaisa has **two documented integration styles**. The merchant chooses per onboarding.

### 2A) REST v4 — initiate-ma-transaction (newer, JSON)
**Endpoint:**
- Live: `https://easypay.easypaisa.com.pk/easypay-service/rest/v4/initiate-ma-transaction` — seen in a working community integration's config (https://github.com/sherazrazadev/easypesa_payment_integration_nodejs `.env`, `API_URL=.../initiate-ma-transaction`). Treat as community-confirmed.
- Sandbox: **UNVERIFIED** (do not guess).

**Request JSON fields** (from Easypay Checkout Integration Guide 6.1, via scribd copy of official guide: https://www.scribd.com/document/498308264/Easypay-Checkout-Integration-guide-6-1):
- `storeId`, `transactionAmount`, `orderId`, `mobileAccountNo`, `emailAddress`, `transactionType` (use `"InitialRequest"`), `bankIdentificationNumber`, `tokenExpiry`, `merchantPaymentMethod`, `postBackURL`

**EXACT signature algorithm (verified from the guide):**
1. Build the JSON with **exactly this parameter sequence** (trim unnecessary spaces):
```json
{"storeId":"43","transactionAmount":"100","orderId":"Test-new-002","mobileAccountNo":"03656565667","emailAddress":"Test@example.com","transactionType":"InitialRequest","bankIdentificationNumber":"","tokenExpiry":"","merchantPaymentMethod":"","postBackURL":"https://example.com/callback"}
```
2. Sign it with **SHA256withRSA** using the merchant's **2048-bit RSA private key**.
3. The merchant's **public key must be uploaded** in the Easypaisa portal first: *Account Settings → Public Key Configuration → Upload Public Key*.
4. The resulting signature (base64) is sent as the request signature.

**Response:** includes `transactionId` (FUNDAMO system transaction id, for MA), `orderId`, `storeId`, `responseCode`, `transactionDateTime`.

**Customer approval:** the MA debit pushes an approval request to the customer's Easypaisa mobile account; customer approves with MPIN. (Same push-approve pattern as JazzCash; explicit official wording not in the excerpts — behavior per portal docs.)

**Notification / postBack:** Easypaisa calls the merchant's `postBackURL` with a query string containing (per guide 6.1, MA section):
- `orderRefNumber`, `Amount`, `TransactionRefNumber`, `Status` (`0000` = Success, `0001` = Failure), `Description`
- ⚠️ **Signature/verification method for this postBack is UNVERIFIED** from today's sources — always re-verify via the Inquire/status API before marking Paid.

**Status/Inquire API for REST v4: UNVERIFIED** from today's sources (do not guess the URL).

### 2B) Classic hosted POST flow (Integration Guide v4.1 — older)
- POST an HTML form to the hosted checkout (`easypay/Index.jsf` → `Confirm.jsf`; live base `https://easypay.easypaisa.com.pk/easypay/`).
- Fields: `amount`, `autoRedirect`, `emailAddr`, `mobileNum`, `orderRefNum`, `paymentMethod` (`OTC` / `MA` / `CC`), `postBackURL`, `storeId` + **`merchantHashedReq`**.
- **merchantHashedReq algorithm (from official guide v4.1, via scribd copy https://www.scribd.com/document/464791415/Easypaisa-Merchant-Integration-Guide-v4-1-2):** build the concatenated string in exactly this order:
```
amount=&autoRedirect=&emailAddr=&mobileNum=&orderRefNum=&paymentMethod=&postBackURL=&storeId=
```
(filled with the actual values), then hash/encrypt with the **Hash Key** from the portal (*Account Settings → Generate Hash Key*). The guide says the same parameters sent in the request are used to generate the encrypted value, reconciled at the OPS end.
- ⚠️ **Exact hash function (HMAC-SHA256 vs SHA-512) UNVERIFIED from official text** — a maintained community plugin offers SHA-256/SHA-512 as a setting (https://github.com/muhammad-hamid-jamil/wc-gateways-pakistan). Confirm from the doc pack.
- Response codes (official): `0000` Success, `0001` System Error, `0002` Required field missing, `0003` Invalid Order ID, `0004` Merchant Account does not exist.
- Older SOAP variant (`initiateTransaction`): uses partner `username`/`password`, `<channel>Internet</channel>`, `orderId`, `storeId`, `transactionAmount`, `transactionType` (`OTC`/`MA`), `msisdn`, `mobileAccountNo`, `emailAddress`. Inquire Transaction API exists with partner credentials ("Manage Partner"). Sources: https://www.scribd.com/document/398008969/easy-paisa-open-cart

### 2C) Sandbox vs live
- Portal-issued credentials (Store ID, Hash Key / RSA keys) differ per environment. Live REST base `https://easypay.easypaisa.com.pk`; sandbox base **UNVERIFIED**.

---

## 3) Safepay (Pakistan card gateway)

### 3.1 Base URLs (verified)
- Sandbox: `https://sandbox.api.getsafepay.com`
- Live: `https://api.getsafepay.com`
- Sources: official Express Checkout guide mirror https://safepay-docs.netlify.app/build-your-integration/express-checkout/ (documents the official API), plus live sandbox tests: https://github.com/sohaib25225-gif/digital-hub/blob/HEAD/STAGE0_FINDINGS_REPORT.md. Official reference: https://apidocs.getsafepay.com. Dashboards: `https://getsafepay.com/dashboard/login` (live), `https://sandbox.api.getsafepay.com/dashboard/login` (sandbox).

### 3.2 Create payment session — server side
`POST /order/payments/v3/` — verified working (HTTP 201 in sandbox tests).
Headers: `Authorization: Bearer <SECRET_KEY>`, `Content-Type: application/json`.
Body (verified field table from official Express Checkout guide):
| Field | Required | Description |
|---|---|---|
| `merchant_api_key` | Yes | **Public** API key of the account (test and live differ) |
| `intent` | Yes | Payment channel: `CYBERSOURCE` or `MPGS` |
| `mode` | Yes | `"payment"` |
| `currency` | Yes | e.g. `PKR` |
| `amount` | Yes | **Lowest denomination** — paisa for PKR (Rs.100 → `10000`) |
| `user` | No | Customer token (prefills checkout) |
| `entry_mode` | No | Entry mode |
| `metadata` | No | JSON object for extra info — put your order id here as `{"order_id": "..."}` (extra unknown fields have been observed to get rejected — keep it to `order_id`) |
| `include_fees` | No | `true` passes MDR fees to the customer |

Response (HTTP 201): `data.tracker.token` (format `track_<uuid>`, 41 chars), `data.tracker.state` = `TRACKER_STARTED`.

### 3.3 Redirecting the customer to pay
1. Create a **passport/auth token**: `POST /client/passport/v1/token` with `{"merchant_api_key": "<public key>"}` and `Authorization: Bearer <secret>` → returns the `tbt` token.
2. Generate the checkout URL with the official SDK (`safepay.checkouts.payment.create({ tracker, tbt, environment: "sandbox"|"production", source: "hosted", redirect_url, cancel_url, user_id })`) and redirect the shopper to it. Source: https://safepay-docs.netlify.app/build-your-integration/express-checkout/
3. ⚠️ The **raw manual checkout URL format is UNVERIFIED** — community testers tried `/order/checkout/{tracker}` and `/checkout/pay/{tracker}` without confirming. Use the SDK/checkout.js route, do not hardcode a URL format.

### 3.4 Webhooks — EXACT verification
- **Header:** `X-SFPY-SIGNATURE`
- **Algorithm:** **HMAC-SHA512** (not SHA-256) — verified by multiple independent sandbox integrations (https://github.com/sohaib25225-gif/digital-hub/blob/HEAD/PHASE6_PRE_IMPLEMENTATION_VERIFICATION.md).
- **What is signed:** the **raw request body bytes**, UTF-8, **before** any JSON parsing.
- **Key:** the **webhook shared secret** (`whsec_...`, 32-char alphanumeric, found in Dashboard → Developers → Endpoints → View Shared Secret). This is a **different secret** from the API secret key.
```
expected = HEX( HMAC-SHA512( key = webhook_secret, message = raw_request_body_bytes ) )
compare with X-SFPY-SIGNATURE using timing-safe comparison
```
- Event types: `payment.succeeded`, `payment.failed`. Payload nesting: `data.tracker`, `data.metadata.data.order_id` (note the double nesting of metadata in webhooks), success state `TRACKER_ENDED`, failure state `TRACKER_ENROLLED`.
- Status lookup endpoint (from Safepay support on GitHub, community): `GET /api/v1/payments/{tracker_token}` — partially verified, use as fallback reconciliation. Source: https://github.com/getsafepay/safepay-node/issues/21
- ⚠️ Do **not** confuse with Safepay's separate **Raast aggregator API** (docs at https://github.com/getsafepay/raast-docs), which uses HMAC-**SHA256** and `X-SFPY-AGGREGATOR-SECRET-KEY` — that is a different product.

### 3.5 Key naming (dashboard)
- **Public API key** — `pk_sandbox_...` / `pk_live_...` → sent in the body as `merchant_api_key`
- **Secret key** — `sk_sandbox_...` / `sk_live_...` → `Authorization: Bearer` header, server-side only
- **Webhook shared secret** — `whsec_...` → HMAC-SHA512 verification of webhooks
- Sandbox and production keys are **not interchangeable** (environment mismatch errors if crossed).

---

## Gaps that MUST be closed before implementation (all UNVERIFIED above)
1. JazzCash REST v2: live DoTransaction URL, exact Inquiry endpoint + its request/response, which pp_SecureHash variant (plain-SHA256 vs HMAC-SHA256 with `&`) the REST v2 endpoint expects, `pp_MobileNumber`/`pp_CNIC` exact formats.
2. Easypaisa: sandbox REST base URL, REST v4 status/inquiry endpoint, postBackURL signature verification method, exact `merchantHashedReq` hash function for the classic flow.
3. Safepay: raw checkout URL format (use SDK instead), webhook event retry semantics.

## Recommended next step
Have Ali pull the **merchant onboarding doc packs** from the JazzCash sandbox portal (https://sandbox.jazzcash.com.pk) and the Easypaisa merchant portal — those packs are authoritative for the UNVERIFIED items. For Safepay, the public docs + sandbox account are sufficient.
