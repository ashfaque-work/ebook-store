<?php

namespace App\Support;

/**
 * The first few sentences of a book — if what you handed it is a book.
 *
 * Pulling an opening out of an EPUB is mostly a problem of refusal. A sample
 * starts wherever the publisher put the first real document, and for these
 * editions that is as often a contents list, a title page, a transcriber's
 * note or a hundred-year-old preface as it is chapter one. Every one of those
 * survives "take the first sixty words", and every one makes a terrible shop
 * window: the home page led with "CONTENTS TRANSLATOR'S PREFACE CRIME AND
 * PUNISHMENT PART I CHAPTER I" before this existed.
 *
 * So each candidate passage is asked whether it reads like somebody writing,
 * and the first one that says yes is the one used.
 */
class OpeningLines
{
    /** Phrases that only ever appear in matter wrapped around a book. */
    private const NOT_THE_BOOK = '/\b(transcriber|project gutenberg|gutenberg\.org|internet archive|'
        .'original publication date|copyright(ed)?\b|all rights reserved|illustrated edition|'
        .'page scan|google books|did not uncover any evidence|produced by|etext|'
        .'printed in|first published|list of illustrations|table of contents|contents)\b/iu';

    /** Below this there is not enough to judge, or to show. */
    private const MINIMUM_LENGTH = 120;

    /**
     * Whole sentences up to roughly the word budget, or null if this passage
     * is not prose.
     *
     * Cut mid-sentence and the window reads as broken rather than inviting,
     * so it overshoots to the end of the sentence it was in and stops there.
     */
    public static function from(string $text, int $budget = 58): ?string
    {
        // Left exactly as the author wrote it, opening quotation mark and
        // all. Stripping the quote to keep a drop cap happy turned
        // «"Gentleman Joe!" "Why, if it isn't old Jimmy McGrath."» into a
        // line that opens mid-speech and closes a quote it never opened.
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) < self::MINIMUM_LENGTH) {
            return null;
        }

        if (preg_match(self::NOT_THE_BOOK, mb_substr($text, 0, 400)) || ! self::readsAsProse($text)) {
            return null;
        }

        $kept = '';
        $count = 0;

        foreach (self::sentences($text) as $sentence) {
            $kept .= ($kept === '' ? '' : ' ').$sentence;
            $count += str_word_count($sentence);

            if ($count >= $budget) {
                break;
            }
        }

        return trim($kept) ?: null;
    }

    /**
     * Does this read like somebody writing, rather than a list?
     *
     * Three tells, all of them cheap. Prose runs mostly lowercase, because
     * only names and sentence openings are capitalised, and a title page or a
     * contents list does not. Prose builds long sentences, while a contents
     * list parses as dozens of two-word ones. And a contents list names its
     * chapters over and over, which nothing written for a reader does.
     */
    public static function readsAsProse(string $text): bool
    {
        $sample = mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 600);

        preg_match_all('/\p{L}[\p{L}\'’]*/u', $sample, $matches);
        $words = $matches[0] ?? [];

        if (count($words) < 25) {
            return false;
        }

        $lowercase = 0;

        foreach ($words as $word) {
            if (mb_strtolower($word) === $word) {
                $lowercase++;
            }
        }

        if ($lowercase / count($words) < 0.62) {
            return false;
        }

        // An average over the opening rather than a floor on each sentence:
        // "Call me Ishmael." is three words and is not the problem.
        $sentences = self::sentences($sample);
        $complete = array_slice($sentences, 0, max(1, count($sentences) - 1));

        if ($complete === [] || count($words) / count($complete) < 7) {
            return false;
        }

        return preg_match_all('/\b(chapter|book|letter|part|act|scene)\s+(the\s+)?([ivxlcdm]+|\d+)\b/iu', $sample) < 3;
    }

    /**
     * Sentence ends, keeping the terminator and any closing quote with it.
     *
     * Two fixed-width lookbehinds rather than one with an optional character:
     * PCRE will not compile a lookbehind that can match two different
     * lengths, but it accepts alternatives that are each fixed.
     *
     * @return list<string>
     */
    private static function sentences(string $text): array
    {
        return preg_split('/(?<=[.!?][”"\')\]])\s+|(?<=[.!?])\s+/u', $text) ?: [];
    }
}
