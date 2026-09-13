<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\Search\SearchInsideBooks;
use App\Support\Meta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Search the text of the books, not the titles.
 *
 * Open to everyone on purpose. Being able to find the passage you half
 * remember, and then being shown which book it is in, is the best argument
 * this shop can make for itself — putting it behind a login would hide the one
 * thing a catalogue of titles cannot do.
 *
 * What it returns is a passage, not the book: a few hundred words, the same
 * amount any shop would let you read standing at the shelf.
 */
class SearchInsideController extends Controller
{
    public function __invoke(Request $request, SearchInsideBooks $search): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'book' => ['nullable', 'string', 'max:255'],
        ]);

        $query = trim($validated['q'] ?? '');

        $book = isset($validated['book'])
            ? Book::where('slug', $validated['book'])->where('is_published', true)->first()
            : null;

        $found = $query === ''
            ? ['results' => [], 'total' => 0, 'engine' => null]
            : $search($query, $book);

        return Inertia::render('SearchInside', [
            'meta' => Meta::make(
                title: $query === '' ? 'Search inside the books' : "“{$query}” inside the books",
                description: 'Search the full text of every book in the shop and find the passage, not just the title.',
                canonical: route('search.inside'),
                // A search page is not something to have indexed: it is a view
                // of the catalogue, not a page of it.
                noindex: true,
            )->toArray(),
            'query' => $query,
            'book' => $book ? ['slug' => $book->slug, 'title' => $book->title] : null,
            'results' => $found['results'],
            'total' => $found['total'],
            'indexedBooks' => Book::where('is_published', true)->whereHas('chunks')->count(),
        ]);
    }
}
