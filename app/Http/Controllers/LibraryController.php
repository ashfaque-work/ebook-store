<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->paginate(12);

        return Inertia::render('Library/Index', [
            'books' => $books,
        ]);
    }

    /**
     * Stream the ebook file — only if the user actually owns it.
     */
    public function download(Request $request, Book $book): StreamedResponse
    {
        $this->authorize('download', $book);

        $path = $book->getRawOriginal('file_path');

        abort_if(! $path || ! Storage::disk(Book::FILE_DISK)->exists($path), 404, 'File not found.');

        // Evidence for refund decisions and for spotting a shared account.
        DownloadLog::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
        $downloadName = Str::slug($book->title).'.'.$extension;

        return Storage::disk(Book::FILE_DISK)->download($path, $downloadName);
    }
}
