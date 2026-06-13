<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('a user who has not purchased a book cannot download it', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)
        ->get(route('library.download', $book))
        ->assertForbidden();
});

test('a user who purchased a book can download the file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/sample.pdf', 'fake-ebook-bytes');

    $user = User::factory()->create();
    $book = Book::factory()->create(['file_path' => 'books/sample.pdf']);

    // Purchase via the checkout flow.
    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    $this->actingAs($user)
        ->get(route('library.download', $book))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('a guest cannot download a file', function () {
    $book = Book::factory()->create();

    $this->get(route('library.download', $book))->assertRedirect('/login');
});

test('the library lists only purchased books', function () {
    $user = User::factory()->create();
    $owned = Book::factory()->create();
    $notOwned = Book::factory()->create();

    $this->actingAs($user)->withSession(['cart' => [$owned->id]])->post('/checkout');

    $this->actingAs($user)
        ->get('/library')
        ->assertInertia(fn ($page) => $page
            ->component('Library/Index')
            ->has('books', 1)
            ->where('books.0.id', $owned->id)
        );
});
