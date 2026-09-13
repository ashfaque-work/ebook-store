<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;

beforeEach(function () {
    actingAsAdmin();
});

test('books can be found by title', function () {
    Book::factory()->create(['title' => 'Moby Dick']);
    Book::factory()->create(['title' => 'Jane Eyre']);

    $this->get('/admin/books?search=Moby')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Books/Index')
            ->has('books.data', 1)
            ->where('books.data.0.title', 'Moby Dick')
        );
});

test('books can be found by their author', function () {
    $melville = Author::factory()->create(['name' => 'Herman Melville']);
    Book::factory()->create(['author_id' => $melville->id, 'title' => 'A Whale of a Time']);
    Book::factory()->create(['title' => 'Something Else']);

    // An admin far more often remembers who wrote it than the exact title.
    $this->get('/admin/books?search=Melville')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.title', 'A Whale of a Time')
        );
});

test('the search is case-insensitive', function () {
    Book::factory()->create(['title' => 'Moby Dick']);

    // Postgres LIKE is case-sensitive; the store runs on Postgres.
    $this->get('/admin/books?search=moby')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('books.data', 1));
});

test('drafts and published books can be separated', function () {
    Book::factory()->count(2)->create(['is_published' => true]);
    Book::factory()->create(['is_published' => false]);

    $this->get('/admin/books?status=draft')
        ->assertInertia(fn ($page) => $page->has('books.data', 1));

    $this->get('/admin/books?status=published')
        ->assertInertia(fn ($page) => $page->has('books.data', 2));
});

test('free and paid books can be separated', function () {
    Book::factory()->free()->count(2)->create();
    Book::factory()->create(['price_paise' => 19900]);

    $this->get('/admin/books?status=free')
        ->assertInertia(fn ($page) => $page->has('books.data', 2));

    $this->get('/admin/books?status=paid')
        ->assertInertia(fn ($page) => $page->has('books.data', 1));
});

test('an unknown status narrows nothing instead of erroring', function () {
    Book::factory()->count(3)->create();

    // It arrives from a query string, so it is not to be trusted.
    $this->get('/admin/books?status=wat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('books.data', 3));
});

test('books can be filtered by genre', function () {
    $poetry = Genre::factory()->create(['name' => 'Poetry', 'slug' => 'poetry']);
    Book::factory()->create(['genre_id' => $poetry->id]);
    Book::factory()->count(2)->create();

    $this->get('/admin/books?genre=poetry')
        ->assertInertia(fn ($page) => $page->has('books.data', 1));
});

test('filters survive pagination', function () {
    Book::factory()->count(20)->create(['is_published' => false]);
    Book::factory()->count(5)->create(['is_published' => true]);

    // Losing the filter on page two is the classic version of this bug.
    $this->get('/admin/books?status=draft&page=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('books.data', 5)
            ->where('filters.status', 'draft')
        );
});

test('the list is admin-only', function () {
    $this->actingAs(User::factory()->create())->get('/admin/books')->assertForbidden();
});
