<?php

namespace App\Support;

use Throwable;
use ZipArchive;

/**
 * How long is this book, really?
 *
 * The reader tells someone how much of their evening is left, and that number
 * comes from a page count. An EPUB has no pages — it reflows — so the honest
 * equivalent is its word count, divided by how much text fits on a printed
 * page. Without it the estimate simply never appears, which is the one outcome
 * worth avoiding: a reader who cannot tell whether this is twenty minutes or
 * twenty hours.
 */
class EpubStats
{
    /** Words to a printed page, near enough for a mass-market paperback. */
    private const WORDS_PER_PAGE = 300;

    /**
     * Counting every word of a long novel costs more than the answer is worth,
     * so this stops early and scales up from what it read.
     */
    private const MAX_DOCUMENTS = 60;

    public static function pageCount(string $epub): ?int
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'epub');

        if ($temp === false) {
            return null;
        }

        try {
            file_put_contents($temp, $epub);

            $zip = new ZipArchive;

            if ($zip->open($temp) !== true) {
                return null;
            }

            $documents = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if ($name !== false && preg_match('/\.(x?html?|xht)$/i', $name)) {
                    $documents[] = $name;
                }
            }

            if ($documents === []) {
                $zip->close();

                return null;
            }

            // Front matter and licence pages cluster at the ends and are not
            // representative, so sample across the whole book rather than
            // taking the first N files.
            $total = count($documents);
            $step = max(1, (int) ceil($total / self::MAX_DOCUMENTS));
            $words = 0;
            $read = 0;

            for ($i = 0; $i < $total; $i += $step) {
                $html = $zip->getFromName($documents[$i]);

                if ($html === false) {
                    continue;
                }

                $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $words += str_word_count($text);
                $read++;
            }

            $zip->close();

            if ($read === 0) {
                return null;
            }

            $estimated = (int) round(($words / $read) * $total);
            $pages = (int) ceil($estimated / self::WORDS_PER_PAGE);

            // A book of nothing is a parsing failure, not a short book.
            return $pages > 0 ? $pages : null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($temp);
        }
    }
}
