<?php

use App\Models\Book;
use App\Models\User;

/**
 * The whole risk of moving to PostgreSQL lives here.
 *
 * Postgres `LIKE` is case-sensitive; MySQL and SQLite are not. A plain `like`
 * would keep passing on SQLite forever while returning nothing in production
 * for a lowercase query — wrong results, no error, no exception in a log.
 */
test('catalogue search ignores case', function (string $term) {
    Book::factory()->create(['title' => 'Harry and the Monsoon']);

    $this->get('/?search='.$term)
        ->assertInertia(fn ($page) => $page->has('books.data', 1));
})->with(['harry', 'HARRY', 'HaRrY', 'Monsoon', 'monsoon']);

test('catalogue search by author ignores case', function () {
    Book::factory()->create();
    $book = Book::factory()->create();
    $book->author->update(['name' => 'Anita Rau']);

    $this->get('/?search=anita')
        ->assertInertia(fn ($page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.id', $book->id)
        );
});

test('search still matches nothing when it should', function () {
    Book::factory()->create(['title' => 'Harry and the Monsoon']);

    // Case-insensitive must not mean "matches everything".
    $this->get('/?search=zzzznope')
        ->assertInertia(fn ($page) => $page->has('books.data', 0));
});

test('admin order search ignores case', function () {
    $customer = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
    completeCheckout($customer, [Book::factory()->create()->id]);

    foreach (['priya', 'PRIYA', 'Sharma', 'PRIYA@EXAMPLE.COM'] as $term) {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index', ['search' => $term]))
            ->assertInertia(fn ($page) => $page->has('orders.data', 1));
    }
});
