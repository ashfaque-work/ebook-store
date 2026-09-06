<?php

use App\Models\Book;
use App\Models\Genre;

test('an unfiltered visit gets shelves, not a page of results', function () {
    Book::factory()->count(15)->create();

    // Browsing and searching answer different questions, so they are different
    // views: one opens a book, the other lists matches.
    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('mode', 'shelves')
            ->has('featured')
            ->has('newest', 10)
            ->missing('books')
        );
});

test('searching paginates the results', function () {
    Book::factory()->count(30)->create(['title' => 'Monsoon Diaries']);

    $this->get('/?search=Monsoon')
        ->assertInertia(fn ($page) => $page
            ->where('mode', 'results')
            ->has('books.data', 24)        // a results page caps at 24
            ->has('books.links')
        );
});

test('the catalogue can be searched by title', function () {
    Book::factory()->create(['title' => 'The Pragmatic Programmer']);
    Book::factory()->create(['title' => 'Clean Code']);

    $this->get('/?search=Pragmatic')
        ->assertInertia(fn ($page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.title', 'The Pragmatic Programmer')
        );
});

test('the catalogue can be filtered by genre', function () {
    $fiction = Genre::factory()->create(['slug' => 'fiction']);
    $tech = Genre::factory()->create(['slug' => 'tech']);
    Book::factory()->create(['genre_id' => $fiction->id]);
    Book::factory()->count(2)->create(['genre_id' => $tech->id]);

    $this->get('/?genre=tech')
        ->assertInertia(fn ($page) => $page->has('books.data', 2));
});

test('book slugs are made unique on collision', function () {
    $a = Book::factory()->create(['title' => 'Same Title', 'slug' => Book::uniqueSlug('Same Title')]);
    $b = Book::factory()->create(['title' => 'Same Title', 'slug' => Book::uniqueSlug('Same Title')]);

    expect($a->slug)->toBe('same-title')
        ->and($b->slug)->toBe('same-title-2');
});
