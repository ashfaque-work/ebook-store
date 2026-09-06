<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The public catalogue.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $request->only(['search', 'genre']);

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
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Welcome', [
            'books' => $books,
            'genres' => Genre::orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => $filters,
        ]);
    }
}
