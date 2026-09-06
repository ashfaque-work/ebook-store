<?php

use App\Models\Book;
use App\Models\User;

test('unpublished books are hidden from the catalogue', function () {
    $live = Book::factory()->create();
    Book::factory()->unpublished()->create();

    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->has('newest', 1)
        ->where('newest.0.id', $live->id)
    );
});

test('unpublished books are hidden from search results too', function () {
    Book::factory()->create(['title' => 'Salt and Monsoon']);
    Book::factory()->unpublished()->create(['title' => 'Salt and Silence']);

    $this->get('/?search=Salt')->assertInertia(fn ($page) => $page
        ->where('mode', 'results')
        ->has('books.data', 1)
        ->where('books.data.0.title', 'Salt and Monsoon')
    );
});

test('an unpublished book page is not reachable by the public', function () {
    $draft = Book::factory()->unpublished()->create();

    $this->get(route('books.show', $draft))->assertForbidden();
});

test('an admin can preview an unpublished book', function () {
    $draft = Book::factory()->unpublished()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('books.show', $draft))
        ->assertOk();
});

test('an unpublished book cannot be bought even if its id is in the cart', function () {
    $draft = Book::factory()->unpublished()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['cart' => [$draft->id]])
        ->post('/checkout')
        ->assertRedirect(route('library.index'));

    expect($user->hasPurchased($draft))->toBeFalse();
});

test('guests are offered a way to register', function () {
    // Audit bug A3: the nav read an unshared prop, so the link never rendered.
    $this->get('/')->assertInertia(fn ($page) => $page->where('canRegister', true));
});

test('the shared auth user exposes only the intended fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertInertia(fn ($page) => $page
        ->has('auth.user', fn ($shared) => $shared
            ->hasAll(['id', 'name', 'email', 'role', 'is_admin', 'email_verified_at'])
            ->etc()
        )
    );
});
