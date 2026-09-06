<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\RazorpayPaymentGateway;
use Illuminate\Support\Facades\Http;

/**
 * The real gateway, against a faked API. No test ever reaches Razorpay.
 */
beforeEach(function () {
    $this->gateway = new RazorpayPaymentGateway('rzp_test_key', 'test_secret', 'webhook_secret');

    $user = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 29900]);

    $this->order = $user->orders()->create([
        'order_number' => 'ORD-TEST123456',
        'status' => Order::STATUS_PENDING,
        'currency' => 'INR',
        'total_paise' => 29900,
        'subtotal_paise' => 29900,
    ]);

    $this->order->items()->create([
        'book_id' => $book->id,
        'title' => $book->title,
        'price_paise' => 29900,
        'subtotal_paise' => 29900,
    ]);
});

/** Sign the way Razorpay does, so the gateway's own check is exercised. */
function razorpaySignature(string $orderId, string $paymentId, string $secret = 'test_secret'): string
{
    return hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
}

test('creating a session sends the amount in paise', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_ABC123'], 200)]);

    $session = $this->gateway->createSession($this->order);

    Http::assertSent(function ($request) {
        return $request['amount'] === 29900          // paise, not rupees
            && $request['currency'] === 'INR'
            && $request['receipt'] === 'ORD-TEST123456';
    });

    expect($session->gatewayOrderId)->toBe('order_ABC123')
        ->and($session->publicKey)->toBe('rzp_test_key')
        ->and($this->order->fresh()->gateway_order_id)->toBe('order_ABC123');
});

test('a retried checkout reuses the gateway order instead of creating another', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_ABC123'], 200)]);

    $this->gateway->createSession($this->order);
    $this->gateway->createSession($this->order->fresh());

    // Reloading the pay page must not leave orphaned gateway orders behind.
    Http::assertSentCount(1);
});

test('a valid callback is confirmed against the API, not just the signature', function () {
    $this->order->update(['gateway_order_id' => 'order_ABC123']);

    Http::fake(['api.razorpay.com/v1/payments/*' => Http::response([
        'id' => 'pay_XYZ', 'amount' => 29900, 'status' => 'captured', 'method' => 'upi',
    ], 200)]);

    $result = $this->gateway->verifyCallback($this->order, [
        'razorpay_order_id' => 'order_ABC123',
        'razorpay_payment_id' => 'pay_XYZ',
        'razorpay_signature' => razorpaySignature('order_ABC123', 'pay_XYZ'),
    ]);

    expect($result->successful)->toBeTrue()
        ->and($result->reference)->toBe('pay_XYZ')
        ->and($result->amountPaise)->toBe(29900)
        ->and($result->method)->toBe('upi');
});

test('a tampered signature never reaches the API', function () {
    $this->order->update(['gateway_order_id' => 'order_ABC123']);

    Http::fake();

    $result = $this->gateway->verifyCallback($this->order, [
        'razorpay_order_id' => 'order_ABC123',
        'razorpay_payment_id' => 'pay_XYZ',
        'razorpay_signature' => str_repeat('a', 64),
    ]);

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});

test('a payment for the wrong amount is refused even with a valid signature', function () {
    $this->order->update(['gateway_order_id' => 'order_ABC123']);

    // Signature only proves the payload is ours. The API is the authority on
    // what was actually captured.
    Http::fake(['api.razorpay.com/v1/payments/*' => Http::response([
        'id' => 'pay_XYZ', 'amount' => 100, 'status' => 'captured',
    ], 200)]);

    $result = $this->gateway->verifyCallback($this->order, [
        'razorpay_order_id' => 'order_ABC123',
        'razorpay_payment_id' => 'pay_XYZ',
        'razorpay_signature' => razorpaySignature('order_ABC123', 'pay_XYZ'),
    ]);

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('does not match');
});

test('a payment that has not been captured is refused', function () {
    $this->order->update(['gateway_order_id' => 'order_ABC123']);

    Http::fake(['api.razorpay.com/v1/payments/*' => Http::response([
        'id' => 'pay_XYZ', 'amount' => 29900, 'status' => 'created',
    ], 200)]);

    $result = $this->gateway->verifyCallback($this->order, [
        'razorpay_order_id' => 'order_ABC123',
        'razorpay_payment_id' => 'pay_XYZ',
        'razorpay_signature' => razorpaySignature('order_ABC123', 'pay_XYZ'),
    ]);

    expect($result->successful)->toBeFalse();
});

test('a callback naming a different gateway order is refused', function () {
    $this->order->update(['gateway_order_id' => 'order_ABC123']);

    Http::fake();

    $result = $this->gateway->verifyCallback($this->order, [
        'razorpay_order_id' => 'order_SOMEONE_ELSE',
        'razorpay_payment_id' => 'pay_XYZ',
        'razorpay_signature' => razorpaySignature('order_SOMEONE_ELSE', 'pay_XYZ'),
    ]);

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});

test('webhook signatures are checked against the raw body', function () {
    $body = '{"event":"payment.captured"}';

    expect($this->gateway->verifyWebhook($body, hash_hmac('sha256', $body, 'webhook_secret')))->toBeTrue()
        // Re-serialising the body changes the bytes and must fail.
        ->and($this->gateway->verifyWebhook($body.' ', hash_hmac('sha256', $body, 'webhook_secret')))->toBeFalse()
        ->and($this->gateway->verifyWebhook($body, 'wrong'))->toBeFalse()
        ->and($this->gateway->verifyWebhook($body, null))->toBeFalse();
});

test('a gateway with no webhook secret rejects every webhook', function () {
    $gateway = new RazorpayPaymentGateway('k', 's', '');
    $body = '{"event":"payment.captured"}';

    expect($gateway->verifyWebhook($body, hash_hmac('sha256', $body, '')))->toBeFalse();
});

test('a refund defaults to the amount still outstanding', function () {
    Http::fake(['api.razorpay.com/v1/payments/*/refund' => Http::response([
        'id' => 'rfnd_1', 'amount' => 19900,
    ], 200)]);

    $payment = Payment::create([
        'order_id' => $this->order->id,
        'gateway' => 'razorpay',
        'gateway_payment_id' => 'pay_XYZ',
        'status' => Payment::STATUS_PARTIALLY_REFUNDED,
        'amount_paise' => 29900,
        'refunded_paise' => 10000,
    ]);

    $result = $this->gateway->refund($payment);

    Http::assertSent(fn ($request) => $request['amount'] === 19900);
    expect($result->successful)->toBeTrue();
});

test('a fully refunded payment cannot be refunded again', function () {
    Http::fake();

    $payment = Payment::create([
        'order_id' => $this->order->id,
        'gateway' => 'razorpay',
        'gateway_payment_id' => 'pay_XYZ',
        'status' => Payment::STATUS_REFUNDED,
        'amount_paise' => 29900,
        'refunded_paise' => 29900,
    ]);

    expect($this->gateway->refund($payment)->successful)->toBeFalse();
    Http::assertNothingSent();
});

test('the mock gateway is used when Razorpay is not configured', function () {
    config()->set('services.razorpay.key', null);

    expect(app(App\Services\Payments\PaymentGateway::class)->provider())->toBe('fake');
});
