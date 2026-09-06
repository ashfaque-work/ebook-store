<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Regression cover for audit bug A4.
 *
 * Against the mock gateway a double-submitted checkout is only an untidy extra
 * row. Against a real gateway it is a double charge and a refund request, so
 * the guards go in before the gateway does.
 */
test('a checkout stores an idempotency key derived from the buyer and the books', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    $order = Order::first();

    expect($order->idempotency_key)
        ->toBe(Order::idempotencyKeyFor($user, collect([$book])));
});

test('the key ignores the order the books appear in', function () {
    $user = User::factory()->create();
    $a = Book::factory()->create();
    $b = Book::factory()->create();

    expect(Order::idempotencyKeyFor($user, collect([$a, $b])))
        ->toBe(Order::idempotencyKeyFor($user, collect([$b, $a])));
});

test('two buyers of the same book get different keys', function () {
    $book = Book::factory()->create();

    expect(Order::idempotencyKeyFor(User::factory()->create(), collect([$book])))
        ->not->toBe(Order::idempotencyKeyFor(User::factory()->create(), collect([$book])));
});

test('a checkout arriving while another is in flight is turned away', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    // Stand in for a request that is already mid-checkout for this user.
    $lock = Cache::lock('checkout:'.$user->id, 15);
    expect($lock->get())->toBeTrue();

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post('/checkout')
        ->assertRedirect(route('cart.index'));

    // Nothing was charged and the cart is intact, so the customer can retry.
    expect(Order::count())->toBe(0);

    $lock->release();
});

test('resubmitting a cart that is already paid for returns the original order', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');
    $first = Order::sole();

    // The already-owned filter catches the sequential case and sends them to
    // their library rather than charging a second time.
    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post('/checkout')
        ->assertRedirect(route('library.index'));

    expect(Order::count())->toBe(1)
        ->and(Order::sole()->id)->toBe($first->id);
});

test('the idempotency key column rejects a duplicate order outright', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    $key = Order::sole()->idempotency_key;

    // The unique index is the last line of defence if both the lock and the
    // ownership filter are somehow bypassed.
    expect(fn () => Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-DUPLICATE',
        'idempotency_key' => $key,
        'status' => Order::STATUS_PENDING,
        'total' => 0,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('a receipt that fails to send does not fail a paid checkout', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    // With QUEUE_CONNECTION=sync on the free tier this runs inline, so a mail
    // outage would otherwise take checkout down with it (audit bug A6).
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post('/checkout')
        ->assertRedirect();

    expect(Order::sole()->status)->toBe(Order::STATUS_PAID)
        ->and($user->hasPurchased($book))->toBeTrue();
});
