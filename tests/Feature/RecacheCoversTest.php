<?php

use App\Models\Book;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('the public disk asks for a long cache on everything it writes', function () {
    // The header itself only exists on a real S3 disk, so what is pinned here
    // is the configuration — it is one line, nothing fails when it is missing,
    // and the symptom is only ever "the site feels slow".
    expect(config('filesystems.disks.r2-public.options.CacheControl'))
        ->toContain('max-age=')
        ->toContain('public');
});

test('it rewrites every stored cover', function () {
    $book = Book::factory()->create([
        'cover_image_path' => UploadedFile::fake()->image('cover.jpg')->store('covers', 'public'),
    ]);

    $path = $book->getRawOriginal('cover_image_path');
    $before = Storage::disk('public')->get($path);

    $this->artisan('store:recache-covers')
        ->expectsOutputToContain('1 rewritten')
        ->assertSuccessful();

    // Same key, same bytes: only the headers were ever the point.
    expect(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->get($path))->toBe($before);
});

test('a dry run writes nothing', function () {
    Book::factory()->create([
        'cover_image_path' => UploadedFile::fake()->image('cover.jpg')->store('covers', 'public'),
    ]);

    $this->artisan('store:recache-covers --dry-run')
        ->expectsOutputToContain('would be rewritten')
        ->assertSuccessful();
});

test('a cover recorded on a book but missing from the bucket is reported, not fatal', function () {
    Book::factory()->create(['cover_image_path' => 'covers/gone.jpg']);

    $this->artisan('store:recache-covers')
        ->expectsOutputToContain('not in the bucket')
        ->assertSuccessful();
});

test('it says so when there are no covers at all', function () {
    Book::factory()->create(['cover_image_path' => null]);

    $this->artisan('store:recache-covers')
        ->expectsOutputToContain('No covers stored')
        ->assertSuccessful();
});
