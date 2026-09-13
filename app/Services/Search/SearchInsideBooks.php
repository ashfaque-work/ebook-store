<?php

namespace App\Services\Search;

use App\Models\Book;
use App\Models\BookChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
