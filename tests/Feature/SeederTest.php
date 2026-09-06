<?php

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Audit bug A8: a fresh clone landed on an empty catalogue, which makes the
 * app impossible to demo or evaluate. The seeder is the fix, so it is worth
 * keeping honest.
 */
test('the catalog seeder produces a browsable store', function () {
    Storage::fake('local');

    $this->seed(CatalogSeeder::class);

    expect(Genre::count())->toBeGreaterThan(5)
        ->and(Book::published()->count())->toBeGreaterThan(20);

    // The placeholder ebook must exist, or every seeded download 404s.
    expect(Storage::disk(Book::FILE_DISK)->exists('books/sample.pdf'))->toBeTrue();

    $this->get('/')->assertInertia(fn ($page) => $page->has('books.data', 12));
});

test('the seeder is safe to run twice', function () {
    Storage::fake('local');

    $this->seed(CatalogSeeder::class);
    $before = Book::count();

    $this->seed(CatalogSeeder::class);

    expect(Book::count())->toBe($before);
});

test('the seeded catalogue exercises the draft and free-book paths', function () {
    Storage::fake('local');

    $this->seed(CatalogSeeder::class);

    expect(Book::where('is_published', false)->exists())->toBeTrue()
        ->and(Book::where('price_paise', 0)->exists())->toBeTrue();
});

test('a seeded book can be bought and downloaded end to end', function () {
    Storage::fake('local');

    $this->seed(CatalogSeeder::class);

    $user = User::factory()->create();
    $book = Book::published()->where('price_paise', '>', 0)->first();

    completeCheckout($user, [$book->id]);

    $this->actingAs($user)
        ->get(route('library.download', $book))
        ->assertOk();
});
