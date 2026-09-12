<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGateway;

/**
 * The simulated gateway is how the whole checkout gets rehearsed without live
 * keys, so its outcomes have to drive the same code Razorpay would — not a
 * shortcut that happens to end in a paid order.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create(['price_paise' => 29900]);

    $this->actingAs($this->user)
        ->withSession(['cart' => [$this->book->id]])
        ->post('/checkout');

    $this->order = Order::sole();
});

test('a simulated success verifies, fulfils and records a payment', function () {
    $this->actingAs($this->user)
        ->post(route('checkout.simulate', $this->order), ['outcome' => 'paid'])
        ->assertRedirect(route('checkout.success', $this->order));

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($this->order->fresh()->invoice_number)->not->toBeNull()
        ->and(Payment::sole()->status)->toBe(Payment::STATUS_CAPTURED)
        ->and($this->user->hasPurchased($this->book))->toBeTrue();
});

test('a simulated decline fails the order and leaves the cart alone', function () {
    $this->actingAs($this->user)
        ->withSession(['cart' => [$this->book->id]])
        ->post(route('checkout.simulate', $this->order), ['outcome' => 'failed'])
        ->assertRedirect(route('cart.index'))
        ->assertSessionHas('cart');

    expect($this->order->fresh()->status)->toBe(Order::STATUS_FAILED)
        ->and($this->order->fresh()->failure_reason)->toContain('refused')
        ->and($this->user->hasPurchased($this->book))->toBeFalse();
});

test('an abandoned payment leaves the order payable', function () {
    $this->actingAs($this->user)
        ->post(route('checkout.simulate', $this->order), ['outcome' => 'abandoned'])
        ->assertRedirect(route('cart.index'));

    // Still pending, so the customer can come back and pay for it.
    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and(Payment::count())->toBe(0);
});

test('the webhook-only outcome pays the order without the browser callback', function () {
    $this->actingAs($this->user)
        ->post(route('checkout.simulate', $this->order), ['outcome' => 'webhook'])
        ->assertRedirect(route('library.index'));

    // Fulfilled through the real webhook controller, so a signed event was
    // recorded on the way — this is the path that saves a closed tab.
    expect($this->order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and(WebhookEvent::count())->toBe(1)
        ->and(WebhookEvent::sole()->processed_at)->not->toBeNull()
        ->and($this->user->hasPurchased($this->book))->toBeTrue();
});

test('simulating twice does not pay twice', function () {
    $this->actingAs($this->user)->post(route('checkout.simulate', $this->order), ['outcome' => 'paid']);
    $this->actingAs($this->user)->post(route('checkout.simulate', $this->order), ['outcome' => 'paid']);

    expect(Payment::count())->toBe(1)
        ->and(Order::count())->toBe(1);
});

test('an unknown outcome is rejected', function () {
    $this->actingAs($this->user)
        ->post(route('checkout.simulate', $this->order), ['outcome' => 'free-books-please'])
        ->assertSessionHasErrors('outcome');

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('nobody can simulate a payment on somebody else\'s order', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('checkout.simulate', $this->order), ['outcome' => 'paid'])
        ->assertForbidden();

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('the simulator disappears once a real gateway is configured', function () {
    config()->set('services.razorpay.key', 'rzp_test_key');
    config()->set('services.razorpay.secret', 'secret');
    app()->forgetInstance(PaymentGateway::class);

    // Not merely hidden in the UI — the route itself stops existing.
    $this->actingAs($this->user)
        ->post(route('checkout.simulate', $this->order), ['outcome' => 'paid'])
        ->assertNotFound();

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('production refuses to fall back to the simulated gateway', function () {
    // A deploy that forgot the keys would otherwise hand out free books while
    // looking perfectly healthy.
    config()->set('services.razorpay.key', null);
    config()->set('services.razorpay.secret', null);
    app()->detectEnvironment(fn () => 'production');
    app()->forgetInstance(PaymentGateway::class);

    expect(fn () => app(PaymentGateway::class))
        ->toThrow(RuntimeException::class, 'No payment gateway is configured');
});
