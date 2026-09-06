<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create();
    $this->book = Book::factory()->create(['price_paise' => 29900]);

    completeCheckout($this->customer, [$this->book->id]);

    $this->order = Order::sole();
});

test('an admin can see the orders list', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('stats.paidCount', 1)
            ->where('stats.grossPaise', 29900)
        );
});

test('a customer cannot see the orders list', function () {
    $this->actingAs($this->customer)
        ->get(route('admin.orders.index'))
        ->assertForbidden();
});

test('orders can be filtered by status and searched', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['status' => 'refunded']))
        ->assertInertia(fn ($page) => $page->has('orders.data', 0));

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['search' => $this->order->order_number]))
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

test('a full refund revokes the customer\'s access', function () {
    expect($this->customer->hasPurchased($this->book))->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.refund', $this->order), ['reason' => 'Wrong edition'])
        ->assertRedirect();

    expect($this->order->fresh()->status)->toBe(Order::STATUS_REFUNDED)
        ->and($this->order->fresh()->refunded_paise)->toBe(29900)
        ->and($this->customer->fresh()->hasPurchased($this->book))->toBeFalse();

    $this->actingAs($this->customer)
        ->get(route('library.download', $this->book))
        ->assertForbidden();
});

test('a partial refund leaves the books in the library', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.orders.refund', $this->order), ['amount' => '99.00']);

    expect($this->order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($this->order->fresh()->refunded_paise)->toBe(9900)
        ->and($this->customer->hasPurchased($this->book))->toBeTrue();
});

test('rupees in the form become exact paise', function () {
    // The float trap: (int) (19.99 * 100) is 1998.
    $this->actingAs($this->admin)
        ->post(route('admin.orders.refund', $this->order), ['amount' => '19.99']);

    expect(Refund::sole()->amount_paise)->toBe(1999);
});

test('a refund larger than what is left is refused', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.orders.refund', $this->order), ['amount' => '500.00'])
        ->assertRedirect();

    expect(Refund::count())->toBe(0)
        ->and($this->order->fresh()->status)->toBe(Order::STATUS_PAID);
});

test('an order cannot be refunded twice over', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $this->order));
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $this->order));

    expect(Refund::count())->toBe(1)
        ->and($this->order->fresh()->refunded_paise)->toBe(29900);
});

test('the webhook does not double-count a refund we issued ourselves', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $this->order), ['amount' => '99.00']);

    $refundId = Refund::sole()->gateway_refund_id;
    $paymentId = Payment::sole()->gateway_payment_id;

    // The gateway reports the same refund back to us moments later.
    postWebhook([
        'event' => 'refund.processed',
        'payload' => ['refund' => ['entity' => [
            'id' => $refundId, 'payment_id' => $paymentId, 'amount' => 9900,
        ]]],
    ])->assertOk();

    expect(Refund::count())->toBe(1)
        ->and($this->order->fresh()->refunded_paise)->toBe(9900);
});

test('two partial refunds add up to a full one and revoke access', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $this->order), ['amount' => '150.00']);
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $this->order), ['amount' => '149.00']);

    expect(Refund::count())->toBe(2)
        ->and($this->order->fresh()->refunded_paise)->toBe(29900)
        ->and($this->order->fresh()->status)->toBe(Order::STATUS_REFUNDED)
        ->and($this->customer->fresh()->hasPurchased($this->book))->toBeFalse();
});

test('an unpaid order cannot be refunded', function () {
    $pending = User::factory()->create();
    $this->actingAs($pending)->withSession(['cart' => [$this->book->id]])->post('/checkout');

    $order = Order::where('user_id', $pending->id)->sole();

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order));

    expect(Refund::count())->toBe(0);
});

test('a customer cannot issue a refund', function () {
    $this->actingAs($this->customer)
        ->post(route('admin.orders.refund', $this->order))
        ->assertForbidden();

    expect(Refund::count())->toBe(0);
});
