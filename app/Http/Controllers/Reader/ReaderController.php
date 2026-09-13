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
     * Hand over the bytes, from this origin, whatever disk they live on.
     *
     * Presigning object storage and redirecting the browser is cheaper, and it
     * does not work: the reader fetches this with XHR, so the redirect target
     * has to be allowed by the page's connect-src *and* answer with CORS
     * headers. R2 sends none, and the signed host is not in the policy — so
     * the request dies silently and the reader sits on "Opening the book…"
     * forever. Nothing is logged, because from the server's side it all
     * succeeded.
     *
     * It was invisible in development for the worst possible reason: the local
     * disk took a different branch and served the file directly. One path now,
     * so what is exercised locally is what runs in production.
     *
     * The cost is real — the bytes travel through the container — but a book
     * is a few megabytes and this is bounded by an ownership check, not open
     * to the internet.
     */
    private function serve(?string $path, string $format): HttpResponse
    {
        $disk = Storage::disk(Book::fileDisk());

        abort_if(! $path || ! $disk->exists($path), 404, 'File not found.');

        $headers = [
            'Content-Type' => self::MIME[$format] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
            // Said plainly rather than left to be discovered: this streams from
            // object storage, so it cannot answer a range request. pdf.js reads
            // this and fetches the whole file instead of seeking.
            'Accept-Ranges' => 'none',
        ];

        if ($size = $disk->size($path)) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);

            if ($stream === false || $stream === null) {
                return;
            }

            // Copied in chunks rather than read into a string: a 20 MB book
            // read whole is 20 MB of memory in a 512 MB container, per reader.
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
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
