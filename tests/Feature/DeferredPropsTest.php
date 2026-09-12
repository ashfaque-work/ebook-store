<?php

use App\Models\Book;
use App\Models\Genre;

/**
 * Deferred props are invisible to an ordinary page assertion: the closure does
 * not run on the first response, so a query inside one can be broken for weeks
 * while every test stays green. Found exactly that way — the genre shelves
 * threw a 500 in the browser while 225 tests passed.
 *
 * Every deferred prop needs a test that actually asks for it.
 *
 * These assert on the JSON rather than through assertInertia: a partial reload
 * returns only the requested props, without the url/version envelope that
 * AssertableInertia insists on.
 */
test('the genre shelves resolve when the browser asks for them', function () {
    $genre = Genre::factory()->create(['name' => 'Literary Fiction']);
    Book::factory()->count(6)->create(['genre_id' => $genre->id]);

    $shelves = inertiaPartial('/', 'Welcome', 'shelves')->assertOk()->json('props.shelves');

    expect($shelves)->not->toBeEmpty()
        ->and($shelves[0])->toHaveKeys(['name', 'slug', 'books'])
        ->and($shelves[0]['books'][0])->toHaveKeys(['id', 'slug', 'title', 'price_paise', 'author']);
});

test('a genre with no published books is left off the shelves', function () {
    Genre::factory()->create(['name' => 'Empty Shelf', 'slug' => 'empty-shelf']);
    $stocked = Genre::factory()->create(['name' => 'Stocked']);
    Book::factory()->count(2)->create(['genre_id' => $stocked->id]);

    $names = collect(inertiaPartial('/', 'Welcome', 'shelves')->json('props.shelves'))->pluck('name');

    expect($names)->not->toContain('Empty Shelf');
});

test('a shelf is never returned empty', function () {
    // The featured book is excluded from the shelves so it does not appear
    // twice on one screen — which can empty a genre that only had the one.
    $lonely = Genre::factory()->create(['name' => 'One Book Only']);
    Book::factory()->create(['genre_id' => $lonely->id, 'is_featured' => true]);

    $other = Genre::factory()->create();
    Book::factory()->count(3)->create(['genre_id' => $other->id]);

    $shelves = inertiaPartial('/', 'Welcome', 'shelves')->json('props.shelves');

    foreach ($shelves as $shelf) {
        expect($shelf['books'])->not->toBeEmpty("shelf '{$shelf['name']}' came back empty");
    }
});

test('the featured book is not repeated on the shelves below it', function () {
    $genre = Genre::factory()->create();
    Book::factory()->count(4)->create(['genre_id' => $genre->id]);

    $featured = $this->get('/')->viewData('page')['props']['featured'];

    $ids = collect(inertiaPartial('/', 'Welcome', 'shelves')->json('props.shelves'))
        ->flatMap(fn ($shelf) => collect($shelf['books'])->pluck('id'));

    expect($ids)->not->toContain($featured['id']);
});

test('shelves resolve on an empty catalogue rather than erroring', function () {
    expect(inertiaPartial('/', 'Welcome', 'shelves')->assertOk()->json('props.shelves'))->toBe([]);
});

test('related books resolve on a book page', function () {
    $genre = Genre::factory()->create();
    $book = Book::factory()->create(['genre_id' => $genre->id]);
    Book::factory()->count(3)->create(['genre_id' => $genre->id]);

    $related = inertiaPartial(route('books.show', $book), 'Books/Show', 'related')
        ->assertOk()
        ->json('props.related');

    expect($related)->toHaveCount(3)
        ->and($related[0])->toHaveKeys(['id', 'slug', 'title', 'price_paise', 'author']);
});

test('related books never include the book being looked at', function () {
    $genre = Genre::factory()->create();
    $book = Book::factory()->create(['genre_id' => $genre->id]);
    Book::factory()->count(2)->create(['genre_id' => $genre->id]);

    $ids = collect(inertiaPartial(route('books.show', $book), 'Books/Show', 'related')->json('props.related'))
        ->pluck('id');

    expect($ids)->not->toContain($book->id);
});

test('related books skip drafts', function () {
    $genre = Genre::factory()->create();
    $book = Book::factory()->create(['genre_id' => $genre->id]);
    Book::factory()->unpublished()->create(['genre_id' => $genre->id]);

    expect(inertiaPartial(route('books.show', $book), 'Books/Show', 'related')->json('props.related'))
        ->toBe([]);
});
