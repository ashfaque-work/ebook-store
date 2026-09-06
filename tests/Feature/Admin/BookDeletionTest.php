<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Regression cover for audit bug A1.
 *
 * `destroy()` used to erase the cover and the ebook from storage and only then
 * call delete() — which the restrictOnDelete constraint on order_items rejects.
 * The row survived, the files did not, and every customer who had paid for the
 * book silently lost their copy.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    Storage::disk('local')->put('books/sample.pdf', 'the-paid-asset');
    Storage::disk('public')->put('covers/sample.jpg', 'the-cover');

    $this->admin = User::factory()->admin()->create();
    $this->book = Book::factory()->create([
        'file_path' => 'books/sample.pdf',
        'cover_image_path' => 'covers/sample.jpg',
    ]);
});

test('a purchased book cannot be deleted and its files are left untouched', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer)->withSession(['cart' => [$this->book->id]])->post('/checkout');

    expect($customer->hasPurchased($this->book))->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('admin.books.destroy', $this->book))
        ->assertRedirect();

    // The row must survive…
    expect(Book::find($this->book->id))->not->toBeNull();

    // …and so must the buyer's file. This is the assertion that matters: the
    // original bug failed the delete *after* destroying the asset.
    expect(Storage::disk('local')->exists('books/sample.pdf'))->toBeTrue()
        ->and(Storage::disk('public')->exists('covers/sample.jpg'))->toBeTrue();
});

test('a purchased book can still be unpublished', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer)->withSession(['cart' => [$this->book->id]])->post('/checkout');

    $this->actingAs($this->admin)->put(route('admin.books.update', $this->book), [
        'title' => $this->book->title,
        'author_id' => $this->book->author_id,
        'genre_id' => $this->book->genre_id,
        'description' => $this->book->description,
        'price' => $this->book->price,
        'is_published' => false,
    ])->assertRedirect(route('admin.books.index'));

    expect($this->book->fresh()->is_published)->toBeFalse();

    // Retiring a book must not take it away from the people who bought it.
    expect($customer->hasPurchased($this->book))->toBeTrue();
});

test('an unsold book is deleted along with its files', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.books.destroy', $this->book))
        ->assertRedirect(route('admin.books.index'));

    expect(Book::find($this->book->id))->toBeNull()
        ->and(Storage::disk('local')->exists('books/sample.pdf'))->toBeFalse()
        ->and(Storage::disk('public')->exists('covers/sample.jpg'))->toBeFalse();
});
