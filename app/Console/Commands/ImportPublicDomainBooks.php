<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fill the catalogue with real books.
 *
 * A store with nothing in it cannot be judged, demonstrated, or reviewed — and
 * Razorpay reviews the live site during KYC. Seeded lorem ipsum is worse than
 * empty: it tells a visitor the thing is not real.
 *
 * The source is Project Gutenberg, through the Gutendex metadata API. Real
 * titles, real authors, real cover art and the summaries Gutenberg writes.
 *
 * Everything imported is priced at zero, and that is a licensing requirement
 * rather than a default. Project Gutenberg's licence asks 20% of gross profits
 * on anything sold carrying its trademark, which these EPUBs do. Distributing
 * them free carries no such condition. Price your own titles; leave these.
 */
class ImportPublicDomainBooks extends Command
{
    protected $signature = 'store:import-books
        {--count=50 : How many books to import}
        {--paid : Price part of the catalogue instead of importing it all free}
        {--dry-run : List what would be imported without downloading or writing}';

    protected $description = 'Import public-domain books from Project Gutenberg';

    private const API = 'https://gutendex.com/books';

    /**
     * Gutenberg asks that automated clients identify themselves. This is a
     * handful of requests, run by hand, but the courtesy costs nothing.
     */
    private const AGENT = 'ebook-store catalogue importer (one-off, low volume)';

    private int $imported = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $wanted = max(1, (int) $this->option('count'));
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('paid') && ! $dryRun && ! $this->confirmPricing()) {
            return self::FAILURE;
        }

        $this->info($dryRun
            ? "Dry run: the first {$wanted} books that would be imported."
            : "Importing up to {$wanted} public-domain books.");
        $this->newLine();

        $page = self::API.'?'.http_build_query([
            'languages' => 'en',
            // Public domain only. Gutenberg also hosts a few copyrighted works
            // it has permission to distribute; those are not ours to sell or
            // give away.
            'copyright' => 'false',
            'mime_type' => 'application/epub+zip',
            'sort' => 'popular',
        ]);

        $bar = $dryRun ? null : $this->output->createProgressBar($wanted);
        $bar?->start();

        while ($page && $this->imported + $this->skipped < $wanted) {
            try {
                $response = Http::withUserAgent(self::AGENT)->timeout(60)->retry(3, 2000)->get($page);
            } catch (Throwable $e) {
                $this->newLine();
                $this->error('Could not reach the catalogue API: '.$e->getMessage());

                return self::FAILURE;
            }

            if (! $response->successful()) {
                $this->newLine();
                $this->error('Catalogue API returned '.$response->status());

                return self::FAILURE;
            }

            $body = $response->json();

            foreach ($body['results'] ?? [] as $entry) {
                if ($this->imported + $this->skipped >= $wanted) {
                    break;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '  %-45s %s',
                        Str::limit($entry['title'] ?? '(untitled)', 44),
                        $this->authorName($entry),
                    ));
                    $this->imported++;

                    continue;
                }

                $this->importOne($entry);
                $bar?->setProgress(min($wanted, $this->imported + $this->skipped));
            }

            $page = $body['next'] ?? null;
        }

        $bar?->finish();
        $this->newLine(2);

        if ($dryRun) {
            return self::SUCCESS;
        }

        $this->info("Imported {$this->imported}.");

        if ($this->skipped > 0) {
            $this->line("  {$this->skipped} already in the catalogue.");
        }

        if ($this->failed > 0) {
            $this->warn("  {$this->failed} could not be downloaded and were left out.");
        }

        return self::SUCCESS;
    }

    /**
     * Make the obligation explicit before it is taken on.
     *
     * The text of these books is public domain and always will be. The
     * editions are not the point either — what matters is that Gutenberg's
     * trademark travels inside the EPUB, and its licence asks 20% of gross
     * profits on anything sold carrying it.
     */
    private function confirmPricing(): bool
    {
        $this->newLine();
        $this->warn('  Pricing Project Gutenberg editions');
        $this->line('  The texts are public domain, but these EPUBs carry Project Gutenberg\'s');
        $this->line('  trademark, and its licence asks 20% of gross profits on copies you sell.');
        $this->line('  Distributing them free carries no such condition.');
        $this->newLine();
        $this->line('  <fg=gray>Your own titles are unaffected — price those however you like.</>');
        $this->newLine();

        return $this->confirm('Price part of this catalogue anyway?', false);
    }

    /**
     * A catalogue where everything costs the same reads as a demo. This gives
     * the shelves a shape: a good share free, the rest across a few believable
     * Indian ebook price points.
     *
     * Deterministic on the source id, so re-running does not reshuffle prices
     * under books somebody has already seen.
     */
    private function priceFor(array $entry): int
    {
        if (! $this->option('paid')) {
            return 0;
        }

        $bucket = ((int) ($entry['id'] ?? 0)) % 10;

        // Four in ten stay free. A free shelf is what gets someone to open a
        // book at all, and a reader who has opened one buys the next.
        return match (true) {
            $bucket < 4 => 0,
            $bucket < 6 => 4900,
            $bucket < 8 => 9900,
            $bucket === 8 => 14900,
            default => 19900,
        };
    }

    private function importOne(array $entry): void
    {
        $title = trim((string) ($entry['title'] ?? ''));

        if ($title === '') {
            $this->failed++;

            return;
        }

        // Gutenberg titles occasionally carry a subtitle on a newline.
        $title = Str::of($title)->replace(["\r\n", "\n", "\r"], ' — ')->squish()->toString();
        $slug = Str::slug(Str::limit($title, 80, ''));

        if ($slug === '' || Book::where('slug', $slug)->exists()) {
            $this->skipped++;

            return;
        }

        $epubUrl = $this->pick($entry['formats'] ?? [], 'application/epub+zip');
        $coverUrl = $this->pick($entry['formats'] ?? [], 'image/jpeg');

        if (! $epubUrl) {
            $this->failed++;

            return;
        }

        $epub = $this->download($epubUrl);

        if ($epub === null) {
            $this->failed++;

            return;
        }

        $filePath = 'books/'.Str::random(40).'.epub';
        Storage::disk(Book::fileDisk())->put($filePath, $epub);

        $coverPath = null;

        if ($coverUrl && $cover = $this->download($coverUrl)) {
            $coverPath = 'covers/'.Str::random(40).'.jpg';
            Storage::disk(Book::coverDisk())->put($coverPath, $cover);
        }

        $summary = trim((string) (($entry['summaries'][0] ?? '')));
        $price = $this->priceFor($entry);

        Book::create([
            'author_id' => $this->author($entry)->id,
            'genre_id' => $this->genre($entry)->id,
            'title' => $title,
            'slug' => $slug,
            'description' => $summary !== '' ? $summary : null,
            'excerpt' => $summary !== '' ? Str::limit($summary, 180) : null,
            'language' => 'en',
            'price' => $price / 100,
            'price_paise' => $price,
            'is_published' => true,
            'published_at' => now(),
            'cover_image_path' => $coverPath,
            'file_path' => $filePath,
            'file_format' => 'epub',
            'file_size' => strlen($epub),
        ]);

        $this->imported++;

        // A short pause between books: this pulls a few megabytes per title
        // from a volunteer-funded archive.
        usleep(400_000);
    }

    private function download(string $url): ?string
    {
        try {
            $response = Http::withUserAgent(self::AGENT)->timeout(120)->retry(2, 3000)->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        // An empty 200 is not a book. Written out it becomes a zero-byte EPUB
        // that lists, sells and downloads exactly like a real one, and only
        // fails in the reader — on someone else's screen.
        $body = $response->body();

        return $body === '' ? null : $body;
    }

    /**
     * Gutenberg lists several variants per type — with and without images,
     * zipped and not. Prefer the plain one, and never a zip.
     */
    private function pick(array $formats, string $type): ?string
    {
        $matches = [];

        foreach ($formats as $mime => $url) {
            if (str_starts_with($mime, $type) && ! str_ends_with($url, '.zip')) {
                $matches[] = $url;
            }
        }

        usort($matches, fn ($a, $b) => strlen($a) <=> strlen($b));

        return $matches[0] ?? null;
    }

    private function author(array $entry): Author
    {
        $name = $this->authorName($entry);

        return Author::firstOrCreate(
            ['name' => $name],
            ['bio' => "{$name}'s work is in the public domain."],
        );
    }

    /**
     * Gutenberg files authors as "Austen, Jane". Readers expect the other way
     * round, and it is what the cover says.
     */
    private function authorName(array $entry): string
    {
        $raw = trim((string) ($entry['authors'][0]['name'] ?? ''));

        if ($raw === '') {
            return 'Unknown';
        }

        if (! str_contains($raw, ',')) {
            return $raw;
        }

        [$family, $given] = array_pad(explode(',', $raw, 2), 2, '');

        return Str::of($given.' '.$family)->squish()->toString();
    }

    /**
     * Gutenberg's bookshelves read like shelf labels already ("Category:
     * British Literature"); its subjects read like library catalogue entries
     * ("Courtship -- Fiction"). Prefer a shelf, fall back to the first useful
     * word of a subject.
     */
    private function genre(array $entry): Genre
    {
        $name = null;

        foreach ($entry['bookshelves'] ?? [] as $shelf) {
            $clean = Str::of($shelf)->after('Category:')->squish()->toString();

            if ($clean !== '' && ! Str::contains($clean, 'Browsing:')) {
                $name = $clean;
                break;
            }
        }

        if (! $name) {
            $subject = (string) ($entry['subjects'][0] ?? '');
            $name = Str::of($subject)->before('--')->squish()->toString();
        }

        $name = $name !== '' ? Str::limit($name, 40, '') : 'Classics';

        return Genre::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        );
    }
}
