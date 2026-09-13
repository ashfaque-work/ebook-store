<?php

namespace App\Support\Epub;

use DOMElement;
use DOMNode;

/**
 * Break a book into passages worth searching.
 *
 * A passage, not a page and not a chapter. A chapter is too big to be an
 * answer — "it is somewhere in these nine thousand words" helps nobody — and a
 * sentence is too small to make sense out of context. A couple of hundred
 * words is about a paragraph or three: enough to recognise the scene, short
 * enough to read in the results.
 *
 * Each passage remembers which document it came from and the heading it sat
 * under, so a result can say "Chapter XI" and open the reader there.
 */
class TextExtractor
{
    public const DEFAULT_WORDS = 180;

    /**
     * Gutenberg's licence wraps every book it distributes. It is identical
     * across the catalogue, so indexing it would make every search for a
     * common legal word return all fifty-four books at once.
     */
    private const BOILERPLATE_IDS = ['pg-header', 'pg-footer', 'pg-machine-header'];

    private const BOILERPLATE_CLASSES = ['pg-boilerplate', 'pgheader'];

    private const HEADINGS = ['h1', 'h2', 'h3', 'h4'];

    /**
     * Elements that contain other blocks rather than prose.
     *
     * The distinction matters more than it looks. Recursing into any element
     * with element children descends into a drop-cap span and takes only the
     * letter inside it, silently dropping the rest of the paragraph — which is
     * how "IT is a truth universally acknowledged" went missing from the index
     * while the chapter around it stayed.
     */
    private const BLOCK_TAGS = [
        'div', 'section', 'article', 'main', 'aside', 'blockquote',
        'ul', 'ol', 'li', 'table', 'tbody', 'thead', 'tr', 'td', 'th',
        'figure', 'figcaption', 'header', 'footer', 'nav', 'p', 'pre',
    ];

    /**
     * @return array<int, array{section: string, heading: string|null, content: string, words: int}>
     */
    public static function passages(string $epub, int $wordsPerPassage = self::DEFAULT_WORDS): array
    {
        $package = Package::open($epub);

        if ($package === null) {
            return [];
        }

        try {
            $passages = [];

            foreach ($package->documents() as $document) {
                $html = $package->read($document['path']);

                if ($html === null) {
                    continue;
                }

                foreach (self::fromDocument($html, $document['href'], $wordsPerPassage) as $passage) {
                    $passages[] = $passage;
                }
            }

            return $passages;
        } finally {
            $package->close();
        }
    }

    /**
     * @return array<int, array{section: string, heading: string|null, content: string, words: int}>
     */
    private static function fromDocument(string $html, string $href, int $wordsPerPassage): array
    {
        $dom = Package::parseHtml($html);

        if ($dom === null) {
            return [];
        }

        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return [];
        }

        $passages = [];
        $heading = null;
        $buffer = '';
        $words = 0;

        $flush = function () use (&$passages, &$buffer, &$words, $href, &$heading) {
            $text = trim($buffer);

            if ($text !== '') {
                $passages[] = [
                    'section' => $href,
                    'heading' => $heading,
                    'content' => $text,
                    'words' => $words,
                ];
            }

            $buffer = '';
            $words = 0;
        };

        foreach (self::blocks($body) as $block) {
            $text = self::normalise($block->textContent);

            if ($text === '') {
                continue;
            }

            if (in_array(strtolower($block->nodeName), self::HEADINGS, true)) {
                // A heading ends the passage before it: the next paragraph
                // belongs to a new section, and a passage that straddles the
                // boundary is filed under the wrong chapter.
                $flush();
                $heading = mb_substr($text, 0, 120);

                continue;
            }

            $buffer = $buffer === '' ? $text : $buffer.' '.$text;
            $words += str_word_count($text);

            if ($words >= $wordsPerPassage) {
                $flush();
            }
        }

        $flush();

        return $passages;
    }

    /**
     * Leaf-ish block elements in reading order, skipping boilerplate.
     *
     * Recursing until a node has no block children keeps paragraphs whole
     * while still descending through the wrapper divs that real books are full
     * of.
     *
     * @return array<int, DOMElement>
     */
    private static function blocks(DOMElement $node): array
    {
        $blocks = [];

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (self::isBoilerplate($child)) {
                continue;
            }

            $name = strtolower($child->nodeName);

            if (in_array($name, self::HEADINGS, true)) {
                $blocks[] = $child;

                continue;
            }

            // Only descend past something that holds other blocks. Anything
            // else is prose, and its inline markup is part of the sentence.
            if (self::holdsBlocks($child)) {
                foreach (self::blocks($child) as $block) {
                    $blocks[] = $block;
                }

                continue;
            }

            if (self::normalise($child->textContent) !== '') {
                $blocks[] = $child;
            }
        }

        return $blocks;
    }

    private static function holdsBlocks(DOMElement $node): bool
    {
        if (! in_array(strtolower($node->nodeName), self::BLOCK_TAGS, true)) {
            return false;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement
                && (in_array(strtolower($child->nodeName), self::BLOCK_TAGS, true)
                    || in_array(strtolower($child->nodeName), self::HEADINGS, true))) {
                return true;
            }
        }

        return false;
    }

    private static function isBoilerplate(DOMNode $node): bool
    {
        if (! $node instanceof DOMElement) {
            return false;
        }

        if (in_array($node->getAttribute('id'), self::BOILERPLATE_IDS, true)) {
            return true;
        }

        $classes = preg_split('/\s+/', $node->getAttribute('class')) ?: [];

        return array_intersect($classes, self::BOILERPLATE_CLASSES) !== [];
    }

    private static function normalise(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
