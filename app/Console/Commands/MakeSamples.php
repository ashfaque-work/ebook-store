<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Support\EpubSampler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cut a readable opening out of every EPUB that has none.
 *
 * The storefront is built on this: the hero offers the first chapter, the book
 * page offers it again, and the reader has a panel that appears when a sample
 * runs out and asks for the sale. None of it does anything without a sample
 * file, and the whole catalogue arrived without one.
 */
class MakeSamples extends Command
{
    protected $signature = 'store:make-samples
        {--force : Replace samples that already exist}
        {--words=6000 : How much of the book the sample should hold}
        {--limit=0 : Stop after this many books}';

    protected $description = 'Generate reading samples for EPUBs that have none';

    public function handle(): int
    {
        $words = max(500, (int) $this->option('words'));
        $limit = max(0, (int) $this->option('limit'));

        $books = Book::where('file_format', 'epub')
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('sample_path'))
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get();

        if ($books->isEmpty()) {
            $this->info('Every EPUB already has a sample.');

            return self::SUCCESS;
        }

        $this->info("Cutting samples from {$books->count()} book(s).");
        $made = 0;
        $failed = 0;

        foreach ($books as $book) {
            try {
                $epub = Storage::disk(Book::fileDisk())->get($book->getRawOriginal('file_path'));
            } catch (Throwable) {
                $epub = null;
            }

            $sample = $epub === null ? null : EpubSampler::make($epub, $words);

            if ($sample === null) {
                $failed++;
                $this->line("  <fg=yellow>skipped</> {$book->title}");

                continue;
            }

            $previous = $book->getRawOriginal('sample_path');
            $path = 'samples/'.Str::random(40).'.epub';

            Storage::disk(Book::fileDisk())->put($path, $sample);
            $book->forceFill(['sample_path' => $path])->save();

            // Only once the new one is safely in place and pointed at.
            if ($previous && $previous !== $path) {
                Storage::disk(Book::fileDisk())->delete($previous);
            }

            $made++;
            $this->line(sprintf('  %6.1f KB  %s', strlen($sample) / 1024, $book->title));
        }

        $this->newLine();
        $this->info("{$made} sample(s) ready.");

        if ($failed > 0) {
            $this->warn("  {$failed} could not be read and were left without one.");
        }

        return self::SUCCESS;
    }
}
