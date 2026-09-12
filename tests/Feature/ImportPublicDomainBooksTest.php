<?php

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function gutendexEntry(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'title' => "Book Number {$id}",
        'authors' => [['name' => 'Austen, Jane']],
        'summaries' => ["A summary of book number {$id}."],
        'subjects' => ['Courtship -- Fiction'],
        'bookshelves' => ['Category: British Literature'],
        'formats' => [
            'application/epub+zip' => "https://example.test/{$id}.epub",
            'image/jpeg' => "https://example.test/{$id}.jpg",
        ],
    ], $overrides);
}

function fakeCatalogue(array $entries, ?string $next = null): void
{
    Http::fake([
        'https://gutendex.com/*' => Http::response(['results' => $entries, 'next' => $next]),
        'https://example.test/*.epub' => Http::response('EPUB-BYTES'),
        'https://example.test/*.jpg' => Http::response('JPEG-BYTES'),
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('books arrive with their files, covers and blurbs', function () {
    fakeCatalogue([gutendexEntry(1)]);

    $this->artisan('store:import-books', ['--count' => 1])->assertSuccessful();

    $book = Book::sole();

    expect($book->title)->toBe('Book Number 1')
        ->and($book->description)->toBe('A summary of book number 1.')
        ->and($book->file_format)->toBe('epub')
        ->and($book->is_published)->toBeTrue();

    Storage::disk(Book::fileDisk())->assertExists($book->file_path);
    // The attribute reads back as a URL; the stored value is the object key.
    Storage::disk(Book::coverDisk())->assertExists($book->getRawOriginal('cover_image_path'));
});

test('the file size recorded is the file actually stored', function () {
    fakeCatalogue([gutendexEntry(1)]);

    $this->artisan('store:import-books', ['--count' => 1]);

    // Downloads get truncated. A size that disagrees with the bytes on disk is
    // how a half-fetched book reaches a customer looking perfectly normal.
    $book = Book::sole();
    expect($book->file_size)->toBe(Storage::disk(Book::fileDisk())->size($book->file_path));
});

test('everything is free unless pricing was asked for', function () {
    fakeCatalogue([gutendexEntry(1), gutendexEntry(5), gutendexEntry(9)]);

    $this->artisan('store:import-books', ['--count' => 3]);

    // Gutenberg's licence asks 20% of gross profits on anything sold carrying
    // its trademark. Free is the default for that reason, not by accident.
    expect(Book::pluck('price_paise')->all())->each->toBe(0);
});

test('--paid gives the shelves a spread of prices, and keeps some free', function () {
    $entries = collect(range(1, 10))->map(fn ($i) => gutendexEntry($i))->all();
    fakeCatalogue($entries);

    $this->artisan('store:import-books', ['--count' => 10, '--paid' => true])
        ->expectsConfirmation('Price part of this catalogue anyway?', 'yes')
        ->assertSuccessful();

    $prices = Book::pluck('price_paise');

    expect($prices)->toHaveCount(10)
        ->and($prices->filter(fn ($p) => $p === 0))->not->toBeEmpty()
        ->and($prices->filter(fn ($p) => $p > 0))->not->toBeEmpty()
        ->and($prices->unique()->count())->toBeGreaterThan(2);
});

test('declining the licence warning imports nothing', function () {
    fakeCatalogue([gutendexEntry(1)]);

    $this->artisan('store:import-books', ['--count' => 1, '--paid' => true])
        ->expectsConfirmation('Price part of this catalogue anyway?', 'no')
        ->assertFailed();

    expect(Book::count())->toBe(0);
});

test('price follows the source id, so a re-import cannot reshuffle the shelves', function () {
    // Two books, two buckets. Derived from the Gutenberg id rather than from
    // random(), so running the import again never reprices a book somebody has
    // already seen listed at something else.
    fakeCatalogue([gutendexEntry(2), gutendexEntry(7)]);

    $this->artisan('store:import-books', ['--count' => 2, '--paid' => true])
        ->expectsConfirmation('Price part of this catalogue anyway?', 'yes')
        ->assertSuccessful();

    $prices = Book::orderBy('title')->pluck('price_paise', 'title');

    expect($prices['Book Number 2'])->toBe(0)
        ->and($prices['Book Number 7'])->toBe(9900);
});

test('a book already in the catalogue is not imported twice', function () {
    fakeCatalogue([gutendexEntry(1)]);

    $this->artisan('store:import-books', ['--count' => 1]);
    $this->artisan('store:import-books', ['--count' => 1]);

    expect(Book::count())->toBe(1);
});

test('the author is turned round into reading order', function () {
    fakeCatalogue([gutendexEntry(1)]);

    $this->artisan('store:import-books', ['--count' => 1]);

    // Gutenberg files them as "Austen, Jane"; the cover says otherwise.
    expect(Book::sole()->author->name)->toBe('Jane Austen');
});

test('shelf labels become genres, without the Category prefix', function () {
    fakeCatalogue([gutendexEntry(1)]);

    $this->artisan('store:import-books', ['--count' => 1]);

    expect(Genre::sole()->name)->toBe('British Literature');
});

test('a book with no epub is skipped rather than half-created', function () {
    fakeCatalogue([gutendexEntry(1, ['formats' => ['text/html' => 'https://example.test/1.html']])]);

    $this->artisan('store:import-books', ['--count' => 1])->assertSuccessful();

    expect(Book::count())->toBe(0);
});

test('a cover that fails to download still leaves a usable book', function () {
    Http::fake([
        'https://gutendex.com/*' => Http::response(['results' => [gutendexEntry(1)], 'next' => null]),
        'https://example.test/*.epub' => Http::response('EPUB-BYTES'),
        'https://example.test/*.jpg' => Http::response('', 404),
    ]);

    $this->artisan('store:import-books', ['--count' => 1]);

    $book = Book::sole();
    expect($book->getRawOriginal('cover_image_path'))->toBeNull();
    Storage::disk(Book::fileDisk())->assertExists($book->file_path);
});

test('a dry run writes nothing at all', function () {
    fakeCatalogue([gutendexEntry(1)]);

    $this->artisan('store:import-books', ['--count' => 1, '--dry-run' => true])
        ->assertSuccessful();

    expect(Book::count())->toBe(0);
    Storage::disk(Book::fileDisk())->assertDirectoryEmpty('/');
});

test('it follows pagination to reach the count asked for', function () {
    Http::fake([
        'https://gutendex.com/books?*page=2*' => Http::response([
            'results' => [gutendexEntry(2)],
            'next' => null,
        ]),
        'https://gutendex.com/*' => Http::response([
            'results' => [gutendexEntry(1)],
            'next' => 'https://gutendex.com/books?page=2',
        ]),
        'https://example.test/*' => Http::response('BYTES'),
    ]);

    $this->artisan('store:import-books', ['--count' => 2]);

    expect(Book::count())->toBe(2);
});

test('an unreachable catalogue fails loudly instead of importing nothing quietly', function () {
    Http::fake(['https://gutendex.com/*' => Http::response('', 503)]);

    $this->artisan('store:import-books', ['--count' => 5])->assertFailed();
});
