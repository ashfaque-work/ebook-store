<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Support\EpubStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Work out how long the EPUBs already in the catalogue are.
 *
 * The importer does this on the way in. This is for everything that arrived
 * before it did — without a page count the reader cannot tell anyone how much
 * of the book is left, and simply shows nothing.
 */
class BackfillPageCounts extends Command
{
    protected $signature = 'store:backfill-pages {--all : Recalculate books that already have a count}';

    protected $description = 'Estimate page counts for EPUBs that have none';

    public function handle(): int
    {
        $books = Book::where('file_format', 'epub')
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('page_count'))
            ->get();

        if ($books->isEmpty()) {
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Measuring {$books->count()} book(s).");
        $done = 0;
        $failed = 0;

        foreach ($books as $book) {
            try {
                $bytes = Storage::disk(Book::fileDisk())->get($book->getRawOriginal('file_path'));
            } catch (Throwable) {
                $bytes = null;
            }

            $pages = $bytes === null ? null : EpubStats::pageCount($bytes);

            if ($pages === null) {
                $failed++;

                continue;
            }

            $book->forceFill(['page_count' => $pages])->save();
            $done++;
            $this->line(sprintf('  %4d pages  %s', $pages, $book->title));
        }

        $this->newLine();
        $this->info("{$done} measured.");

        if ($failed > 0) {
            $this->warn("  {$failed} could not be read and were left without a count.");
        }

        return self::SUCCESS;
    }
}
