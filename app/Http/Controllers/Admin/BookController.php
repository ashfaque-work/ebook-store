<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'genre']);

        $books = Book::with(['author', 'genre'])
            ->withCount('orderItems')
            // Fifty-odd books is already more than fits on a screen, and a
            // catalogue only grows. Scrolling pages to find one title is the
            // thing an admin does most.
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereLike('title', "%{$search}%")
                        ->orWhereHas('author', fn ($a) => $a->whereLike('name', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'published' => $q->where('is_published', true),
                'draft' => $q->where('is_published', false),
                'free' => $q->where('price_paise', 0),
                'paid' => $q->where('price_paise', '>', 0),
                // An unknown value narrows nothing rather than erroring: this
                // arrives from a query string and is not to be trusted.
                default => $q,
            })
            ->when($filters['genre'] ?? null, fn ($q, $genre) => $q->whereHas('genre', fn ($g) => $g->where('slug', $genre)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Books/Index', [
            'books' => $books,
            'filters' => $filters,
            'genres' => Genre::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Pass authors and genres to the form for dropdowns
        return Inertia::render('Admin/Books/Create', [
            'authors' => Author::orderBy('name')->get(),
            'genres' => Genre::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author_id' => 'required|exists:authors,id',
            'genre_id' => 'required|exists:genres,id',
            'description' => 'required|string',
            'excerpt' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'is_published' => 'boolean',
            'cover_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'book_file' => 'required|file|mimes:pdf,epub|max:10240', // 10MB Max
            // The free preview. Same format as the book so one reader serves both.
            'sample_file' => 'nullable|file|mimes:pdf,epub|max:10240',
        ]);

        // Covers are marketing images and stay on the public disk.
        $coverImagePath = $request->file('cover_image')->store('covers', Book::coverDisk());
        // The actual ebook is a paid asset: store it on the PRIVATE disk so it
        // is never reachable by direct URL. It is delivered only through the
        // gated library download route after a confirmed purchase.
        $file = $request->file('book_file');
        $bookFilePath = $file->store('books', Book::fileDisk());

        $isPublished = $validated['is_published'] ?? true;

        Book::create([
            'title' => $validated['title'],
            'slug' => Book::uniqueSlug($validated['title']),
            'author_id' => $validated['author_id'],
            'genre_id' => $validated['genre_id'],
            'description' => $validated['description'],
            'excerpt' => $validated['excerpt'] ?? null,
            'price' => $validated['price'],
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
            'cover_image_path' => $coverImagePath,
            'file_path' => $bookFilePath,
            'file_format' => strtolower($file->getClientOriginalExtension()) ?: 'pdf',
            'file_size' => $file->getSize(),
            'sample_path' => $request->hasFile('sample_file')
                ? $request->file('sample_file')->store('samples', Book::fileDisk())
                : null,
        ]);

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book created successfully.',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book): Response
    {
        return Inertia::render('Admin/Books/Edit', [
            'book' => $book,
            'authors' => Author::orderBy('name')->get(),
            'genres' => Genre::orderBy('name')->get(),
            'hasBeenPurchased' => $book->hasBeenPurchased(),
            'hasSample' => $book->hasSample(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Book $book): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author_id' => 'required|exists:authors,id',
            'genre_id' => 'required|exists:genres,id',
            'description' => 'required|string',
            'excerpt' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'is_published' => 'boolean',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'book_file' => 'nullable|file|mimes:pdf,epub|max:10240',
            'sample_file' => 'nullable|file|mimes:pdf,epub|max:10240',
        ]);

        // Drop the uploaded-file keys; only real columns should reach update().
        unset($validated['cover_image'], $validated['book_file'], $validated['sample_file']);

        $updateData = $validated;
        $updateData['slug'] = Book::uniqueSlug($validated['title'], $book->id);

        // Stamp the first time it goes live, and never overwrite that date.
        if (($validated['is_published'] ?? false) && ! $book->published_at) {
            $updateData['published_at'] = now();
        }

        if ($request->hasFile('cover_image')) {
            // Delete old cover image (use the raw stored key, not the URL the
            // coverImagePath accessor produces).
            if ($book->getRawOriginal('cover_image_path')) {
                Storage::disk(Book::coverDisk())->delete($book->getRawOriginal('cover_image_path'));
            }
            $updateData['cover_image_path'] = $request->file('cover_image')->store('covers', Book::coverDisk());
        }

        if ($request->hasFile('book_file')) {
            // Delete old book file from the private disk
            if ($book->getRawOriginal('file_path')) {
                Storage::disk(Book::fileDisk())->delete($book->getRawOriginal('file_path'));
            }
            $file = $request->file('book_file');
            $updateData['file_path'] = $file->store('books', Book::fileDisk());
            $updateData['file_format'] = strtolower($file->getClientOriginalExtension()) ?: 'pdf';
            $updateData['file_size'] = $file->getSize();
        }

        if ($request->hasFile('sample_file')) {
            if ($book->getRawOriginal('sample_path')) {
                Storage::disk(Book::fileDisk())->delete($book->getRawOriginal('sample_path'));
            }
            $updateData['sample_path'] = $request->file('sample_file')->store('samples', Book::fileDisk());
        }

        $book->update($updateData);

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * A book that somebody has paid for is never deleted: order_items holds a
     * restrictOnDelete foreign key, so the delete would fail — and previously
     * it failed *after* the files had already been erased, silently destroying
     * every buyer's copy. Check first, and only touch storage once the row is
     * definitely gone.
     */
    public function destroy(Book $book): RedirectResponse
    {
        if ($book->hasBeenPurchased()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This book has been purchased and cannot be deleted. Unpublish it instead.',
            ]);
        }

        $coverPath = $book->getRawOriginal('cover_image_path');
        $filePath = $book->getRawOriginal('file_path');
        $samplePath = $book->getRawOriginal('sample_path');

        $book->delete();

        // Only now is it safe to destroy the files.
        if ($coverPath) {
            Storage::disk(Book::coverDisk())->delete($coverPath);
        }
        if ($filePath) {
            Storage::disk(Book::fileDisk())->delete($filePath);
        }
        if ($samplePath) {
            Storage::disk(Book::fileDisk())->delete($samplePath);
        }

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book deleted successfully.',
        ]);
    }
}
