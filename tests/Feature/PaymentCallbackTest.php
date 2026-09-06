<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create(['price_paise' => 29900]);

    $this->actingAs($this->user)
        ->withSession(['cart' => [$this->book->id]])
        ->post('/checkout');

    $this->order = Order::sole();
});

test('a correctly signed callback pays the order', function () {
    $this->actingAs($this->user)
        ->post(route('checkout.verify', $this->order), callbackPayloadFor($this->order))
        ->assertRedirect(route('checkout.success', $this->order));

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($this->user->hasPurchased($this->book))->toBeTrue();
});

test('a tampered signature is rejected and nothing is delivered', function () {
    $payload = callbackPayloadFor($this->order);
    $payload['razorpay_signature'] = str_repeat('0', 64);

    $this->actingAs($this->user)
        ->post(route('checkout.verify', $this->order), $payload)
        ->assertRedirect(route('cart.index'));

    expect($this->order->fresh()->status)->toBe(Order::STATUS_FAILED)
        ->and($this->user->hasPurchased($this->book))->toBeFalse();
});

test('a callback signed for a different order is rejected', function () {
    // Correctly signed, but for somebody else's gateway order.
    $other = User::factory()->create();
    $otherBook = Book::factory()->create();

    $this->actingAs($other)->withSession(['cart' => [$otherBook->id]])->post('/checkout');
    $otherOrder = Order::where('user_id', $other->id)->sole();

    $this->actingAs($this->user)
        ->post(route('checkout.verify', $this->order), callbackPayloadFor($otherOrder))
        ->assertRedirect(route('cart.index'));

    expect($this->order->fresh()->status)->not->toBe(Order::STATUS_PAID);
});

test('a callback cannot be replayed to pay somebody else\'s order', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('checkout.verify', $this->order), callbackPayloadFor($this->order))
        ->assertForbidden();

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('an incomplete callback is refused before any verification', function () {
    $this->actingAs($this->user)
        ->post(route('checkout.verify', $this->order), ['razorpay_payment_id' => 'pay_x'])
        ->assertSessionHasErrors(['razorpay_order_id', 'razorpay_signature']);

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('paying records the payment for audit', function () {
    $payload = callbackPayloadFor($this->order);

    $this->actingAs($this->user)->post(route('checkout.verify', $this->order), $payload);

    $payment = Payment::sole();

    expect($payment->gateway_payment_id)->toBe($payload['razorpay_payment_id'])
        ->and($payment->amount_paise)->toBe(29900)
        ->and($payment->status)->toBe(Payment::STATUS_CAPTURED)
        ->and($payment->order_id)->toBe($this->order->id);
});

test('the pay page is not reachable by another customer', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('checkout.pay', $this->order))
        ->assertForbidden();
});

test('an already paid order skips the pay page', function () {
    $this->actingAs($this->user)->post(route('checkout.verify', $this->order), callbackPayloadFor($this->order));

    $this->actingAs($this->user)
        ->get(route('checkout.pay', $this->order))
        ->assertRedirect(route('checkout.success', $this->order));
});

test('the pay page never exposes a gateway secret', function () {
    $this->actingAs($this->user)
        ->get(route('checkout.pay', $this->order))
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Pay')
            ->where('session.publicKey', 'fake_key')
            ->missing('session.secret')
        );
});
