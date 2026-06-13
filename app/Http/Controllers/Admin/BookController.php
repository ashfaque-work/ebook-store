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
    public function index(): Response
    {
        // Eager load relationships to prevent N+1 query issues
        $books = Book::with(['author', 'genre'])->latest()->paginate(15);

        return Inertia::render('Admin/Books/Index', [
            'books' => $books,
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
            'price' => 'required|numeric|min:0',
            'cover_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'book_file' => 'required|file|mimes:pdf,epub|max:10240', // 10MB Max
        ]);

        // Covers are marketing images and stay on the public disk.
        $coverImagePath = $request->file('cover_image')->store('covers', 'public');
        // The actual ebook is a paid asset: store it on the PRIVATE disk so it
        // is never reachable by direct URL. It is delivered only through the
        // gated library download route after a confirmed purchase.
        $bookFilePath = $request->file('book_file')->store('books', Book::FILE_DISK);

        Book::create([
            'title' => $validated['title'],
            'slug' => Book::uniqueSlug($validated['title']),
            'author_id' => $validated['author_id'],
            'genre_id' => $validated['genre_id'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'cover_image_path' => $coverImagePath,
            'file_path' => $bookFilePath,
        ]);

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book)
    {
        // Not used for this project
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
            'price' => 'required|numeric|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'book_file' => 'nullable|file|mimes:pdf,epub|max:10240',
        ]);

        // Drop the uploaded-file keys; only real columns should reach update().
        unset($validated['cover_image'], $validated['book_file']);

        $updateData = $validated;
        $updateData['slug'] = Book::uniqueSlug($validated['title'], $book->id);

        if ($request->hasFile('cover_image')) {
            // Delete old cover image (use the raw stored key, not the URL the
            // coverImagePath accessor produces).
            if ($book->getRawOriginal('cover_image_path')) {
                Storage::disk('public')->delete($book->getRawOriginal('cover_image_path'));
            }
            $updateData['cover_image_path'] = $request->file('cover_image')->store('covers', 'public');
        }

        if ($request->hasFile('book_file')) {
            // Delete old book file from the private disk
            if ($book->file_path) {
                Storage::disk(Book::FILE_DISK)->delete($book->file_path);
            }
            $updateData['file_path'] = $request->file('book_file')->store('books', Book::FILE_DISK);
        }

        $book->update($updateData);

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book): RedirectResponse
    {
        // Delete associated files from storage (raw key for the cover, since
        // the accessor returns a URL; private disk for the ebook file).
        if ($book->getRawOriginal('cover_image_path')) {
            Storage::disk('public')->delete($book->getRawOriginal('cover_image_path'));
        }
        if ($book->file_path) {
            Storage::disk(Book::FILE_DISK)->delete($book->file_path);
        }

        $book->delete();

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book deleted successfully.',
        ]);
    }
}
