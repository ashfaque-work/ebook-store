<?php

namespace App\Console\Commands;

use App\Models\BookChunk;
use App\Services\Search\Embeddings\EmbedderFactory;
use App\Services\Search\Embeddings\EmbeddingFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Give every passage a vector, so it can be found by meaning.
 *
 * Resumable by design. Embedding a whole catalogue is thousands of passages
 * and a provider that will occasionally rate-limit or fall over; a run that
 * had to start again from the beginning each time would never finish, and
 * would be paid for twice.
 */
class EmbedBookText extends Command
{
    protected $signature = 'store:embed-text
        {--force : Re-embed passages that already have a vector}
        {--limit=0 : Stop after this many passages}';

    protected $description = 'Generate embeddings for indexed passages';

    public function handle(): int
    {
        $embedder = EmbedderFactory::make();

        if ($embedder === null) {
            $this->error('No embeddings provider is configured.');
            $this->line('Set EMBEDDINGS_DRIVER and the matching credentials; see config/search.php.');

            return self::FAILURE;
        }

        if (! $this->supportsVectors()) {
            $this->error('This database has no vector column — semantic search needs Postgres with pgvector.');

            return self::FAILURE;
        }

        $this->info("Embedding with {$embedder->name()}.");

        $batchSize = max(1, (int) config('search.embeddings.batch', 32));
        $limit = max(0, (int) $this->option('limit'));
        $done = 0;
        $failed = 0;

        $total = $this->pending()->count();

        if ($total === 0) {
            $this->info('Every passage already has a vector.');

            return self::SUCCESS;
        }

        $target = $limit > 0 ? min($limit, $total) : $total;
        $bar = $this->output->createProgressBar($target);
        $bar->start();

        while ($done < $target) {
            $batch = $this->pending()->limit(min($batchSize, $target - $done))->get();

            if ($batch->isEmpty()) {
                break;
            }

            try {
                $vectors = $embedder->embed($batch->pluck('content')->all());
            } catch (EmbeddingFailed $e) {
                // Skipped, not fatal: the run keeps its progress and the
                // passages it could not do are still pending next time.
                $failed += $batch->count();
                $this->newLine();
                $this->warn('  '.$e->getMessage());

                // Move past this batch so the loop cannot spin on it forever.
                $this->markUnembeddable($batch->pluck('id')->all(), $embedder->name());
                $done += $batch->count();
                $bar->setProgress(min($target, $done));

                continue;
            }

            DB::transaction(function () use ($batch, $vectors, $embedder) {
                foreach ($batch as $index => $chunk) {
                    $vector = $vectors[$index] ?? null;

                    if ($vector === null) {
                        continue;
                    }

                    DB::update(
                        'UPDATE book_chunks SET embedding = ?::vector, embedded_with = ? WHERE id = ?',
                        ['['.implode(',', $vector).']', $embedder->name(), $chunk->id],
                    );
                }
            });

            $done += $batch->count();
            $bar->setProgress(min($target, $done));
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(($done - $failed).' passage(s) embedded.');

        if ($failed > 0) {
            $this->warn("  {$failed} could not be embedded; run again to retry them.");
        }

        return self::SUCCESS;
    }

    private function pending()
    {
        return BookChunk::query()
            ->when(
                ! $this->option('force'),
                fn ($q) => $q->whereNull('embedded_with'),
            )
            ->orderBy('id');
    }

    /**
     * Note the attempt without a vector, so a passage the provider keeps
     * refusing does not block every later one behind it.
     *
     * @param  array<int, int>  $ids
     */
    private function markUnembeddable(array $ids, string $model): void
    {
        BookChunk::whereIn('id', $ids)->update(['embedded_with' => $model.' (failed)']);
    }

    private function supportsVectors(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::select("SELECT 1 FROM information_schema.columns WHERE table_name = 'book_chunks' AND column_name = 'embedding'") !== [];
    }
}
