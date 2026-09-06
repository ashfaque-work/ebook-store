<?php

use App\Models\Book;
use App\Models\DownloadLog;
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
    completeCheckout($user, [$book->id]);

    $this->actingAs($user)
        ->get(route('library.download', $book))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('a guest cannot download a file', function () {
    $book = Book::factory()->create();

    $this->get(route('library.download', $book))->assertRedirect('/login');
});

test('every delivered download is logged', function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/sample.pdf', 'fake-ebook-bytes');

    $user = User::factory()->create();
    $book = Book::factory()->create(['file_path' => 'books/sample.pdf']);

    completeCheckout($user, [$book->id]);
    $this->actingAs($user)->get(route('library.download', $book))->assertOk();

    expect(DownloadLog::where('user_id', $user->id)->where('book_id', $book->id)->count())->toBe(1);
});

test('a refused download is not logged', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)->get(route('library.download', $book))->assertForbidden();

    expect(DownloadLog::count())->toBe(0);
});

test('downloads are rate limited', function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/sample.pdf', 'fake-ebook-bytes');

    $user = User::factory()->create();
    $book = Book::factory()->create(['file_path' => 'books/sample.pdf']);

    completeCheckout($user, [$book->id]);

    // The route allows 20 a minute; the 21st must be turned away.
    foreach (range(1, 20) as $ignored) {
        $this->actingAs($user)->get(route('library.download', $book))->assertOk();
    }

    $this->actingAs($user)
        ->get(route('library.download', $book))
        ->assertStatus(429);
});

test('the library lists only purchased books', function () {
    $user = User::factory()->create();
    $owned = Book::factory()->create();
    Book::factory()->create();

    completeCheckout($user, [$owned->id]);

    $this->actingAs($user)
        ->get('/library')
        ->assertInertia(fn ($page) => $page
            ->component('Library/Index')
            ->has('books.data', 1)
            ->where('books.data.0.id', $owned->id)
        );
});

test('the ebook file path is never serialised to the frontend', function () {
    $book = Book::factory()->create(['file_path' => 'books/secret.pdf']);

    expect($book->toArray())->not->toHaveKey('file_path')
        ->and($book->toArray())->not->toHaveKey('sample_path');
});
