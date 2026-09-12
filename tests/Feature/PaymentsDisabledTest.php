<?php

use App\Models\Book;
use App\Models\User;

/**
 * The window between going live and Razorpay's KYC clearing.
 *
 * The store has to be public in that window — Razorpay reads the legal pages
 * on a live URL as part of the review — so everything except taking money
 * stays open, and the one thing that is closed says so plainly.
 */
beforeEach(function () {
    config()->set('store.payments_enabled', false);
});

test('checkout is closed, and says so instead of erroring', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post(route('checkout.store'))
        ->assertRedirect(route('cart.index'))
        ->assertSessionHas('toast.type', 'info');
});

test('the cart survives the attempt', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post(route('checkout.store'));

    // Nothing is more annoying than rebuilding a cart you did not empty.
    expect(session('cart'))->toBe([$book->id]);
});

test('no order is created while selling is off', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post(route('checkout.store'));

    expect($user->orders()->count())->toBe(0);
});

test('the front end is told, so the button can say the right thing', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('store.paymentsEnabled', false));
});

test('browsing, samples and the catalogue are untouched', function () {
    $book = Book::factory()->create();

    $this->get('/')->assertOk();
    $this->get(route('books.show', $book->slug))->assertOk();
    $this->get(route('cart.index'))->assertOk();
});

test('a book already owned still reads', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    // Closing the till must not lock existing customers out of what they own.
    config()->set('store.payments_enabled', true);
    completeCheckout($user, [$book->id]);
    config()->set('store.payments_enabled', false);

    $this->actingAs($user)->get(route('reader.show', $book->slug))->assertOk();
    $this->actingAs($user)->get(route('library.index'))->assertOk();
});

test('checkout reopens the moment it is switched back on', function () {
    config()->set('store.payments_enabled', true);

    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post(route('checkout.store'))
        ->assertRedirectContains('/pay');
});
