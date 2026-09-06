<?php

namespace App\Http\Controllers\Reader;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Bookmark;
use App\Models\Highlight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bookmarks and highlights. Both belong to one reader and one book, and both
 * are only ever reachable for a book that reader owns.
 */
class AnnotationController extends Controller
{
    public function storeBookmark(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('download', $book);

        $validated = $request->validate([
            'location' => ['required', 'string', 'max:512'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        Bookmark::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'book_id' => $book->id,
                'location' => $validated['location'],
            ],
            ['label' => $validated['label'] ?? null],
        );

        return back();
    }

    public function destroyBookmark(Request $request, Book $book, Bookmark $bookmark): RedirectResponse
    {
        $this->authorizeAnnotation($request, $book, $bookmark->user_id, $bookmark->book_id);

        $bookmark->delete();

        return back();
    }

    public function storeHighlight(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('download', $book);

        $validated = $request->validate([
            'location' => ['required', 'string', 'max:512'],
            'text' => ['required', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        Highlight::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'location' => $validated['location'],
            'text' => $validated['text'],
            'note' => $validated['note'] ?? null,
            'color' => $validated['color'] ?? 'yellow',
        ]);

        return back();
    }

    public function updateHighlight(Request $request, Book $book, Highlight $highlight): RedirectResponse
    {
        $this->authorizeAnnotation($request, $book, $highlight->user_id, $highlight->book_id);

        $highlight->update($request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:16'],
        ]));

        return back();
    }

    public function destroyHighlight(Request $request, Book $book, Highlight $highlight): RedirectResponse
    {
        $this->authorizeAnnotation($request, $book, $highlight->user_id, $highlight->book_id);

        $highlight->delete();

        return back();
    }

    /**
     * An annotation is only yours if it is yours *and* it belongs to the book
     * in the URL — otherwise the book in the path is decorative and any id
     * would do.
     */
    private function authorizeAnnotation(Request $request, Book $book, int $ownerId, int $bookId): void
    {
        $this->authorize('download', $book);

        abort_unless($ownerId === $request->user()->id && $bookId === $book->id, 403);
    }
}
