<?php

use App\Models\Book;
use App\Support\EpubStats;
use Illuminate\Support\Facades\Storage;

/**
 * Build a minimal EPUB: a zip with a set number of chapter documents, each
 * holding a known number of words.
 */
function fakeEpub(int $chapters = 10, int $wordsPerChapter = 3000): string
{
    $path = tempnam(sys_get_temp_dir(), 'test').'.epub';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('META-INF/container.xml', '<container/>');

    $body = trim(str_repeat('word ', $wordsPerChapter));

    for ($i = 1; $i <= $chapters; $i++) {
        $zip->addFromString("OEBPS/chapter{$i}.xhtml", "<html><body><p>{$body}</p></body></html>");
    }

    $zip->close();
    $bytes = file_get_contents($path);
    @unlink($path);

    return $bytes;
}

test('a page count is estimated from the words in the book', function () {
    // 10 chapters x 3000 words = 30,000 words, at 300 to a page.
    expect(EpubStats::pageCount(fakeEpub(10, 3000)))->toBe(100);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('a longer book gets a larger count', function () {
    $short = EpubStats::pageCount(fakeEpub(4, 1000));
    $long = EpubStats::pageCount(fakeEpub(40, 1000));

    expect($long)->toBeGreaterThan($short);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('a book too long to read whole is still measured, by sampling', function () {
    // Past the sampling cap: the answer comes from a subset scaled back up,
    // so it should be close rather than exact.
    $pages = EpubStats::pageCount(fakeEpub(200, 3000));

    expect($pages)->toBeGreaterThan(1800)->toBeLessThan(2200);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('junk is reported as unknown rather than as a short book', function () {
    // A zero-page book would make the reader promise "less than a minute left".
    expect(EpubStats::pageCount('not a zip at all'))->toBeNull()
        ->and(EpubStats::pageCount(''))->toBeNull();
});

test('an epub with no documents in it is unknown', function () {
    $path = tempnam(sys_get_temp_dir(), 'test').'.epub';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->close();
    $bytes = file_get_contents($path);
    @unlink($path);

    expect(EpubStats::pageCount($bytes))->toBeNull();
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('the backfill fills in books that have no count', function () {
    Storage::fake('local');

    $book = Book::factory()->create([
        'file_format' => 'epub',
        'file_path' => 'books/one.epub',
        'page_count' => null,
    ]);

    Storage::disk(Book::fileDisk())->put('books/one.epub', fakeEpub(10, 3000));

    $this->artisan('store:backfill-pages')->assertSuccessful();

    expect($book->fresh()->page_count)->toBe(100);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('the backfill leaves existing counts alone', function () {
    Storage::fake('local');

    $book = Book::factory()->create([
        'file_format' => 'epub',
        'file_path' => 'books/one.epub',
        'page_count' => 42,
    ]);

    Storage::disk(Book::fileDisk())->put('books/one.epub', fakeEpub(10, 3000));

    $this->artisan('store:backfill-pages')->assertSuccessful();

    // A hand-entered count from the admin form should not be overwritten by
    // an estimate.
    expect($book->fresh()->page_count)->toBe(42);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('a missing file does not stop the run', function () {
    Storage::fake('local');

    Book::factory()->create([
        'file_format' => 'epub',
        'file_path' => 'books/gone.epub',
        'page_count' => null,
    ]);

    $this->artisan('store:backfill-pages')->assertSuccessful();
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');
