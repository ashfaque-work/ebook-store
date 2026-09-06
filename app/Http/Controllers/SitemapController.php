<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Response;

/**
 * Generated rather than stored, because the catalogue changes and a stale
 * sitemap is worse than none.
 *
 * Only published books appear. Sample pages are included deliberately: each
 * one is a real page of real prose on our own domain, which is the strongest
 * SEO asset a store this size has.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
        ];

        foreach (array_keys(LegalController::PAGES) as $page) {
            $urls[] = ['loc' => route('legal', $page), 'priority' => '0.3', 'changefreq' => 'yearly'];
        }

        foreach (Genre::orderBy('name')->get() as $genre) {
            $urls[] = [
                'loc' => route('home', ['genre' => $genre->slug]),
                'priority' => '0.6',
                'changefreq' => 'weekly',
            ];
        }

        Book::published()
            ->orderByDesc('published_at')
            ->chunk(200, function ($books) use (&$urls) {
                foreach ($books as $book) {
                    $urls[] = [
                        'loc' => route('books.show', $book),
                        'lastmod' => $book->updated_at?->toAtomString(),
                        'priority' => '0.8',
                        'changefreq' => 'weekly',
                    ];

                    if ($book->hasSample()) {
                        $urls[] = [
                            'loc' => route('reader.sample', $book),
                            'priority' => '0.7',
                            'changefreq' => 'monthly',
                        ];
                    }
                }
            });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
