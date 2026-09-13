<?php

use App\Models\Book;
use App\Models\BookChunk;
use App\Services\Search\SearchInsideBooks;
use Illuminate\Support\Facades\Storage;

function passage(Book $book, int $position, string $content, ?string $heading = null): BookChunk
{
    return BookChunk::create([
        'book_id' => $book->id,
        'position' => $position,
        'section' => 'text.xhtml',
        'heading' => $heading,
        'content' => $content,
        'word_count' => str_word_count($content),
    ]);
}

test('a passage is found by words inside the book', function () {
    $book = Book::factory()->create(['title' => 'Pride and Prejudice', 'is_published' => true]);
    passage($book, 0, 'IT is a truth universally acknowledged, that a single man in possession of a good fortune must be in want of a wife.', 'Chapter I.');
    passage($book, 1, 'Mr. Bennet was so odd a mixture of quick parts, sarcastic humour, reserve, and caprice.');

    $found = app(SearchInsideBooks::class)('universally acknowledged');

    expect($found['total'])->toBe(1)
        ->and($found['results'][0]['bookTitle'])->toBe('Pride and Prejudice')
        ->and($found['results'][0]['heading'])->toBe('Chapter I.');
});

test('the excerpt is a window around the match, not the start of the passage', function () {
    $book = Book::factory()->create(['is_published' => true]);
    passage($book, 0, str_repeat('filler words here. ', 40).'the peculiar phrase appears late in this passage.'.str_repeat(' more filler.', 20));

    $excerpt = app(SearchInsideBooks::class)('peculiar phrase')['results'][0]['excerpt'];

    // Showing the first two hundred words of the passage would leave the words
    // someone searched for off the screen entirely.
    expect($excerpt)->toContain('peculiar phrase');
});

test('an unpublished book is not searchable', function () {
    $book = Book::factory()->create(['is_published' => false]);
    passage($book, 0, 'a secret manuscript nobody should find yet');

    expect(app(SearchInsideBooks::class)('secret manuscript')['total'])->toBe(0);
});

test('search can be narrowed to one book', function () {
    $austen = Book::factory()->create(['title' => 'Emma', 'is_published' => true]);
    $dickens = Book::factory()->create(['title' => 'Bleak House', 'is_published' => true]);

    passage($austen, 0, 'the handsome clever and rich young woman');
    passage($dickens, 0, 'the handsome clever fog everywhere over the city');

    $everywhere = app(SearchInsideBooks::class)('handsome clever');
    $justEmma = app(SearchInsideBooks::class)('handsome clever', $austen);

    expect($everywhere['total'])->toBe(2)
        ->and($justEmma['total'])->toBe(1)
        ->and($justEmma['results'][0]['bookTitle'])->toBe('Emma');
});

test('a one-letter query returns nothing rather than the whole catalogue', function () {
    $book = Book::factory()->create(['is_published' => true]);
    passage($book, 0, 'a passage about a great many things');

    // Otherwise every keystroke in the box scans the entire index.
    expect(app(SearchInsideBooks::class)('a')['total'])->toBe(0);
});

test('the search page renders results', function () {
    $book = Book::factory()->create(['title' => 'Moby Dick', 'is_published' => true]);
    passage($book, 0, 'Call me Ishmael. Some years ago, never mind how long precisely.', 'Chapter 1. Loomings.');

    $this->get('/search-inside?q='.urlencode('Ishmael'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SearchInside')
            ->where('query', 'Ishmael')
            ->has('results', 1)
            ->where('results.0.bookTitle', 'Moby Dick')
        );
});

test('the search page works with no query at all', function () {
    $this->get('/search-inside')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('query', '')->has('results', 0));
});

test('the search page is open to guests', function () {
    // The best argument the shop makes is being able to find the passage.
    // Hiding it behind a login hides the argument.
    $this->get('/search-inside?q=anything')->assertOk();
});

test('an absurdly long query is refused rather than run', function () {
    $this->get('/search-inside?q='.str_repeat('a', 500))->assertSessionHasErrors('q');
});

test('indexing writes the book text as passages', function () {
    Storage::fake('local');

    $book = Book::factory()->create(['file_format' => 'epub', 'file_path' => 'books/one.epub']);
    Storage::disk(Book::fileDisk())->put('books/one.epub', frontLoadedEpub(3, 500));

    $this->artisan('store:index-text')->assertSuccessful();

    expect($book->chunks()->count())->toBeGreaterThan(0)
        ->and($book->chunks()->where('heading', 'Chapter I.')->exists())->toBeTrue();
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('re-indexing replaces passages instead of duplicating them', function () {
    Storage::fake('local');

    $book = Book::factory()->create(['file_format' => 'epub', 'file_path' => 'books/one.epub']);
    Storage::disk(Book::fileDisk())->put('books/one.epub', frontLoadedEpub(3, 500));

    $this->artisan('store:index-text');
    $first = $book->chunks()->count();

    $this->artisan('store:index-text', ['--force' => true]);

    expect($book->chunks()->count())->toBe($first);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('deleting a book takes its text with it', function () {
    $book = Book::factory()->create();
    passage($book, 0, 'text that should not outlive its book');

    $book->delete();

    expect(BookChunk::count())->toBe(0);
});

test('the licence Gutenberg wraps every book in is not indexed', function () {
    Storage::fake('local');

    $book = Book::factory()->create(['file_format' => 'epub', 'file_path' => 'books/one.epub']);
    Storage::disk(Book::fileDisk())->put('books/one.epub', frontLoadedEpub(3, 500));

    $this->artisan('store:index-text');

    // It is identical across the catalogue, so indexing it would make a search
    // for a common legal word return every book at once.
    expect($book->chunks()->where('content', 'like', '%PROJECT GUTENBERG%')->count())->toBe(0);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');
