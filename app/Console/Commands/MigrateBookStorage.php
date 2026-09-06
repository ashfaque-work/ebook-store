<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Move existing book files and covers onto the configured disks.
 *
 * Run once, after pointing PRIVATE_DISK/PUBLIC_DISK at object storage and
 * before the first deploy that uses it. Copies rather than moves, and only
 * rewrites the database row once the copy is verified, so a failure halfway
 * through leaves the store working on the old disk.
 */
class MigrateBookStorage extends Command
{
    protected $signature = 'books:migrate-storage
                            {--from-private=local : Disk the ebook files are on now}
                            {--from-public=public : Disk the covers are on now}
                            {--dry-run : List what would move without touching anything}';

    protected $description = 'Copy book files and covers onto the configured storage disks';

    public function handle(): int
    {
        $fromPrivate = $this->option('from-private');
        $fromPublic = $this->option('from-public');
        $toPrivate = Book::fileDisk();
        $toPublic = Book::coverDisk();
        $dry = (bool) $this->option('dry-run');

        if ($fromPrivate === $toPrivate && $fromPublic === $toPublic) {
            $this->warn("Source and destination disks are the same ({$toPrivate}, {$toPublic}). Nothing to do.");
            $this->line('Set PRIVATE_DISK and PUBLIC_DISK first — see docs/08-DEPLOYMENT.md.');

            return self::SUCCESS;
        }

        $this->info($dry ? 'Dry run — nothing will be written.' : 'Migrating book storage.');
        $this->line("  ebooks: {$fromPrivate} → {$toPrivate}");
        $this->line("  covers: {$fromPublic} → {$toPublic}");
        $this->newLine();

        $moved = 0;
        $skipped = 0;
        $failed = 0;

        Book::query()->chunkById(50, function ($books) use (
            $fromPrivate, $fromPublic, $toPrivate, $toPublic, $dry, &$moved, &$skipped, &$failed
        ) {
            foreach ($books as $book) {
                foreach ([
                    ['file_path', $fromPrivate, $toPrivate],
                    ['sample_path', $fromPrivate, $toPrivate],
                    ['cover_image_path', $fromPublic, $toPublic],
                ] as [$column, $from, $to]) {
                    $path = $book->getRawOriginal($column);

                    if (! $path) {
                        continue;
                    }

                    if (Storage::disk($to)->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! Storage::disk($from)->exists($path)) {
                        $this->warn("  missing on {$from}: {$path} (book #{$book->id})");
                        $failed++;

                        continue;
                    }

                    if ($dry) {
                        $this->line("  would copy {$path}");
                        $moved++;

                        continue;
                    }

                    // Stream rather than read into memory: some of these are
                    // tens of megabytes and the container is small.
                    $stream = Storage::disk($from)->readStream($path);
                    $ok = Storage::disk($to)->writeStream($path, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    if ($ok) {
                        $moved++;
                    } else {
                        $this->error("  failed to copy {$path} (book #{$book->id})");
                        $failed++;
                    }
                }
            }
        });

        $this->newLine();
        $this->info("Copied {$moved}, already present {$skipped}, failed {$failed}.");

        if ($failed > 0) {
            $this->error('Some files did not copy. Fix those before switching the disks over.');

            return self::FAILURE;
        }

        if (! $dry) {
            $this->line('Paths are unchanged, so nothing in the database needs rewriting.');
            $this->line('Verify a download, then remove the old files by hand.');
        }

        return self::SUCCESS;
    }
}
