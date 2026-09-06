<?php

namespace App\Http\Controllers\Reader;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\ReadingProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Reading in the browser.
 *
 * The client never receives a file path. It asks for an asset route, the
 * server decides whether this person may have it, and only then streams or
 * presigns. `file_path` and `sample_path` stay hidden on the model.
 */
class ReaderController extends Controller
{
    private const MIME = [
        'epub' => 'application/epub+zip',
        'pdf' => 'application/pdf',
    ];

    /**
     * The reader itself, for a book the user owns.
     */
    public function show(Book $book): Response|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasPurchased($book)) {
            // Not an error they can act on — send them to the sample if there
            // is one, so the answer to "can I read this?" is never a dead end.
            return $book->hasSample()
                ? redirect()->route('reader.sample', $book)
                : redirect()->route('books.show', $book);
        }

        $book->load('author');

        $progress = ReadingProgress::firstWhere(['user_id' => $user->id, 'book_id' => $book->id]);

        return Inertia::render('Reader/Show', [
            'book' => $this->bookPayload($book),
            'assetUrl' => route('reader.asset', $book),
            'isSample' => false,
            'progress' => $progress ? [
                'location' => $progress->location,
                'percent' => $progress->percent,
            ] : null,
            'bookmarks' => Bookmark::where('user_id', $user->id)->where('book_id', $book->id)
                ->latest()->get(['id', 'location', 'label']),
            'highlights' => Highlight::where('user_id', $user->id)->where('book_id', $book->id)
                ->latest()->get(['id', 'location', 'text', 'note', 'color']),
        ]);
    }

    /**
     * The free sample. Deliberately open to anyone, signed in or not — a reader
     * who finishes a chapter converts far better than one reading a blurb, and
     * a signup wall in front of that is the wrong trade.
     */
    public function sample(Book $book): Response|RedirectResponse
    {
        abort_unless($book->is_published, 404);

        if (! $book->hasSample()) {
            return redirect()->route('books.show', $book);
        }

        $book->load('author');

        return Inertia::render('Reader/Show', [
            'book' => $this->bookPayload($book),
            'assetUrl' => route('reader.sample.asset', $book),
            'isSample' => true,
            'progress' => null,
            'bookmarks' => [],
            'highlights' => [],
        ]);
    }

    /**
     * Stream the full book. The gate on every paid asset.
     */
    public function asset(Book $book): HttpResponse
    {
        $this->authorize('download', $book);

        return $this->serve($book->getRawOriginal('file_path'), $book->file_format);
    }

    /**
     * Stream the sample. No ownership check — that is the point of a sample.
     */
    public function sampleAsset(Book $book): HttpResponse
    {
        abort_unless($book->is_published && $book->hasSample(), 404);

        return $this->serve($book->getRawOriginal('sample_path'), $book->file_format);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookPayload(Book $book): array
    {
        return [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'author' => $book->author?->name,
            'format' => $book->file_format,
            'cover' => $book->cover_image_path,
            'price_paise' => $book->price_paise,
            'page_count' => $book->page_count,
        ];
    }

    /**
     * Hand over the bytes.
     *
     * On object storage we presign and let the CDN do the work: streaming a
     * 20 MB EPUB through a 512 MB container is how the free tier falls over.
     * Locally we serve the file so byte ranges work, which is what lets pdf.js
     * seek instead of downloading everything up front.
     */
    private function serve(?string $path, string $format): HttpResponse
    {
        $disk = Storage::disk(Book::FILE_DISK);

        abort_if(! $path || ! $disk->exists($path), 404, 'File not found.');

        $headers = [
            'Content-Type' => self::MIME[$format] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (config('filesystems.disks.'.Book::FILE_DISK.'.driver') === 's3') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(10)));
        }

        // BinaryFileResponse handles Range requests for us.
        return response()->file($disk->path($path), $headers);
    }

    /**
     * Save where the reader has got to.
     *
     * Called on a debounce and again on unload via sendBeacon, so it must be
     * cheap and must never fail loudly.
     */
    public function progress(Request $request, Book $book): HttpResponse
    {
        $this->authorize('download', $book);

        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:512'],
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        ReadingProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            [
                'location' => $validated['location'] ?? null,
                'percent' => $validated['percent'],
                'last_read_at' => now(),
            ],
        );

        return response()->noContent();
    }
}
