<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryController extends Controller
{
    /**
     * List the books the authenticated user has purchased.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $books = Book::with(['author', 'genre'])
            ->whereHas('orderItems.order', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', Order::STATUS_PAID);
            })
            ->latest()
            ->get();

        return Inertia::render('Library/Index', [
            'books' => $books,
        ]);
    }

    /**
     * Stream the ebook file — only if the user actually owns it.
     */
    public function download(Book $book): StreamedResponse
    {
        $user = auth()->user();

        abort_unless($user->hasPurchased($book), 403, 'You have not purchased this book.');

        $path = $book->getRawOriginal('file_path');

        abort_if(! $path || ! Storage::disk(Book::FILE_DISK)->exists($path), 404, 'File not found.');

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
        $downloadName = \Illuminate\Support\Str::slug($book->title).'.'.$extension;

        return Storage::disk(Book::FILE_DISK)->download($path, $downloadName);
    }
}
