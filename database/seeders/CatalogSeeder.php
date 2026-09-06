<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A catalogue worth looking at. A fresh clone previously landed on an empty
 * store, which makes the whole app impossible to evaluate or demo.
 */
class CatalogSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const CATALOG = [
        'Literary Fiction' => [
            ['The Unquiet House', 'Anita Rau'],
            ['Salt and Monsoon', 'Anita Rau'],
            ['A Map of Small Rooms', 'Devika Menon'],
            ['The Cartographer of Silences', 'Yusuf Ali Khan'],
            ['Everything the River Kept', 'Devika Menon'],
        ],
        'Crime & Mystery' => [
            ['The Bandra Confession', 'Farhan Qureshi'],
            ['Nine Hours to Dhanbad', 'Farhan Qureshi'],
            ['The Last Tenant of Flat 4B', 'Rhea Sequeira'],
            ['Cold Case, Warm Rain', 'Rhea Sequeira'],
        ],
        'Science Fiction' => [
            ['Orbital Monsoon', 'Kabir Sen'],
            ['The Ganges Protocol', 'Kabir Sen'],
            ['Signals from a Quiet Star', 'Meera Iyengar'],
        ],
        'History' => [
            ['The Long Road to Bombay', 'Prof. S. Balachandran'],
            ['Empire of Paper', 'Prof. S. Balachandran'],
            ['Before the Railways', 'Ananya Bhattacharya'],
        ],
        'Technology' => [
            ['Systems That Fit in Your Head', 'Rohan D\'Souza'],
            ['The Pragmatic Deployment', 'Rohan D\'Souza'],
            ['Reading Code Like Prose', 'Nikhil Varma'],
        ],
        'Poetry' => [
            ['Kitchen Light', 'Zoya Ahmed'],
            ['Thirty-One Monsoons', 'Zoya Ahmed'],
        ],
        'Biography' => [
            ['The Woman Who Counted Stars', 'Ananya Bhattacharya'],
            ['A Life in Six Cities', 'Yusuf Ali Khan'],
        ],
        'Business' => [
            ['Small Shop, Long Game', 'Nikhil Varma'],
            ['The Patient Founder', 'Meera Iyengar'],
        ],
    ];

    public function run(): void
    {
        // A placeholder ebook so downloads and (later) the reader have
        // something real to serve in development.
        $samplePath = 'books/sample.pdf';

        if (! Storage::disk(Book::FILE_DISK)->exists($samplePath)) {
            Storage::disk(Book::FILE_DISK)->put($samplePath, $this->placeholderPdf());
        }

        foreach (self::CATALOG as $genreName => $titles) {
            $genre = Genre::firstOrCreate(
                ['slug' => Str::slug($genreName)],
                ['name' => $genreName],
            );

            foreach ($titles as [$title, $authorName]) {
                $author = Author::firstOrCreate(
                    ['name' => $authorName],
                    ['bio' => "{$authorName} writes from India. This is placeholder biography copy for local development."],
                );

                Book::firstOrCreate(
                    ['slug' => Str::slug($title)],
                    [
                        'title' => $title,
                        'author_id' => $author->id,
                        'genre_id' => $genre->id,
                        'description' => $this->blurb($title),
                        'language' => 'en',
                        'page_count' => random_int(148, 512),
                        // Realistic Indian ebook pricing, in paise.
                        'price_paise' => collect([9900, 14900, 19900, 24900, 29900, 34900, 39900, 49900])->random(),
                        'tax_rate' => 18.00,
                        'is_published' => true,
                        'published_at' => now()->subDays(random_int(0, 400)),
                        'cover_image_path' => null,
                        'file_path' => $samplePath,
                        'file_format' => 'pdf',
                        'file_size' => Storage::disk(Book::FILE_DISK)->size($samplePath),
                    ],
                );
            }
        }

        // One draft, so the publish/draft split is visible in the admin panel.
        Book::where('slug', Str::slug('The Patient Founder'))->update([
            'is_published' => false,
            'published_at' => null,
        ]);

        // One free book, so the "Free" price path is exercised.
        Book::where('slug', Str::slug('Kitchen Light'))->update(['price_paise' => 0]);
    }

    private function blurb(string $title): string
    {
        return "{$title} is placeholder catalogue copy for local development. "
            .'It stands in for a real blurb so the book page, search results and '
            .'shelves can be judged at a realistic length rather than against '
            .'a single line of lorem ipsum.';
    }

    /**
     * The smallest valid single-page PDF, so seeded downloads open.
     */
    private function placeholderPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n"
            .'%%EOF';
    }
}
