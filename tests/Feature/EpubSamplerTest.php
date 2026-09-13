<?php

use App\Models\Book;
use App\Support\EpubSampler;
use Illuminate\Support\Facades\Storage;

/**
 * Build an EPUB shaped like the ones this store actually holds: a single
 * document carrying licence boilerplate, a table of contents, a long preface,
 * and only then the first chapter.
 */
function frontLoadedEpub(int $chapters = 6, int $wordsPerChapter = 4000): string
{
    $preface = trim(str_repeat('preface ', 5000));
    $contents = trim(str_repeat('Chapter: I., II., III., IV., V., VI. ', 12));

    $body = '<header id="pg-header"><p>The Project Gutenberg eBook. '
        .'*** START OF THE PROJECT GUTENBERG EBOOK TEST ***</p></header>'
        ."<div class=\"blk\">{$contents}</div>"
        ."<h2>PREFACE.</h2><p>{$preface}</p>";

    for ($i = 1; $i <= $chapters; $i++) {
        $roman = ['I', 'II', 'III', 'IV', 'V', 'VI'][$i - 1] ?? (string) $i;
        // Letters only: str_word_count does not count digits as word
        // characters, so "chapter1word" would score two and quietly double
        // every measurement in these tests.
        $marker = ['chapterone', 'chaptertwo', 'chapterthree', 'chapterfour', 'chapterfive', 'chaptersix'][$i - 1] ?? 'chapterlater';
        $prose = trim(str_repeat("{$marker} ", $wordsPerChapter));
        $body .= "<h2>Chapter {$roman}.</h2><p>{$prose}</p>";
    }

    $path = tempnam(sys_get_temp_dir(), 'epub').'.epub';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('META-INF/container.xml',
        '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">'
        .'<rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
    $zip->addFromString('OEBPS/text.xhtml',
        '<?xml version="1.0" encoding="utf-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>Book</title>'
        .'</head><body>'.$body.'</body></html>');
    $zip->addFromString('OEBPS/style.css', 'body { margin: 1em; }');
    $zip->addFromString('OEBPS/content.opf',
        '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="id">'
        .'<metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Book</dc:title><dc:identifier id="id">x</dc:identifier></metadata>'
        .'<manifest><item id="text" href="text.xhtml" media-type="application/xhtml+xml"/>'
        .'<item id="css" href="style.css" media-type="text/css"/>'
        .'<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/></manifest>'
        .'<spine toc="ncx"><itemref idref="text"/></spine></package>');
    $zip->addFromString('OEBPS/toc.ncx', '<?xml version="1.0"?><ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"/>');
    $zip->close();

    $bytes = file_get_contents($path);
    @unlink($path);

    return $bytes;
}

function sampleText(string $epub): string
{
    $path = tempnam(sys_get_temp_dir(), 'out').'.epub';
    file_put_contents($path, $epub);

    $zip = new ZipArchive;
    $zip->open($path);
    $text = '';

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (preg_match('/\.x?html?$/i', $name)) {
            $text .= (string) $zip->getFromName($name);
        }
    }

    $zip->close();
    @unlink($path);

    return $text;
}

uses()->group('epub');

test('the sample starts at the first chapter, not the front matter', function () {
    $text = sampleText(EpubSampler::make(frontLoadedEpub()));

    // This is the whole point, and it took three attempts to get right: a
    // budget measured from the top of the file produces a sample of the
    // licence, the contents and a five-thousand-word preface.
    expect($text)->toContain('chapterone')
        ->and($text)->not->toContain('preface')
        ->and($text)->not->toContain('START OF THE PROJECT GUTENBERG');
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('the contents list is not mistaken for a chapter', function () {
    $text = sampleText(EpubSampler::make(frontLoadedEpub()));

    // It says "Chapter" more often than any chapter does.
    expect($text)->not->toContain('II., III., IV.');
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('the rest of the book is not in the file', function () {
    $text = sampleText(EpubSampler::make(frontLoadedEpub(6, 4000), 5000));

    // A sample that carries the whole book inside it, reachable by anyone who
    // unzips it, is not a sample.
    expect($text)->toContain('chapterone')
        ->and($text)->not->toContain('chapterfive')
        ->and($text)->not->toContain('chaptersix');
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('the sample honours its word budget', function () {
    // Chapters of a thousand words, so the overshoot is bounded by one of
    // them: the cut is made between blocks, never inside a paragraph.
    $text = sampleText(EpubSampler::make(frontLoadedEpub(8, 1000), 3000));
    $words = str_word_count(strip_tags($text));

    expect($words)->toBeGreaterThan(2000)->toBeLessThan(3000 + 1200);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('the sample is a real epub a reader can open', function () {
    $path = tempnam(sys_get_temp_dir(), 'out').'.epub';
    file_put_contents($path, EpubSampler::make(frontLoadedEpub()));

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    // The spec wants mimetype first and stored uncompressed.
    expect($zip->getNameIndex(0))->toBe('mimetype')
        ->and($zip->statIndex(0)['comp_method'])->toBe(ZipArchive::CM_STORE)
        ->and($zip->getFromName('mimetype'))->toBe('application/epub+zip')
        ->and($zip->locateName('META-INF/container.xml'))->not->toBeFalse()
        ->and($zip->locateName('OEBPS/content.opf'))->not->toBeFalse()
        // Stylesheets come along, so the sample reads like the book.
        ->and($zip->locateName('OEBPS/style.css'))->not->toBeFalse();

    $opf = $zip->getFromName('OEBPS/content.opf');

    // The NCX was dropped with the rest of the manifest, so the spine must not
    // still point at it — a dangling reference is a broken book.
    expect($opf)->not->toContain('toc.ncx')
        ->and($opf)->toContain('sample-nav.xhtml');

    $zip->close();
    @unlink($path);
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('junk in gives nothing back rather than a broken file', function () {
    expect(EpubSampler::make('this is not an epub'))->toBeNull()
        ->and(EpubSampler::make(''))->toBeNull();
});

test('the command gives every epub a sample', function () {
    Storage::fake('local');

    $book = Book::factory()->create(['file_format' => 'epub', 'file_path' => 'books/one.epub', 'sample_path' => null]);
    Storage::disk(Book::fileDisk())->put('books/one.epub', frontLoadedEpub());

    $this->artisan('store:make-samples')->assertSuccessful();

    $book->refresh();
    expect($book->getRawOriginal('sample_path'))->not->toBeNull();
    Storage::disk(Book::fileDisk())->assertExists($book->getRawOriginal('sample_path'));
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('the command leaves existing samples alone unless forced', function () {
    Storage::fake('local');

    $book = Book::factory()->create([
        'file_format' => 'epub',
        'file_path' => 'books/one.epub',
        'sample_path' => 'samples/hand-made.epub',
    ]);
    Storage::disk(Book::fileDisk())->put('books/one.epub', frontLoadedEpub());

    $this->artisan('store:make-samples')->assertSuccessful();

    // A sample uploaded by hand through the admin form should not be replaced
    // by a generated one.
    expect($book->fresh()->getRawOriginal('sample_path'))->toBe('samples/hand-made.epub');

    $this->artisan('store:make-samples', ['--force' => true])->assertSuccessful();

    expect($book->fresh()->getRawOriginal('sample_path'))->not->toBe('samples/hand-made.epub');
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');

test('a book whose file is missing is skipped, not emptied', function () {
    Storage::fake('local');

    $book = Book::factory()->create(['file_format' => 'epub', 'file_path' => 'books/gone.epub', 'sample_path' => null]);

    $this->artisan('store:make-samples')->assertSuccessful();

    expect($book->fresh()->getRawOriginal('sample_path'))->toBeNull();
})->skip(fn () => ! class_exists(ZipArchive::class), 'ZipArchive is not available');
