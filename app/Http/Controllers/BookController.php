<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Support\Meta;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function show(Book $book): Response
    {
        // Drafts are visible to admins only.
        $this->authorize('view', $book);

        $book->load('author', 'genre');

        return Inertia::render('Books/Show', [
            'meta' => Meta::forBook($book)->toArray(),
            'book' => [
                'id' => $book->id,
                'slug' => $book->slug,
                'title' => $book->title,
                'description' => $book->description,
                'excerpt' => $book->excerpt,
                'price_paise' => $book->price_paise,
                'cover_image_path' => $book->cover_image_path,
                'format' => strtoupper($book->file_format),
                'page_count' => $book->page_count,
                'language' => $book->language,
                'isbn' => $book->isbn,
                'is_published' => $book->is_published,
                'author' => ['name' => $book->author?->name],
                'genre' => ['name' => $book->genre?->name, 'slug' => $book->genre?->slug],
            ],
            'isBookInCart' => in_array($book->id, Session::get('cart', []), true),
            'isPurchased' => (bool) auth()->user()?->hasPurchased($book),
            'hasSample' => $book->hasSample(),
            'canReview' => (bool) auth()->user()?->hasPurchased($book),
            'myReview' => $this->myReview($book),
            // Below the fold, so neither holds up first paint.
            'related' => Inertia::defer(fn () => $this->related($book)),
            'reviews' => Inertia::defer(fn () => $this->reviews($book)),
        ]);
    }

    /**
     * This reader's own review, so the form opens with what they already said
     * rather than blank — and so a second submission edits it instead of
     * silently failing on the unique key.
     *
     * @return array<string, mixed>|null
     */
    private function myReview(Book $book): ?array
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $review = Review::where('book_id', $book->id)->where('user_id', $user->id)->first();

        return $review ? [
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'hidden' => $review->isHidden(),
        ] : null;
    }

    /**
     * What readers said, and the summary a shopper actually reads first.
     *
     * @return array<string, mixed>
     */
    private function reviews(Book $book): array
    {
        $visible = Review::visible()->where('book_id', $book->id);

        $spread = (clone $visible)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        return [
            'average' => round((float) (clone $visible)->avg('rating'), 1),
            'count' => (clone $visible)->count(),
            // Every rating from one to five, including the ones nobody gave,
            // so the bar chart has a shape instead of gaps.
            'spread' => collect(range(5, 1))->map(fn ($star) => [
                'stars' => $star,
                'count' => (int) ($spread[$star] ?? 0),
            ])->all(),
            'items' => (clone $visible)
                ->with('user:id,name')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Review $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'body' => $review->body,
                    'verified' => $review->verified,
                    'percentRead' => $review->percent_read,
                    'name' => $review->displayName(),
                    'when' => $review->created_at?->diffForHumans(),
                ])
                ->all(),
        ];
    }

    /**
     * More from the same author first, then the same shelf.
     *
     * @return array<int, array<string, mixed>>
     */
    private function related(Book $book): array
    {
        $byAuthor = Book::published()
            ->with('author')
            ->where('author_id', $book->author_id)
            ->whereKeyNot($book->id)
            ->limit(5)
            ->get();

        $sameGenre = Book::published()
            ->with('author')
            ->where('genre_id', $book->genre_id)
            ->whereKeyNot($book->id)
            ->whereNotIn('id', $byAuthor->pluck('id'))
            ->latest('published_at')
            ->limit(10 - $byAuthor->count())
            ->get();

        return $byAuthor->concat($sameGenre)
            ->map(fn (Book $related) => [
                'id' => $related->id,
                'slug' => $related->slug,
                'title' => $related->title,
                'price_paise' => $related->price_paise,
                'cover_image_path' => $related->cover_image_path,
                'author' => ['name' => $related->author?->name],
            ])
            ->values()
            ->all();
    }
}
