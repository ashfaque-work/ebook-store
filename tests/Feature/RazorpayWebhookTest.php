<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;

/**
 * The webhook is the source of truth for payment: it arrives whether or not
 * the customer's browser survives checkout. These are the highest-value tests
 * in the project, because a bug here costs money rather than face.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create(['price_paise' => 29900]);

    $this->actingAs($this->user)
        ->withSession(['cart' => [$this->book->id]])
        ->post('/checkout');

    $this->order = Order::sole();
});

/** Post a webhook the way the gateway does: no session, no CSRF token. */
function postWebhook(array $payload, ?string $signature = null, ?string $eventId = null)
{
    [$raw, $sig] = signedWebhook($payload);

    return test()->call(
        'POST',
        route('webhooks.razorpay'),
        [], [], [],
        array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? $sig,
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId ?? 'evt_'.bin2hex(random_bytes(8)),
        ]),
        $raw,
    );
}

test('a signed webhook pays the order with no browser involved', function () {
    postWebhook(capturedWebhookFor($this->order))->assertOk();

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($this->user->hasPurchased($this->book))->toBeTrue();
});

test('an unsigned webhook is rejected and changes nothing', function () {
    postWebhook(capturedWebhookFor($this->order), signature: 'not-a-signature')
        ->assertStatus(400);

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and(WebhookEvent::count())->toBe(0);
});

test('a webhook with no signature header at all is rejected', function () {
    [$raw] = signedWebhook(capturedWebhookFor($this->order));

    $this->call('POST', route('webhooks.razorpay'), [], [], [],
        ['CONTENT_TYPE' => 'application/json'], $raw)
        ->assertStatus(400);

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('replaying the same event changes nothing', function () {
    $payload = capturedWebhookFor($this->order);
    $eventId = 'evt_replayed';

    postWebhook($payload, eventId: $eventId)->assertOk();
    postWebhook($payload, eventId: $eventId)->assertOk();

    // One fulfilment, one payment row, one invoice number.
    expect(Payment::count())->toBe(1)
        ->and(WebhookEvent::count())->toBe(1)
        ->and(Order::sole()->status)->toBe(Order::STATUS_PAID);
});

test('the browser callback and the webhook together fulfil exactly once', function () {
    $paymentId = 'fake_pay_bothpaths';

    // The customer's browser gets back first...
    $this->actingAs($this->user)->post(
        route('checkout.verify', $this->order),
        callbackPayloadFor($this->order, $paymentId),
    );

    // ...and the webhook arrives a moment later for the same payment.
    postWebhook(capturedWebhookFor($this->order, $paymentId))->assertOk();

    expect(Payment::count())->toBe(1)
        ->and(Order::sole()->status)->toBe(Order::STATUS_PAID)
        ->and(Order::sole()->paid_at)->not->toBeNull();
});

test('a webhook whose amount disagrees with the order is not fulfilled', function () {
    postWebhook(capturedWebhookFor($this->order, amountPaise: 100))->assertOk();

    // Tampering, or a gateway mismatch. Either way it is not a sale.
    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and($this->order->fresh()->failure_reason)->not->toBeNull()
        ->and($this->user->hasPurchased($this->book))->toBeFalse();
});

test('a webhook for an unknown order is acknowledged and ignored', function () {
    $payload = capturedWebhookFor($this->order);
    $payload['payload']['payment']['entity']['order_id'] = 'order_does_not_exist';

    postWebhook($payload)->assertOk();

    expect(Order::sole()->status)->toBe(Order::STATUS_PENDING);
});

test('a failed payment marks the order failed', function () {
    $payload = capturedWebhookFor($this->order);
    $payload['event'] = 'payment.failed';
    $payload['payload']['payment']['entity']['error_description'] = 'Card declined by issuer.';

    postWebhook($payload)->assertOk();

    expect($this->order->fresh()->status)->toBe(Order::STATUS_FAILED)
        ->and($this->order->fresh()->failure_reason)->toBe('Card declined by issuer.');
});

test('a late failure never downgrades an order that is already paid', function () {
    postWebhook(capturedWebhookFor($this->order))->assertOk();

    $failure = capturedWebhookFor($this->order);
    $failure['event'] = 'payment.failed';

    postWebhook($failure)->assertOk();

    // A failed first attempt can arrive after a successful retry.
    expect($this->order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($this->user->hasPurchased($this->book))->toBeTrue();
});

test('a refund revokes access to the book', function () {
    $paymentId = 'fake_pay_refundme';
    postWebhook(capturedWebhookFor($this->order, $paymentId))->assertOk();

    expect($this->user->hasPurchased($this->book))->toBeTrue();

    postWebhook([
        'event' => 'refund.processed',
        'payload' => ['refund' => ['entity' => [
            'id' => 'rfnd_1', 'payment_id' => $paymentId, 'amount' => 29900,
        ]]],
    ])->assertOk();

    // hasPurchased() only counts paid orders, so revocation falls out of
    // marking the order refunded rather than needing its own bookkeeping.
    expect($this->order->fresh()->status)->toBe(Order::STATUS_REFUNDED)
        ->and($this->user->fresh()->hasPurchased($this->book))->toBeFalse()
        ->and(Payment::sole()->status)->toBe(Payment::STATUS_REFUNDED);
});

test('a partial refund leaves access in place', function () {
    $paymentId = 'fake_pay_partial';
    postWebhook(capturedWebhookFor($this->order, $paymentId))->assertOk();

    postWebhook([
        'event' => 'refund.processed',
        'payload' => ['refund' => ['entity' => [
            'id' => 'rfnd_2', 'payment_id' => $paymentId, 'amount' => 10000,
        ]]],
    ])->assertOk();

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($this->user->hasPurchased($this->book))->toBeTrue()
        ->and(Payment::sole()->status)->toBe(Payment::STATUS_PARTIALLY_REFUNDED)
        ->and(Payment::sole()->refunded_paise)->toBe(10000);
});

test('an unrecognised event is acknowledged so the gateway stops retrying', function () {
    $payload = capturedWebhookFor($this->order);
    $payload['event'] = 'subscription.charged';

    postWebhook($payload)->assertOk();

    expect(WebhookEvent::sole()->processed_at)->not->toBeNull();
});
