<?php

use App\Models\Book;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/sample.pdf', '%PDF-1.4 full book');
    Storage::disk('local')->put('samples/chapter-one.pdf', '%PDF-1.4 first chapter');

    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->book = Book::factory()->create([
        'file_path' => 'books/sample.pdf',
        'sample_path' => 'samples/chapter-one.pdf',
        'file_format' => 'pdf',
        'page_count' => 300,
    ]);

    completeCheckout($this->owner, [$this->book->id]);
});

test('an owner can open the reader', function () {
    $this->actingAs($this->owner)
        ->get(route('reader.show', $this->book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reader/Show')
            ->where('isSample', false)
            ->where('book.title', $this->book->title)
            ->where('assetUrl', route('reader.asset', $this->book))
        );
});

test('the reader never receives a file path', function () {
    $this->actingAs($this->owner)
        ->get(route('reader.show', $this->book))
        ->assertInertia(fn ($page) => $page
            ->missing('book.file_path')
            ->missing('book.sample_path')
        );
});

test('someone who has not bought the book is offered the sample instead', function () {
    // Not a dead end: the answer to "can I read this?" is always "here is some".
    $this->actingAs($this->stranger)
        ->get(route('reader.show', $this->book))
        ->assertRedirect(route('reader.sample', $this->book));
});

test('with no sample, a non-owner is sent to the book page', function () {
    $book = Book::factory()->create(['sample_path' => null]);

    $this->actingAs($this->stranger)
        ->get(route('reader.show', $book))
        ->assertRedirect(route('books.show', $book));
});

test('a guest must sign in to read a book they own', function () {
    $this->get(route('reader.show', $this->book))->assertRedirect('/login');
});

test('the full book is streamed only to its owner', function () {
    $this->actingAs($this->owner)
        ->get(route('reader.asset', $this->book))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($this->stranger)
        ->get(route('reader.asset', $this->book))
        ->assertForbidden();

    signOut();

    $this->get(route('reader.asset', $this->book))->assertRedirect('/login');
});

test('a missing file is a 404 rather than an empty download', function () {
    Storage::disk('local')->delete('books/sample.pdf');

    $this->actingAs($this->owner)
        ->get(route('reader.asset', $this->book))
        ->assertNotFound();
});

/* ------------------------------------------------------------------ samples */

test('a sample is readable without an account', function () {
    $this->assertGuest();

    $this->get(route('reader.sample', $this->book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reader/Show')
            ->where('isSample', true)
            ->where('assetUrl', route('reader.sample.asset', $this->book))
        );

    $this->get(route('reader.sample.asset', $this->book))->assertOk();
});

test('the sample never serves the full book', function () {
    $response = $this->get(route('reader.sample.asset', $this->book));

    // Streamed from the disk rather than handed over as a file path, so that
    // object storage and the local disk take the same route through the app.
    expect($response->streamedContent())->toBe('%PDF-1.4 first chapter');
});

test('a book with no sample has no sample to read', function () {
    $book = Book::factory()->create(['sample_path' => null]);

    $this->get(route('reader.sample', $book))->assertRedirect(route('books.show', $book));
    $this->get(route('reader.sample.asset', $book))->assertNotFound();
});

test('an unpublished book does not leak through its sample', function () {
    $draft = Book::factory()->unpublished()->create(['sample_path' => 'samples/chapter-one.pdf']);

    $this->get(route('reader.sample', $draft))->assertNotFound();
    $this->get(route('reader.sample.asset', $draft))->assertNotFound();
});

/* ----------------------------------------------------------------- progress */

test('reading progress is saved and read back', function () {
    $this->actingAs($this->owner)
        ->post(route('reader.progress', $this->book), ['location' => '42', 'percent' => 37])
        ->assertNoContent();

    $this->actingAs($this->owner)
        ->get(route('reader.show', $this->book))
        ->assertInertia(fn ($page) => $page
            ->where('progress.location', '42')
            ->where('progress.percent', 37)
        );
});

test('progress updates in place rather than piling up rows', function () {
    foreach ([10, 20, 30] as $percent) {
        $this->actingAs($this->owner)
            ->post(route('reader.progress', $this->book), ['location' => (string) $percent, 'percent' => $percent]);
    }

    expect(ReadingProgress::count())->toBe(1)
        ->and(ReadingProgress::sole()->percent)->toBe(30);
});

test('progress cannot be written for a book you do not own', function () {
    $this->actingAs($this->stranger)
        ->post(route('reader.progress', $this->book), ['location' => '1', 'percent' => 5])
        ->assertForbidden();

    expect(ReadingProgress::count())->toBe(0);
});

test('an out-of-range percentage is rejected', function () {
    $this->actingAs($this->owner)
        ->post(route('reader.progress', $this->book), ['location' => '1', 'percent' => 140])
        ->assertSessionHasErrors('percent');
});

test('the library offers to continue the book in progress', function () {
    $this->actingAs($this->owner)
        ->post(route('reader.progress', $this->book), ['location' => '42', 'percent' => 43]);

    $this->actingAs($this->owner)
        ->get('/library')
        ->assertInertia(fn ($page) => $page
            ->where('continueReading.percent', 43)
            ->where('continueReading.book.id', $this->book->id)
        );
});

test('a finished book is not offered as unfinished', function () {
    $this->actingAs($this->owner)
        ->post(route('reader.progress', $this->book), ['location' => 'end', 'percent' => 100]);

    $this->actingAs($this->owner)
        ->get('/library')
        ->assertInertia(fn ($page) => $page->where('continueReading', null));
});

/* -------------------------------------------------------------- annotations */

test('an owner can bookmark and unbookmark a place', function () {
    $this->actingAs($this->owner)
        ->post(route('reader.bookmarks.store', $this->book), ['location' => '12', 'label' => 'Chapter 2']);

    expect(Bookmark::count())->toBe(1);

    // Bookmarking the same spot twice is one bookmark, not two.
    $this->actingAs($this->owner)
        ->post(route('reader.bookmarks.store', $this->book), ['location' => '12']);

    expect(Bookmark::count())->toBe(1);

    $this->actingAs($this->owner)
        ->delete(route('reader.bookmarks.destroy', [$this->book, Bookmark::sole()]));

    expect(Bookmark::count())->toBe(0);
});

test('bookmarks belong to one reader', function () {
    $this->actingAs($this->owner)
        ->post(route('reader.bookmarks.store', $this->book), ['location' => '12']);

    $bookmark = Bookmark::sole();

    // Another customer who owns the same book still cannot touch it.
    $other = User::factory()->create();
    completeCheckout($other, [$this->book->id]);

    $this->actingAs($other)
        ->delete(route('reader.bookmarks.destroy', [$this->book, $bookmark]))
        ->assertForbidden();

    expect(Bookmark::count())->toBe(1);
});

test('highlights can be created, annotated and removed', function () {
    $this->actingAs($this->owner)->post(route('reader.highlights.store', $this->book), [
        'location' => 'epubcfi(/6/14!/4/2/1:0,/1:24)',
        'text' => 'It was the hour of the unexpected guest.',
    ]);

    $highlight = Highlight::sole();
    expect($highlight->color)->toBe('yellow');

    $this->actingAs($this->owner)->patch(
        route('reader.highlights.update', [$this->book, $highlight]),
        ['note' => 'Opening line'],
    );

    expect($highlight->fresh()->note)->toBe('Opening line');

    $this->actingAs($this->owner)->delete(route('reader.highlights.destroy', [$this->book, $highlight]));

    expect(Highlight::count())->toBe(0);
});

test('annotations cannot be made on a book you do not own', function () {
    $this->actingAs($this->stranger)
        ->post(route('reader.bookmarks.store', $this->book), ['location' => '1'])
        ->assertForbidden();

    $this->actingAs($this->stranger)
        ->post(route('reader.highlights.store', $this->book), ['location' => '1', 'text' => 'x'])
        ->assertForbidden();

    expect(Bookmark::count())->toBe(0)->and(Highlight::count())->toBe(0);
});

test('an annotation cannot be reached through the wrong book', function () {
    $this->actingAs($this->owner)
        ->post(route('reader.bookmarks.store', $this->book), ['location' => '12']);

    $otherBook = Book::factory()->create();
    completeCheckout($this->owner, [$otherBook->id]);

    // Owning both books is not enough: the bookmark is not in this one.
    $this->actingAs($this->owner)
        ->delete(route('reader.bookmarks.destroy', [$otherBook, Bookmark::sole()]))
        ->assertForbidden();

    expect(Bookmark::count())->toBe(1);
});
