<?php

namespace App\Http\Controllers;

use App\Models\Book;
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
            // Below the fold, so it does not hold up first paint.
            'related' => Inertia::defer(fn () => $this->related($book)),
        ]);
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
