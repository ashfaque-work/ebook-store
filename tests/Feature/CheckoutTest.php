<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\User;

test('checkout creates a paid order, records items, and clears the cart', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['price' => 12.50]);

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post('/checkout')
        ->assertRedirect();

    $order = Order::first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(Order::STATUS_PAID)
        ->and((float) $order->total)->toBe(12.50)
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
    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');
    // Second attempt with the same book.
    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    expect(Order::count())->toBe(1);
});
