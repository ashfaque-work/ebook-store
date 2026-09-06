<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Every page renders for the role that should see it.
 *
 * A Vue template error is invisible until the page is actually requested, and
 * these are the cheapest possible guard against a redesign quietly breaking a
 * screen nobody opened during development.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/sample.pdf', '%PDF-1.4');
    Storage::disk('local')->put('samples/one.pdf', '%PDF-1.4');

    $this->customer = User::factory()->create();
    $this->admin = User::factory()->admin()->create();

    $this->book = Book::factory()->create([
        'file_path' => 'books/sample.pdf',
        'sample_path' => 'samples/one.pdf',
        'excerpt' => 'It was the hour of the unexpected guest.',
    ]);

    completeCheckout($this->customer, [$this->book->id]);
});

test('public pages render', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/',
    '/?search=the',
    '/cart',
    '/login',
    '/register',
    '/forgot-password',
    '/terms',
    '/privacy',
    '/refunds',
    '/delivery',
    '/contact',
]);

test('the book page renders', function () {
    $this->get(route('books.show', $this->book))->assertOk();
});

test('a genre filter renders', function () {
    // Not in the dataset above: datasets resolve before the database exists.
    $this->get('/?genre='.Genre::first()->slug)->assertOk();
});

test('the sample reader renders for a guest', function () {
    $this->get(route('reader.sample', $this->book))->assertOk();
});

test('customer pages render', function (string $route) {
    $this->actingAs($this->customer)->get($route)->assertOk();
})->with([
    '/library',
    '/orders',
    '/profile',
]);

test('the reader renders for an owner', function () {
    $this->actingAs($this->customer)
        ->get(route('reader.show', $this->book))
        ->assertOk();
});

test('a customer order receipt renders', function () {
    $this->actingAs($this->customer)
        ->get(route('orders.show', Order::sole()))
        ->assertOk();
});

test('the checkout success page renders', function () {
    $this->actingAs($this->customer)
        ->get(route('checkout.success', Order::sole()))
        ->assertOk();
});

test('the pay page renders', function () {
    $book = Book::factory()->create();
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->withSession(['cart' => [$book->id]])->post('/checkout');

    $this->actingAs($buyer)
        ->get(route('checkout.pay', Order::where('user_id', $buyer->id)->sole()))
        ->assertOk();
});

test('admin pages render', function (string $route) {
    $this->actingAs($this->admin)->get($route)->assertOk();
})->with([
    '/admin/books',
    '/admin/books/create',
    '/admin/authors',
    '/admin/authors/create',
    '/admin/genres',
    '/admin/genres/create',
    '/admin/orders',
]);

test('admin edit screens render', function () {
    $admin = $this->admin;

    $this->actingAs($admin)->get(route('admin.books.edit', $this->book))->assertOk();
    $this->actingAs($admin)->get(route('admin.authors.edit', Author::first()))->assertOk();
    $this->actingAs($admin)->get(route('admin.genres.edit', Genre::first()))->assertOk();
    $this->actingAs($admin)->get(route('admin.orders.show', Order::sole()))->assertOk();
});

test('a missing page gets our 404 rather than the framework default', function () {
    // The errors:: namespace only exists while an exception is being
    // rendered, so this has to go through a real request to mean anything.
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('Browse the shelves');
});

test('a forbidden page gets our 403', function () {
    $this->actingAs($this->customer)
        ->get('/admin/books')
        ->assertForbidden()
        ->assertSee('Go to my library');
});

test('every error view we ship is present', function (int $status) {
    expect(file_exists(resource_path("views/errors/{$status}.blade.php")))->toBeTrue();
})->with([403, 404, 419, 429, 500, 503]);
