<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\User;

/**
 * A free book should cost one click, not a cart, a checkout and a payment page
 * with nothing to pay.
 */
test('claiming a free book lands the reader in the reader', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();

    $this->actingAs($user)
        ->post(route('books.claim', $book))
        ->assertRedirect(route('reader.show', $book->slug));

    expect($user->hasPurchased($book))->toBeTrue();
});

test('the claim leaves a real order behind', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();

    $this->actingAs($user)->post(route('books.claim', $book));

    // Ownership, downloads and order history all read from orders. A separate
    // path to "owns this book" is how the two drift apart.
    $order = Order::sole();

    expect($order->user_id)->toBe($user->id)
        ->and($order->isPaid())->toBeTrue()
        ->and($order->total_paise)->toBe(0)
        ->and($order->items)->toHaveCount(1);
});

test('a paid book cannot be claimed for nothing', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 19900]);

    $this->actingAs($user)
        ->post(route('books.claim', $book))
        ->assertRedirect(route('books.show', $book->slug));

    expect($user->hasPurchased($book))->toBeFalse()
        ->and(Order::count())->toBe(0);
});

test('claiming twice does not make a second order', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();

    $this->actingAs($user)->post(route('books.claim', $book));
    $this->actingAs($user)->post(route('books.claim', $book))
        ->assertRedirect(route('reader.show', $book->slug));

    expect(Order::count())->toBe(1);
});

test('a guest is sent to sign in, not handed the book', function () {
    $book = Book::factory()->free()->create();

    $this->post(route('books.claim', $book))->assertRedirect(route('login'));

    expect(Order::count())->toBe(0);
});

test('free books stay available while the gateway is in review', function () {
    config()->set('store.payments_enabled', false);

    $user = User::factory()->create();
    $book = Book::factory()->free()->create();

    // The hold is on taking money. There is none to take here.
    $this->actingAs($user)
        ->post(route('books.claim', $book))
        ->assertRedirect(route('reader.show', $book->slug));

    expect($user->hasPurchased($book))->toBeTrue();
});

test('a cart of only free books checks out without a gateway', function () {
    $user = User::factory()->create();
    $books = Book::factory()->free()->count(2)->create();

    $this->actingAs($user)
        ->withSession(['cart' => $books->pluck('id')->all()])
        ->post(route('checkout.store'))
        ->assertRedirectContains('/checkout/success/');

    expect(Order::sole()->isPaid())->toBeTrue()
        ->and(session('cart'))->toBeEmpty();

    // A gateway would reject a zero charge outright, so reaching one at all is
    // the bug — not merely wasteful.
    foreach ($books as $book) {
        expect($user->hasPurchased($book))->toBeTrue();
    }
});

test('a free cart checks out even with payments switched off', function () {
    config()->set('store.payments_enabled', false);

    $user = User::factory()->create();
    $book = Book::factory()->free()->create();

    $this->actingAs($user)
        ->withSession(['cart' => [$book->id]])
        ->post(route('checkout.store'))
        ->assertRedirectContains('/checkout/success/');

    expect($user->hasPurchased($book))->toBeTrue();
});

test('a mixed cart still goes through the gateway', function () {
    $user = User::factory()->create();
    $free = Book::factory()->free()->create();
    $paid = Book::factory()->create(['price_paise' => 19900]);

    $this->actingAs($user)
        ->withSession(['cart' => [$free->id, $paid->id]])
        ->post(route('checkout.store'))
        ->assertRedirectContains('/pay');

    // Nothing is owned until it is paid for, the free item included.
    expect($user->hasPurchased($free))->toBeFalse();
});

test('the free book is readable straight after claiming', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();

    $this->actingAs($user)->post(route('books.claim', $book));

    $this->actingAs($user)->get(route('reader.show', $book->slug))->assertOk();
    $this->actingAs($user)->get(route('library.index'))->assertOk();
});
