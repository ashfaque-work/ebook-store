<?php

namespace App\Support;

/**
 * Tidying for titles, author names and blurbs that arrived from a library
 * catalogue rather than from a publisher.
 *
 * Project Gutenberg's metadata is a MARC record with the serial numbers filed
 * off, and it shows on a shop page: subfield markers left in titles, authors
 * recorded surname-first with their forenames expanded in brackets, and a
 * summary that signs off by admitting a machine wrote it. None of that is
 * wrong in a library. All of it reads as unfinished in a shop.
 *
 * Every rule here is reversible by hand in the admin and deliberately timid:
 * it fixes what is unambiguous and leaves anything that needs a judgement for
 * a person. See `store:tidy-catalogue`, which reports what it will not touch.
 */
class CatalogueText
{
    /**
     * Words that stay lowercase inside a title.
     *
     * Short prepositions, articles and conjunctions only — the set every
     * style guide agrees on, so the result does not look opinionated.
     */
    private const MINOR_WORDS = [
        'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'into',
        'nor', 'of', 'on', 'onto', 'or', 'over', 'the', 'to', 'up', 'with',
    ];

    /**
     * The sentence the importer's summariser signs its work with.
     *
     * True, and not something a shopfront should say out loud on every book.
     */
    private const GENERATED_NOTE = '/\s*\(This is an automatically generated summary\.\)\s*$/u';

    public static function description(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $cleaned = preg_replace(self::GENERATED_NOTE, '', $description);

        return self::collapse($cleaned) ?: null;
    }

    /**
     * Clean a title without restyling one that is already fine.
     */
    public static function title(string $title): string
    {
        // MARC subfield markers. "Eloisa : $b or, A series of original
        // letters" is a record that was never meant to be read by a customer.
        $title = preg_replace('/\s*:\s*\$[a-z]\s*/u', ': ', $title);

        $title = self::collapse($title);

        return self::looksUnstyled($title) ? self::titleCase($title) : $title;
    }

    /**
     * Drop a catalogue's expansion of an abbreviated forename.
     *
     * "E. M. (Edward Morgan) Forster" is how a library disambiguates an
     * author. On a book jacket he is E. M. Forster.
     */
    public static function authorName(string $name): string
    {
        return self::collapse(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name));
    }

    /**
     * Does this title still read as a catalogue record after tidying?
     *
     * For the ones the restyling declines: a couple of lowercase words among
     * several proper nouns. Minor words are not evidence of anything — "A
     * Room with a View" is set exactly as its publisher set it, and listing
     * it as suspect trains people to ignore the list.
     */
    public static function titleNeedsAttention(string $title): bool
    {
        $words = array_slice(preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [], 1);

        foreach ($words as $word) {
            $bare = preg_replace('/[^\p{L}]/u', '', $word);

            if ($bare === '' || in_array(mb_strtolower($bare), self::MINOR_WORDS, true)) {
                continue;
            }

            if (mb_strlen($bare) > 3 && mb_strtolower($bare) === $bare) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this title in sentence case, as a catalogue record would store it?
     *
     * Looked at word by word: a title with capitals of its own ("The Strange
     * Case of Dr. Jekyll") is left exactly as the publisher set it, and only
     * one that has gone uniformly lowercase after the first word ("A farewell
     * to arms") is restyled. Initialisms, names and anything already
     * capitalised survive untouched either way.
     */
    private static function looksUnstyled(string $title): bool
    {
        $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) < 3) {
            return false;
        }

        $significant = 0;
        $lowercase = 0;

        foreach (array_slice($words, 1) as $word) {
            $bare = preg_replace('/[^\p{L}]/u', '', $word);

            if ($bare === '' || in_array(mb_strtolower($bare), self::MINOR_WORDS, true)) {
                continue;
            }

            $significant++;

            if (mb_strtolower($bare) === $bare) {
                $lowercase++;
            }
        }

        // Nearly all of them, not merely most: one stray lowercase word in an
        // otherwise styled title is a publisher's choice, not a data problem.
        return $significant >= 2 && $lowercase >= $significant - 1;
    }

    private static function titleCase(string $title): string
    {
        $words = preg_split('/(\s+)/u', $title, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $index = 0;
        $last = self::lastWordIndex($words);

        foreach ($words as $position => $word) {
            if (trim($word) === '') {
                continue;
            }

            $bare = mb_strtolower(preg_replace('/[^\p{L}]/u', '', $word));
            $minor = in_array($bare, self::MINOR_WORDS, true);

            // First and last words are always capitalised, however minor —
            // and so is anything after a colon or semicolon, because that is
            // a new title.
            $opensClause = $index === 0 || $position === $last || self::followsBreak($words, $position);

            $words[$position] = $minor && ! $opensClause ? mb_strtolower($word) : self::capitalise($word);

            $index++;
        }

        return implode('', $words);
    }

    private static function followsBreak(array $words, int $position): bool
    {
        for ($i = $position - 1; $i >= 0; $i--) {
            if (trim($words[$i]) === '') {
                continue;
            }

            return (bool) preg_match('/[:;.!?]$/u', $words[$i]);
        }

        return true;
    }

    private static function lastWordIndex(array $words): int
    {
        for ($i = count($words) - 1; $i >= 0; $i--) {
            if (trim($words[$i]) !== '') {
                return $i;
            }
        }

        return 0;
    }

    /**
     * Capitalise the first letter and leave the rest of the word alone.
     *
     * Not ucfirst on a lowercased word: that would turn McVeigh into Mcveigh
     * and ISBN into Isbn.
     */
    private static function capitalise(string $word): string
    {
        return preg_replace_callback('/\p{L}/u', fn ($m) => mb_strtoupper($m[0]), $word, 1);
    }

    private static function collapse(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
