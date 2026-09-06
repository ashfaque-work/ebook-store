<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\Order;
use App\Models\ReadingProgress;
use Illuminate\Http\RedirectResponse;
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
            // The first thing a returning reader should see.
            'continueReading' => $this->continueReading($user),
        ]);
    }

    /**
     * The book this reader is part-way through, if there is one.
     *
     * @return array<string, mixed>|null
     */
    private function continueReading($user): ?array
    {
        $progress = ReadingProgress::with('book.author')
            ->where('user_id', $user->id)
            ->where('percent', '<', 98)
            ->whereNotNull('last_read_at')
            ->latest('last_read_at')
            ->first();

        if (! $progress?->book) {
            return null;
        }

        return [
            'book' => [
                'id' => $progress->book->id,
                'slug' => $progress->book->slug,
                'title' => $progress->book->title,
                'author' => $progress->book->author?->name,
                'cover_image_path' => $progress->book->cover_image_path,
            ],
            'percent' => $progress->percent,
            'lastReadAt' => $progress->last_read_at,
        ];
    }

    /**
     * Stream the ebook file — only if the user actually owns it.
     */
    public function download(Request $request, Book $book): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $book);

        $path = $book->getRawOriginal('file_path');

        abort_if(! $path || ! Storage::disk(Book::fileDisk())->exists($path), 404, 'File not found.');

        // Evidence for refund decisions and for spotting a shared account.
        DownloadLog::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
        $downloadName = Str::slug($book->title).'.'.$extension;

        // On object storage, hand out a short-lived URL rather than pushing
        // tens of megabytes through the application container.
        if (config('filesystems.disks.'.Book::fileDisk().'.driver') === 's3') {
            return redirect()->away(Storage::disk(Book::fileDisk())->temporaryUrl(
                $path,
                now()->addMinutes(10),
                ['ResponseContentDisposition' => 'attachment; filename="'.$downloadName.'"'],
            ));
        }

        return Storage::disk(Book::fileDisk())->download($path, $downloadName);
    }
}
