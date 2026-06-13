<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Author;
use App\Models\Genre;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        // Eager load relationships to prevent N+1 query issues
        $books = Book::with(['author', 'genre'])->latest()->get();

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

        $coverImagePath = $request->file('cover_image')->store('covers', 'public');
        $bookFilePath = $request->file('book_file')->store('books', 'public');

        Book::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'author_id' => $validated['author_id'],
            'genre_id' => $validated['genre_id'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'cover_image_path' => $coverImagePath,
            'file_path' => $bookFilePath,
        ]);

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book created successfully.'
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

        $updateData = $validated;
        $updateData['slug'] = Str::slug($validated['title']);

        if ($request->hasFile('cover_image')) {
            // Delete old cover image
            if ($book->cover_image_path) {
                Storage::disk('public')->delete($book->cover_image_path);
            }
            $updateData['cover_image_path'] = $request->file('cover_image')->store('covers', 'public');
        }

        if ($request->hasFile('book_file')) {
            // Delete old book file
            if ($book->file_path) {
                Storage::disk('public')->delete($book->file_path);
            }
            $updateData['file_path'] = $request->file('book_file')->store('books', 'public');
        }

        $book->update($updateData);

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book updated successfully.'
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book): RedirectResponse
    {
        // Delete associated files from storage
        if ($book->cover_image_path) {
            Storage::disk('public')->delete($book->cover_image_path);
        }
        if ($book->file_path) {
            Storage::disk('public')->delete($book->file_path);
        }

        $book->delete();

        return redirect(route('admin.books.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Book deleted successfully.'
        ]);
    }
}
