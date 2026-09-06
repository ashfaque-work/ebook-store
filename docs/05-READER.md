# 05 — The Reader

The store currently ends at "download a file". That makes it a file locker. The reader is
what makes it a place people come back to, and it is the only part of this product where you
can beat Amazon — by being fast on a mid-range Android phone on a patchy connection, which
Kindle Cloud Reader is not.

---

## 1. Scope

| | In | Out (for now) |
|---|---|---|
| Formats | EPUB, PDF | MOBI, AZW, audiobooks |
| Delivery | Streamed, ownership-checked | Offline / service worker |
| State | Progress, bookmarks, highlights, notes | Social annotations, shared margins |
| Protection | Ownership gate, short-lived URLs, PDF watermark | Hard DRM |
| Samples | First chapter free, no account needed | Time-limited full-book loans |

**On DRM, honestly:** any file a browser can render can be extracted by a determined user.
Adobe-grade DRM costs more than this store will make in its first year and makes the reading
experience worse. The realistic goal is *friction plus attribution* — make casual sharing
inconvenient and make leaked copies traceable to a buyer. Watermarking does more for you than
encryption here. Do not spend weeks on this.

---

## 2. Libraries

| Need | Choice | Why |
|---|---|---|
| EPUB rendering | **epub.js** (`epubjs`) | The only mature browser EPUB renderer. Pagination, CFI locations, theming. |
| PDF rendering | **pdf.js** (`pdfjs-dist`) | Mozilla's. Canvas + text layer for selection. |
| Gestures | **@vueuse/core** `useSwipe`, `usePointerSwipe` | Already a Phase D dependency. |
| Persistence | Inertia POST + `useDebounceFn` | No extra client state library needed. |

`epub.js` is ~180 KB gzipped and `pdfjs-dist` ~350 KB. **Lazy-load both** — dynamic
`import()` inside the reader route only. They must never enter the storefront bundle.

Alternative worth knowing: **foliate-js** is a more modern EPUB renderer than epub.js but has
a smaller ecosystem. epub.js is the safer default; revisit if you hit its pagination bugs.

---

## 3. Routes

```php
Route::middleware('auth')->group(function () {
    Route::get('/read/{book:slug}',            [ReaderController::class, 'show'])->name('reader.show');
    Route::get('/read/{book:slug}/asset',      [ReaderController::class, 'asset'])
        ->middleware('throttle:120,1')->name('reader.asset');

    Route::post('/read/{book}/progress',       [ProgressController::class, 'store'])->name('reader.progress');
    Route::apiResource('read/{book}/bookmarks', BookmarkController::class)->only(['index','store','destroy']);
    Route::apiResource('read/{book}/highlights', HighlightController::class)->only(['index','store','update','destroy']);
});

// Samples — deliberately outside auth. This is the conversion lever.
Route::get('/read/{book:slug}/sample',       [ReaderController::class, 'sample'])->name('reader.sample');
Route::get('/read/{book:slug}/sample/asset', [ReaderController::class, 'sampleAsset'])
    ->middleware('throttle:60,1');
```

**The client never receives a file path.** It asks for `/read/{slug}/asset`; the server checks
ownership and either streams or issues a presigned URL. `file_path` stays in `$hidden` on the
model.

### Serving the asset

Two modes, chosen by disk:

```php
public function asset(Book $book)
{
    abort_unless(auth()->user()->hasPurchased($book), 403);

    $path = $book->getRawOriginal('file_path');

    // R2/S3 in production: presign, short TTL, let the CDN do the work.
    if (config('filesystems.default') === 's3') {
        return redirect()->away(
            Storage::disk('private')->temporaryUrl($path, now()->addMinutes(10))
        );
    }

    // Local dev: stream, honouring Range so epub.js/pdf.js can seek.
    return Storage::disk(Book::FILE_DISK)->response($path, null, [
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'private, max-age=600',
    ]);
}
```

Do **not** stream large files through PHP on a free-tier host — a 20 MB EPUB read by ten
people at once will exhaust the worker pool. Presigned URLs move that cost to R2, whose
egress is free.

A ten-minute TTL is a deliberate tradeoff: long enough that a reader does not break mid-book,
short enough that a leaked URL is worthless by the time it is shared.

---

## 4. Progress sync

```php
// reading_progress: unique(user_id, book_id) — see 03-DATABASE.md
ReadingProgress::updateOrCreate(
    ['user_id' => auth()->id(), 'book_id' => $book->id],
    ['location' => $validated['location'],
     'percent'  => $validated['percent'],
     'last_read_at' => now()],
);
```

Client side: debounce to **one write every 10 seconds**, plus a `visibilitychange` flush and
a `navigator.sendBeacon` on unload. Do not write per page turn — a fast reader would generate
hundreds of requests an hour.

`location` is an **EPUB CFI** (`epubcfi(/6/14[chap05]!/4/2/1:0)`) for EPUB, or a page number
for PDF. Both are opaque strings; store them as given, never parse them server-side.

Progress powers "Continue reading" on the library, which should be the first thing a returning
customer sees — above the grid, one large card with the cover, the title, and a progress bar.

---

## 5. Samples — the highest-value feature here

A reader who finishes a free chapter converts far better than one reading a blurb. Give this
priority over bookmarks and highlights.

- `books.sample_path` — a separate file on the private disk, uploaded by the admin, typically the first chapter.
- If no sample is uploaded for an EPUB, generate one: take the first ~10% of spine items. For PDF, extract the first N pages with a queued job.
- Readable **without an account**. No signup wall before the value.
- At the end of the sample: a full-bleed panel with the cover, the price in ₹, and one button — "Buy and keep reading". Not a modal, not a countdown.
- Track sample opens and completions; sample-completion → purchase is the funnel metric that matters most.

---

## 6. Reader UI

Detailed visual spec in [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md) §7. The essentials:

**Chrome disappears.** Header and footer fade out while reading and return on tap or pointer
movement. The page is the interface. This is the one screen where the design system's ink
palette gives way entirely to paper.

**Controls**
- Themes: Paper (warm white), Sepia, Night. Persist per user, not per device.
- Font size (5 steps), line height (3 steps), margin width (3 steps), font family (the book's own vs. Literata).
- Table of contents in a side drawer.
- Progress: percent, and "about 14 min left in this chapter" from a words-per-minute estimate. Time remaining is more useful than a percentage.

**Navigation**
- Tap left/right thirds, swipe, arrow keys, `space` / `shift+space`, `j`/`k`.
- `t` toggles TOC, `b` bookmarks, `Esc` exits. Show a `?` shortcut sheet.

**Accessibility**
- Real text, selectable, not canvas-only (this rules out image-only PDFs — flag those at upload).
- Respect `prefers-reduced-motion`: no page-curl animation, just an instant swap.
- Reader themes must all pass 4.5:1 body contrast.
- Font size controls change actual text size, not a CSS transform.

---

## 7. Watermarking

For **downloaded** PDFs only — not for in-browser reading.

Queue a job on first download that stamps a footer on each page:
`Licensed to ashfaque@example.com · Order ORD-XXXX · not for redistribution`.

Small, grey, in the bottom margin. `setasign/fpdi` + `tecnickcom/tcpdf` handles this. Cache
the watermarked file per user so a re-download is free.

Do not watermark EPUBs page-by-page; instead inject a metadata record and one colophon page.

---

## 8. Build order

1. `reading_progress` table + the asset route + ownership gate
2. EPUB reader, minimal chrome, progress save/restore
3. PDF reader on the same shell
4. Reader settings (themes, type controls) with per-user persistence
5. Samples — public route, end-of-sample buy panel
6. "Continue reading" on the library
7. Bookmarks
8. Highlights and notes
9. PDF watermarking on download

**Done when** a book can be read start to finish on a 360px phone screen, progress survives a
switch from phone to desktop, and a logged-out visitor can read chapter one and buy from
inside the reader.
