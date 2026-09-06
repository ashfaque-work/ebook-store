<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingProgress;
use App\Support\Meta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The storefront.
 *
 * Two modes, because they answer different questions. Searching or filtering
 * gives a results list. Arriving with no query gives a shelf: one book opened
 * up so you can read a page of it, then rows by genre.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = array_filter($request->only(['search', 'genre']));

        return $filters
            ? $this->results($filters)
            : $this->shelves($request);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function results(array $filters): Response
    {
        $books = Book::published()
            ->with(['author', 'genre'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('author', fn ($a) => $a->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['genre'] ?? null, function ($query, $slug) {
                $query->whereHas('genre', fn ($g) => $g->where('slug', $slug));
            })
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $label = $filters['search'] ?? null;

        return Inertia::render('Welcome', [
            'meta' => Meta::make(
                title: $label ? 'Search: '.$label : 'Browse books',
                description: 'Digital books you can read in your browser, delivered the moment you buy them.',
                // A results page is one of infinitely many query strings; the
                // catalogue itself is the page worth indexing.
                noindex: true,
            )->toArray(),
            'mode' => 'results',
            'books' => $books,
            'genres' => $this->genres(),
            'filters' => $filters,
        ]);
    }

    private function shelves(Request $request): Response
    {
        $featured = Book::published()
            ->with('author')
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->first();

        return Inertia::render('Welcome', [
            'meta' => Meta::make(
                title: 'Books worth your evening',
                description: 'Buy a book and start reading in seconds — in your browser, on any device. '
                    .'Free first chapters on everything.',
                canonical: route('home'),
                image: $featured?->cover_image_path,
            )->toArray(),
            'mode' => 'shelves',
            'genres' => $this->genres(),
            'filters' => [],
            'featured' => $featured ? $this->featurePayload($featured) : null,
            'newest' => $this->shelfBooks(Book::published()->latest('published_at')->limit(10)),
            // Reviews and related rows are not needed for first paint.
            'shelves' => Inertia::defer(fn () => $this->genreShelves($featured)),
            'continueReading' => $request->user()
                ? $this->continueReading($request->user())
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function featurePayload(Book $book): array
    {
        return [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'author' => $book->author?->name,
            'cover_image_path' => $book->cover_image_path,
            'price_paise' => $book->price_paise,
            // Lead with the writing; fall back to the blurb when there is no
            // opening passage on file yet.
            'excerpt' => $book->excerpt ?: $book->description,
            'isExcerpt' => (bool) $book->excerpt,
            'hasSample' => $book->hasSample(),
        ];
    }

    /**
     * A few genre rows, biggest first, skipping the featured book so it does
     * not appear twice on one screen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function genreShelves(?Book $featured): array
    {
        return Genre::query()
            ->withCount(['books' => fn ($q) => $q->where('is_published', true)])
            ->having('books_count', '>', 0)
            ->orderByDesc('books_count')
            ->limit(4)
            ->get()
            ->map(fn (Genre $genre) => [
                'name' => $genre->name,
                'slug' => $genre->slug,
                'books' => $this->shelfBooks(
                    Book::published()
                        ->where('genre_id', $genre->id)
                        ->when($featured, fn ($q) => $q->whereKeyNot($featured->id))
                        ->latest('published_at')
                        ->limit(10),
                ),
            ])
            ->values()
            ->all();
    }

    private function shelfBooks($query): Collection
    {
        return $query->with('author')->get()
            ->map(fn (Book $book) => [
                'id' => $book->id,
                'slug' => $book->slug,
                'title' => $book->title,
                'price_paise' => $book->price_paise,
                'cover_image_path' => $book->cover_image_path,
                'author' => ['name' => $book->author?->name],
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function continueReading($user): ?array
    {
        $progress = ReadingProgress::with('book')
            ->where('user_id', $user->id)
            ->where('percent', '<', 98)
            ->whereNotNull('last_read_at')
            ->latest('last_read_at')
            ->first();

        return $progress?->book ? [
            'slug' => $progress->book->slug,
            'title' => $progress->book->title,
            'percent' => $progress->percent,
        ] : null;
    }

    private function genres(): Collection
    {
        return Genre::orderBy('name')->get(['id', 'name', 'slug']);
    }
}
