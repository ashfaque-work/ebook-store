# 04 — Payments (India)

---

## 1. Gateway choice

**Stripe.** It does operate in India, but for domestic Indian businesses it is
restricted/invite-only and oriented toward export. Planning around it is the right call.

**Recommendation: Razorpay.**

| | Razorpay | Cashfree | Instamojo | PhonePe PG |
|---|---|---|---|---|
| Onboarding | PAN + bank + website | similar | easiest for individuals | needs a registered business |
| Methods | UPI, cards, netbanking, wallets, EMI | same | UPI, cards, netbanking | UPI-first |
| Pricing | ~2% + GST | ~1.75–2% | ~2–5% | ~2% |
| Docs / SDK | best in class | good | dated | improving |
| Official PHP SDK | yes | yes | yes | limited |
| Settlement | T+2 (T+1 paid) | T+2 | T+3 | T+1 |

Razorpay wins on documentation, PHP SDK quality, and webhook design — which matters more
than a 0.25% rate difference at your volume. Cashfree is the fallback if KYC stalls.
Instamojo if you have no registered business at all and want to start this week.

**Later, for international sales:** a merchant-of-record like **Paddle** or **Lemon Squeezy**
takes a higher cut but handles global VAT, chargebacks and invoicing. Add it as a second
gateway behind the same contract rather than replacing Razorpay.

### Before you write any code

Razorpay activation requires these to be **live and reachable**:

1. Terms & Conditions
2. Privacy Policy
3. Refund & Cancellation Policy
4. Shipping / Delivery Policy (for digital goods: "delivered instantly to your library")
5. Contact Us with a real email and address

Plus PAN, bank account, and a business name. Sole proprietors qualify. Approval takes
2–7 working days. **Start this on day one of Phase B** — it is the long pole, and people
routinely discover it after building everything else. Content outlines are in
[09-SEO-LEGAL.md](09-SEO-LEGAL.md).

---

## 2. The contract has to change

The current interface is synchronous:

```php
public function charge(Order $order, array $paymentDetails = []): PaymentResult;
```

Razorpay is create-session → client modal → callback → webhook. Nothing about that fits
`charge()`. Replace it:

```php
namespace App\Services\Payments;

interface PaymentGateway
{
    /** Create a gateway-side order and return what the client needs to open checkout. */
    public function createSession(Order $order): PaymentSession;

    /** Verify the browser callback. Never trust it without this. */
    public function verifyCallback(array $payload): PaymentResult;

    /** Verify a webhook body against its signature header. */
    public function verifyWebhook(string $rawBody, string $signature): bool;

    /** Refund a captured payment, fully or partially. */
    public function refund(Payment $payment, ?int $amountPaise = null): PaymentResult;
}

final class PaymentSession
{
    public function __construct(
        public readonly string $gatewayOrderId,
        public readonly int $amountPaise,
        public readonly string $currency,
        public readonly string $publicKey,
    ) {}
}
```

`FakePaymentGateway` implements the same four methods and stays bound in `local` and
`testing`, so the whole suite runs with no network:

```php
// AppServiceProvider::register()
$this->app->bind(PaymentGateway::class, fn () =>
    app()->environment('production')
        ? new RazorpayPaymentGateway(config('services.razorpay'))
        : new FakePaymentGateway()
);
```

---

## 3. The flow

```
1. POST /checkout
   PlaceOrder: build order (pending, paise) + order_items, under a cache lock
   RazorpayPaymentGateway::createSession() → Orders API → razorpay_order_id
   render Checkout/Pay.vue with { gatewayOrderId, amountPaise, publicKey, prefill }

2. Client opens the Razorpay modal (checkout.razorpay.com/v1/checkout.js)
   User pays by UPI / card / netbanking

3a. Browser callback → POST /checkout/verify
    verify HMAC_SHA256(razorpay_order_id + '|' + razorpay_payment_id, key_secret)
      === razorpay_signature
    → FulfilOrder → redirect to success

3b. Webhook → POST /webhooks/razorpay   (fires regardless of what the browser did)
    verify HMAC_SHA256(raw body, webhook_secret) === X-Razorpay-Signature
    handle payment.captured / order.paid / payment.failed / refund.processed
    → FulfilOrder
```

**3a and 3b both call `FulfilOrder`, and it must be idempotent.** The customer who pays and
immediately closes the tab is served entirely by the webhook. The customer on a flaky mobile
connection may trigger both within a second of each other. Every payment bug in a store like
this comes from those two paths disagreeing.

```php
final class FulfilOrder
{
    public function __invoke(Order $order, array $gatewayData): void
    {
        DB::transaction(function () use ($order, $gatewayData) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($order->isPaid()) {
                return;                       // already done — this is the whole point
            }

            $order->update([
                'status' => Order::STATUS_PAID,
                'gateway_payment_id' => $gatewayData['payment_id'],
                'paid_at' => now(),
            ]);

            Payment::updateOrCreate(
                ['gateway_payment_id' => $gatewayData['payment_id']],
                [...]
            );
        });

        SendReceipt::dispatch($order);        // outside the transaction
    }
}
```

### Signature verification

```php
// callback
$expected = hash_hmac('sha256', $orderId.'|'.$paymentId, config('services.razorpay.secret'));
if (! hash_equals($expected, $signature)) { abort(400); }

// webhook — must use the RAW body, not the parsed array
$expected = hash_hmac('sha256', $request->getContent(), config('services.razorpay.webhook_secret'));
if (! hash_equals($expected, $request->header('X-Razorpay-Signature', ''))) { abort(400); }
```

`hash_equals`, never `===` — timing attacks are cheap to prevent here.
Re-serialising the webhook body changes the bytes and breaks the signature. Take
`$request->getContent()`.

### Route and middleware

```php
// routes/web.php — outside the CSRF-protected web group
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class]);
```

Log every webhook into `webhook_events` **before** processing, keyed on the gateway event id.
A duplicate insert means you have already handled it — return 200 and stop. Always return 200
for anything you have successfully received, even if you choose to ignore it; a non-200 makes
Razorpay retry for hours.

---

## 4. Config

```php
// config/services.php
'razorpay' => [
    'key'            => env('RAZORPAY_KEY'),
    'secret'         => env('RAZORPAY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
],
```

```
# .env — test keys start rzp_test_, live keys rzp_live_
RAZORPAY_KEY=rzp_test_xxxxxxxx
RAZORPAY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
```

`composer require razorpay/razorpay`. The secret never reaches the client — only
`RAZORPAY_KEY` is sent to the browser.

**CSP:** the checkout script needs `script-src https://checkout.razorpay.com` and
`frame-src https://api.razorpay.com`. Add these when you set security headers in Phase E,
or checkout silently fails to open.

---

## 5. Amounts

Razorpay takes `amount` as an **integer in paise**. ₹499.00 is `49900`.

Send the amount computed server-side from `order_items`, never a client value, and verify on
callback that the gateway's amount matches the order's `total_paise`. A mismatch means
tampering — fail loudly and do not fulfil.

See [03-DATABASE.md](03-DATABASE.md) §1 for the paise migration. Do it *before* the
integration, not after; retrofitting a currency representation under a live gateway is
miserable.

---

## 6. GST

Digital goods sold in India attract GST. Ebooks are **5%** where a printed edition of the same
title exists, and **18%** otherwise. That distinction is per-title, so it belongs on the book
row (`tax_rate`), not hardcoded.

What the schema needs (already in [03-DATABASE.md](03-DATABASE.md)):

- **Place of supply** — the buyer's state. Same state as you → CGST + SGST; different state → IGST. Collect `state_code` at checkout.
- **Invoice series** — sequential, gapless, per financial year (e.g. `INV/2026-27/000123`). Generate on fulfilment, never reuse.
- **Tax breakdown** stored on the order, not recomputed at display time — rates change.

Registration thresholds depend on turnover and whether you supply inter-state. **Talk to a
CA before you take the first payment.** An hour of their time is cheaper than reconstructing
a year of invoices. This document is not tax advice.

---

## 7. Refunds

- Admin action on the order → `PaymentGateway::refund()` → `orders.status = refunded`, `refunded_paise` set.
- **Revoke library and reader access** when a refund is full. `hasPurchased()` filters on `status = paid`, so this works automatically — verify it with a test.
- Publish the policy and honour it. Razorpay weighs dispute rates.
- Refunds settle in 5–7 working days; say so in the confirmation so people do not chargeback out of impatience.

For digital goods a common stance is: refunds within 7 days if the book has not been
downloaded or read past ~10%. You have `download_logs` and `reading_progress` to enforce that
fairly — use them rather than a blanket no-refunds policy, which Razorpay dislikes.

---

## 8. Testing

- Unit-test signature verification with known-good and tampered payloads.
- Test `FulfilOrder` called twice produces one paid order, one payment row, one receipt.
- Test an amount mismatch is rejected.
- Test the webhook with a replayed event id.
- Test refund revokes library access.
- Use Razorpay **test mode** end-to-end; their test UPI and card details are in the dashboard.
- Expose local webhooks with `ngrok` or `expose` while developing.

**Launch check:** a real ₹1 purchase on live keys, then a real refund of it.
