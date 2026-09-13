<?php

namespace App\Services\Search;

use App\Models\Book;
use App\Models\BookChunk;
use App\Services\Search\Embeddings\EmbedderFactory;
use App\Services\Search\Embeddings\EmbeddingFailed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Find the passage, not the book.
 *
 * Catalogue search answers "do you have this book". This answers "where is the
 * bit where Darcy proposes" — which is the question someone who already owns
 * the book actually has, and the one a shelf of full text can answer and a
 * list of titles cannot.
 *
 * Postgres does the work where it can: a generated tsvector, a GIN index, and
 * websearch_to_tsquery so that quoted phrases and -exclusions behave the way
 * people expect from a search box. Elsewhere — SQLite, in tests — it falls
 * back to a substring match, which is worse at ranking but returns the same
 * shape of answer, so the feature is testable without a Postgres in the loop.
 */
class SearchInsideBooks
{
    public const PER_PAGE = 20;

    /**
     * How much of the catalogue must carry a vector before semantic search is
     * allowed to influence the ranking. Not a round number for its own sake:
     * a run that fails on a handful of passages should not disable the feature
     * for everyone, but a run that has barely started should.
     */
    private const COVERAGE_REQUIRED = 0.9;

    /**
     * @return array{results: array<int, array<string, mixed>>, total: int, engine: string}
     */
    public function __invoke(string $query, ?Book $book = null, int $limit = self::PER_PAGE): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return ['results' => [], 'total' => 0, 'engine' => $this->engine()];
        }

        $chunks = BookChunk::query()
            ->with(['book:id,slug,title,author_id,cover_image_path,price_paise', 'book.author:id,name'])
            ->when($book, fn (Builder $q) => $q->where('book_id', $book->id))
            // Only what a customer could reach anyway.
            ->when(! $book, fn (Builder $q) => $q->whereHas('book', fn ($b) => $b->where('is_published', true)));

        $semantic = $this->semanticIds($query, $book, $limit);

        if ($semantic !== []) {
            return $this->fused($query, $book, $semantic, $limit);
        }

        $chunks = $this->onPostgres()
            ? $this->rankWithPostgres($chunks, $query)
            : $this->rankWithSubstring($chunks, $query);

        // Without dropping the ordering, the count carries the ts_rank
        // expression into a query that does not need it — sorting rows it is
        // only going to tally.
        $total = (clone $chunks)->reorder()->count();
        $rows = $chunks->limit($limit)->get();

        return [
            'results' => $rows->map(fn (BookChunk $chunk) => $this->present($chunk, $query))->all(),
            'total' => $total,
            'engine' => $this->engine(),
        ];
    }

    /**
     * Passages closest in meaning to the question, by vector distance.
     *
     * Returns nothing — rather than failing — whenever semantic search is not
     * available: no provider configured, no pgvector, nothing embedded yet, or
     * the provider being down. The literal search is a perfectly good answer,
     * and an outage of something optional must not take the search box with it.
     *
     * @return array<int, int> chunk ids, nearest first
     */
    private function semanticIds(string $query, ?Book $book, int $limit): array
    {
        if (! $this->onPostgres() || ! $this->vectorsReady()) {
            return [];
        }

        $embedder = EmbedderFactory::make();

        if ($embedder === null) {
            return [];
        }

        try {
            $vector = $embedder->embed([$query])[0] ?? null;
        } catch (EmbeddingFailed) {
            return [];
        }

        if ($vector === null) {
            return [];
        }

        $rows = DB::select(
            'SELECT c.id FROM book_chunks c
             JOIN books b ON b.id = c.book_id
             WHERE c.embedding IS NOT NULL AND '.($book ? 'c.book_id = ?' : 'b.is_published = ?').'
             ORDER BY c.embedding <=> ?::vector
             LIMIT ?',
            [$book ? $book->id : true, '['.implode(',', $vector).']', $limit * 2],
        );

        return array_map(fn ($row) => (int) $row->id, $rows);
    }

    /**
     * Combine the two rankings with reciprocal rank fusion.
     *
     * A tsvector score and a distance in vector space are not on the same
     * scale and cannot be added. RRF uses only the position in each list,
     * which sidesteps that: a passage both methods like beats one only a
     * single method found, and a passage only one of them found still places.
     * The constant damps the top few ranks, so one confident wrong hit cannot
     * run away with the results.
     *
     * @param  array<int, int>  $semantic
     * @return array{results: array<int, array<string, mixed>>, total: int, engine: string}
     */
    private function fused(string $query, ?Book $book, array $semantic, int $limit): array
    {
        $k = 60;
        $scores = [];

        foreach ($semantic as $rank => $id) {
            $scores[$id] = ($scores[$id] ?? 0) + 1 / ($k + $rank + 1);
        }

        $literalQuery = BookChunk::query()
            ->when($book, fn (Builder $q) => $q->where('book_id', $book->id))
            ->when(! $book, fn (Builder $q) => $q->whereHas('book', fn ($b) => $b->where('is_published', true)));

        $literal = $this->rankWithPostgres($literalQuery, $query)->limit($limit * 2)->pluck('id')->all();

        foreach ($literal as $rank => $id) {
            $scores[$id] = ($scores[$id] ?? 0) + 1 / ($k + $rank + 1);
        }

        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $limit);

        $chunks = BookChunk::with(['book:id,slug,title,author_id,cover_image_path,price_paise', 'book.author:id,name'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $results = [];

        foreach ($ids as $id) {
            if ($chunk = $chunks->get($id)) {
                $results[] = $this->present($chunk, $query);
            }
        }

        return [
            'results' => $results,
            'total' => count($scores),
            'engine' => 'hybrid',
        ];
    }

    /**
     * Is there enough to compare against?
     *
     * Not "is there one vector". A half-embedded catalogue is actively worse
     * than none: the vector half of the ranking can only ever return passages
     * that happen to have been reached, so it promotes them over better
     * matches that have not been. Measured, this turned "universally
     * acknowledged" from Pride and Prejudice into King Arthur.
     *
     * So semantic search waits until nearly everything is embedded, and until
     * then the literal search answers alone — which it does well.
     */
    private function vectorsReady(): bool
    {
        return Cache::remember('search:vector-coverage', now()->addMinutes(10), function () {
            try {
                $row = DB::selectOne(
                    'SELECT COUNT(*) AS total, COUNT(embedding) AS embedded FROM book_chunks'
                );
            } catch (Throwable) {
                // No such column: this database cannot do it at all.
                return false;
            }

            $total = (int) ($row->total ?? 0);

            return $total > 0 && ((int) ($row->embedded ?? 0)) / $total >= self::COVERAGE_REQUIRED;
        });
    }

    private function rankWithPostgres(Builder $chunks, string $query): Builder
    {
        return $chunks
            ->whereRaw("searchable @@ websearch_to_tsquery('english', ?)", [$query])
            ->orderByRaw("ts_rank(searchable, websearch_to_tsquery('english', ?)) DESC", [$query])
            ->orderBy('book_id')
            ->orderBy('position');
    }

    /**
     * No ranking worth the name — the order is the book's own. It keeps the
     * feature working off Postgres rather than pretending to be as good.
     */
    private function rankWithSubstring(Builder $chunks, string $query): Builder
    {
        return $chunks
            ->where(fn (Builder $q) => $q
                ->whereLike('content', '%'.$query.'%')
                ->orWhereLike('heading', '%'.$query.'%'))
            ->orderBy('book_id')
            ->orderBy('position');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BookChunk $chunk, string $query): array
    {
        return [
            'id' => $chunk->id,
            'bookId' => $chunk->book_id,
            'bookTitle' => $chunk->book?->title,
            'bookSlug' => $chunk->book?->slug,
            'author' => $chunk->book?->author?->name,
            'cover' => $chunk->book?->cover_image_path,
            'pricePaise' => $chunk->book?->price_paise,
            'heading' => $chunk->heading,
            'section' => $chunk->section,
            'position' => $chunk->position,
            'excerpt' => $this->excerpt($chunk->content, $query),
        ];
    }

    /**
     * A window around the first match rather than the opening of the passage,
     * so the words someone searched for are visible in the result instead of
     * two hundred words above it.
     */
    private function excerpt(string $content, string $query): string
    {
        $needle = trim($query, " \t\n\r\0\x0B\"");
        $at = $needle === '' ? false : mb_stripos($content, $needle);

        if ($at === false) {
            // A stemmed or multi-word match that does not appear verbatim.
            foreach (preg_split('/\s+/u', $needle) ?: [] as $word) {
                if (mb_strlen($word) > 3 && ($at = mb_stripos($content, $word)) !== false) {
                    break;
                }
            }
        }

        if ($at === false) {
            return Str::limit($content, 260);
        }

        $start = max(0, $at - 90);
        $window = mb_substr($content, $start, 300);

        return ($start > 0 ? '…' : '').trim($window).'…';
    }

    private function onPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function engine(): string
    {
        return $this->onPostgres() ? 'full-text' : 'substring';
    }
}
