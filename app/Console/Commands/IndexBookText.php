<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookChunk;
use App\Support\Epub\TextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Read every book and write its text down in passages, so it can be searched.
 *
 * Rebuilt rather than updated: a book's file either changes or it does not,
 * and reconciling passages one by one would be more code than re-reading the
 * whole thing, which takes a fraction of a second.
 */
class IndexBookText extends Command
{
    protected $signature = 'store:index-text
        {--book= : Index only this book, by slug}
        {--force : Re-index books that already have passages}
        {--words=180 : Words per passage}';

    protected $description = 'Index the full text of every book for searching inside';

    public function handle(): int
    {
        $words = max(40, (int) $this->option('words'));

        $books = Book::where('file_format', 'epub')
            ->when($this->option('book'), fn ($q, $slug) => $q->where('slug', $slug))
            ->when(! $this->option('force'), fn ($q) => $q->whereDoesntHave('chunks'))
            ->get();

        if ($books->isEmpty()) {
            $this->info('Nothing to index.');

            return self::SUCCESS;
        }

        $this->info("Indexing {$books->count()} book(s).");
        $indexed = 0;
        $passages = 0;
        $failed = 0;

        foreach ($books as $book) {
            try {
                $epub = Storage::disk(Book::fileDisk())->get($book->getRawOriginal('file_path'));
            } catch (Throwable) {
                $epub = null;
            }

            $extracted = $epub === null ? [] : TextExtractor::passages($epub, $words);

            if ($extracted === []) {
                $failed++;
                $this->line("  <fg=yellow>skipped</> {$book->title}");

                continue;
            }

            DB::transaction(function () use ($book, $extracted) {
                // Inside the transaction so a book is never left half-indexed:
                // searchable with most of its text missing is worse than not
                // searchable at all, because nothing tells you it is wrong.
                BookChunk::where('book_id', $book->id)->delete();

                foreach (array_chunk($extracted, 500) as $offset => $batch) {
                    $rows = [];

                    foreach ($batch as $index => $passage) {
                        $rows[] = [
                            'book_id' => $book->id,
                            'position' => $offset * 500 + $index,
                            'section' => mb_substr($passage['section'], 0, 255),
                            'heading' => $passage['heading'],
                            'content' => $passage['content'],
                            'word_count' => min(65535, $passage['words']),
                        ];
                    }

                    BookChunk::insert($rows);
                }
            });

            $count = count($extracted);
            $passages += $count;
            $indexed++;
            $this->line(sprintf('  %5d passages  %s', $count, $book->title));
        }

        $this->newLine();
        $this->info("{$indexed} book(s), {$passages} passages.");

        if ($failed > 0) {
            $this->warn("  {$failed} could not be read.");
        }

        return self::SUCCESS;
    }
}
