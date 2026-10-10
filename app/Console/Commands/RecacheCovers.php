<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Put the cache headers back on covers that were uploaded without them.
 *
 * The disk sets `CacheControl` on everything it writes now. Objects already
 * in the bucket keep whatever headers they were stored with, which is none,
 * so the shelves kept refetching them. Object storage has no way to change a
 * header in place; the only move is to write the object again.
 *
 * Idempotent, and safe to interrupt — a cover is read and written back
 * unchanged, under the same key.
 */
class RecacheCovers extends Command
{
    protected $signature = 'store:recache-covers
        {--dry-run : List the covers that would be rewritten}';

    protected $description = 'Rewrite stored covers so they carry cache headers';

    public function handle(): int
    {
        $disk = Storage::disk(Book::coverDisk());

        $covers = Book::query()
            ->whereNotNull('cover_image_path')
            ->orderBy('id')
            ->get()
            ->map(fn (Book $book) => $book->getRawOriginal('cover_image_path'))
            ->filter()
            ->unique()
            ->values();

        if ($covers->isEmpty()) {
            $this->info('No covers stored.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $covers->each(fn (string $path) => $this->line("  {$path}"));
            $this->comment($covers->count().' cover(s) would be rewritten. Drop --dry-run to do it.');

            return self::SUCCESS;
        }

        $this->info("Rewriting {$covers->count()} cover(s).");

        $bar = $this->output->createProgressBar($covers->count());
        $bar->start();

        $done = 0;
        $missing = 0;
        $failed = 0;

        foreach ($covers as $path) {
            try {
                if (! $disk->exists($path)) {
                    $missing++;
                    $bar->advance();

                    continue;
                }

                $disk->put($path, $disk->get($path), 'public');
                $done++;
            } catch (Throwable $e) {
                // One unreadable cover is not a reason to abandon the rest.
                $failed++;
                $this->newLine();
                $this->warn("  {$path}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("{$done} rewritten.");

        if ($missing > 0) {
            $this->warn("  {$missing} recorded on a book but not in the bucket.");
        }

        if ($failed > 0) {
            $this->warn("  {$failed} could not be rewritten.");
        }

        return self::SUCCESS;
    }
}
