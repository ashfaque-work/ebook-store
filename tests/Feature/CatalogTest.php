<?php

use App\Models\Book;
use App\Models\Genre;

test('the catalogue paginates books', function () {
    Book::factory()->count(15)->create();

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('books.data', 12)        // first page caps at 12
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
