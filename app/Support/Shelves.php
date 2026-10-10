<?php

namespace App\Support;

use App\Models\Genre;
use Illuminate\Support\Str;

/**
 * The shop's own shelves, and how a library's subject headings map onto them.
 *
 * Project Gutenberg files a book under shelves like "Banned Books from Anne
 * Haight's list" and subjects like "Courtship -- Fiction". Both are accurate
 * and neither is something a person browses by, so importing them verbatim
 * produced twelve shelves, three of which held one book each, with names that
 * read as a data dump on the storefront.
 *
 * Eight shelves, chosen so that every one of them is a thing somebody might
 * actually be in the mood for. Anything that matches nothing lands on
 * Classics rather than inventing a ninth shelf for one book — which is how
 * the first set grew.
 */
class Shelves
{
    /**
     * Shelf slug => name.
     */
    public const SHELVES = [
        'romance' => 'Romance',
        'gothic-and-horror' => 'Gothic & Horror',
        'mystery-and-detection' => 'Mystery & Detection',
        'classics' => 'Classics',
        'adventure-and-the-sea' => 'Adventure & the Sea',
        'myth-and-fable' => 'Myth & Fable',
        'lives-and-letters' => 'Lives & Letters',
        'plays-and-poetry' => 'Plays & Poetry',
    ];

    public const DEFAULT = 'classics';

    /**
     * Words that put a book on a shelf, most specific first.
     *
     * Order matters where a book could sit on two: a ghost story is horror
     * before it is a story, and a detective novel is detection before it is
     * an adventure.
     *
     * @var array<string, list<string>>
     */
    private const SIGNALS = [
        'gothic-and-horror' => [
            'horror', 'gothic', 'ghost', 'vampire', 'supernatural', 'monster', 'occult', 'terror',
        ],
        'mystery-and-detection' => [
            'detective', 'mystery', 'crime', 'thriller', 'murder', 'suspense', 'spy', 'burglar',
        ],
        'myth-and-fable' => [
            'myth', 'legend', 'arthur', 'fairy', 'folklore', 'epic', 'fable', 'mythology', 'grail',
        ],
        'plays-and-poetry' => [
            'poetry', 'poems', 'drama', 'plays', 'shakespeare', 'tragedies', 'comedies', 'verse', 'sonnet',
        ],
        'lives-and-letters' => [
            'biograph', 'autobiograph', 'memoir', 'letters', 'correspondence', 'essays', 'philosophy',
            'diaries', 'speeches',
        ],
        'adventure-and-the-sea' => [
            'adventure', 'sea stories', 'seafaring', 'voyage', 'whaling', 'pirate', 'western',
            'science fiction', 'exploration', 'war stories',
        ],
        'romance' => [
            'romance', 'love', 'courtship', 'marriage', 'domestic fiction', 'love stories',
        ],
    ];

    /**
     * The shelf for a book described by these subject and shelf strings.
     *
     * @param  list<string>  $descriptions
     */
    public static function slugFor(array $descriptions): string
    {
        $haystack = Str::lower(implode(' | ', array_filter($descriptions)));

        if ($haystack === '') {
            return self::DEFAULT;
        }

        foreach (self::SIGNALS as $slug => $words) {
            foreach ($words as $word) {
                if (str_contains($haystack, $word)) {
                    return $slug;
                }
            }
        }

        return self::DEFAULT;
    }

    /**
     * @param  list<string>  $descriptions
     */
    public static function for(array $descriptions): Genre
    {
        $slug = self::slugFor($descriptions);

        return Genre::firstOrCreate(['slug' => $slug], ['name' => self::SHELVES[$slug]]);
    }
}
