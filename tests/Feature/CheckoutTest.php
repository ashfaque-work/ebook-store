<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\User;

test('checkout hands off to the gateway before anything is marked paid', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 1250]);

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post('/checkout')
        ->assertRedirect(route('checkout.pay', Order::sole()));

    $order = Order::sole();

    // The order exists and a gateway session is open, but nothing is paid for
    // until the gateway says so.
    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->gateway_order_id)->not->toBeNull()
        ->and($order->paid_at)->toBeNull()
        ->and($user->hasPurchased($book))->toBeFalse();
});

test('a verified callback pays the order, records items, and clears the cart', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 1250]);

    completeCheckout($user, [$book->id]);

    $order = Order::first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->total_paise)->toBe(1250)
        ->and($order->payment_reference)->not->toBeNull()
        ->and($order->items)->toHaveCount(1)
        ->and($user->hasPurchased($book))->toBeTrue();

    expect(session('cart'))->toBeNull();
});

test('checkout with an empty cart redirects back without creating an order', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/checkout')
        ->assertRedirect(route('cart.index'));

    expect(Order::count())->toBe(0);
});

test('a guest cannot check out', function () {
    $book = Book::factory()->create();

    $this->withSession(['cart' => [$book->id]])
        ->post('/checkout')
        ->assertRedirect('/login');

    expect(Order::count())->toBe(0);
});

test('already-owned books are not purchased again', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    // First purchase.
    completeCheckout($user, [$book->id]);
    // Second attempt with the same book.
    completeCheckout($user, [$book->id]);

    expect(Order::count())->toBe(1);
});
