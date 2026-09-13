<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;
use ZipArchive;

/**
 * Cut a readable sample out of an EPUB.
 *
 * The store is built around reading the opening of a book before buying it,
 * and a sample has to be a real EPUB — the same reader opens it.
 *
 * The unit is words, not files. How a publisher chunks a book is arbitrary:
 * Project Gutenberg puts all of Pride and Prejudice into five documents, so
 * "the first file" is either the licence header or a third of the novel. A
 * word budget gives the same size of taster whatever the packaging, which
 * means truncating inside a document when the budget runs out mid-chapter.
 *
 * Nothing beyond the budget is copied into the archive. A sample that quietly
 * carries the whole book inside it, reachable by anyone who unzips it, is
 * not a sample.
 */
class EpubSampler
{
    private const OPF_NS = 'http://www.idpf.org/2007/opf';

    private const CONTAINER_NS = 'urn:oasis:names:tc:opendocument:xmlns:container';

    private const XHTML_NS = 'http://www.w3.org/1999/xhtml';

    /**
     * Roughly twenty pages: enough to be past the front matter and into the
     * story, short enough that it is plainly a taster.
     */
    public const DEFAULT_BUDGET = 6000;

    /**
     * Project Gutenberg marks where its boilerplate ends and the book begins.
     * Six thousand words of licence text is not a sample of the novel — on the
     * first attempt at this, Pride and Prejudice's sample ran out of budget
     * before "It is a truth universally acknowledged".
     */
    private const TEXT_STARTS_AT = '*** START OF THE PROJECT GUTENBERG EBOOK';

    /**
     * Where a story actually begins.
     *
     * Gutenberg's first document is not a title page: for Pride and Prejudice
     * it holds the licence, the contents, the title, a five-thousand-word
     * critical preface, a list of illustrations, and then chapters I to X — all
     * in one file. Budgeting from the top of it produces a sample of the
     * preface. Anchoring on the first chapter heading produces a sample of the
     * novel.
     */
    private const CHAPTER_HEADING = '/\b(chapter|prologue|canto|part\s+(one|i)\b|book\s+(one|i)\b)/i';

    public static function make(string $epub, int $wordBudget = self::DEFAULT_BUDGET): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $source = tempnam(sys_get_temp_dir(), 'epub-in');
        $target = tempnam(sys_get_temp_dir(), 'epub-out');

        if ($source === false || $target === false) {
            return null;
        }

        try {
            file_put_contents($source, $epub);

            $zip = new ZipArchive;

            if ($zip->open($source) !== true) {
                return null;
            }

            $opfPath = self::opfPath($zip);

            if ($opfPath === null) {
                $zip->close();

                return null;
            }

            $opf = $zip->getFromName($opfPath);

            if ($opf === false) {
                $zip->close();

                return null;
            }

            $package = self::parseXml($opf);

            if ($package === null) {
                $zip->close();

                return null;
            }

            $base = self::directoryOf($opfPath);
            $manifest = self::manifest($package);
            $spine = self::spine($package);

            $kept = self::collectDocuments($zip, $base, $manifest, $spine, $wordBudget);

            if ($kept === []) {
                $zip->close();

                return null;
            }

            $assets = self::collectAssets($zip, $base, $manifest, $kept);
            $bytes = self::build($zip, $opfPath, $base, $package, $manifest, $kept, $assets, $target);

            $zip->close();

            return $bytes;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    private static function opfPath(ZipArchive $zip): ?string
    {
        $container = $zip->getFromName('META-INF/container.xml');

        if ($container === false) {
            return null;
        }

        $dom = self::parseXml($container);

        if ($dom === null) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('c', self::CONTAINER_NS);

        $rootfile = $xpath->query('//c:rootfiles/c:rootfile')->item(0);

        return $rootfile instanceof DOMElement ? $rootfile->getAttribute('full-path') : null;
    }

    /**
     * @return array<string, array{href: string, type: string, properties: string}>
     */
    private static function manifest(DOMDocument $package): array
    {
        $xpath = new DOMXPath($package);
        $xpath->registerNamespace('o', self::OPF_NS);

        $items = [];

        foreach ($xpath->query('//o:manifest/o:item') as $item) {
            /** @var DOMElement $item */
            $items[$item->getAttribute('id')] = [
                'href' => $item->getAttribute('href'),
                'type' => $item->getAttribute('media-type'),
                'properties' => $item->getAttribute('properties'),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private static function spine(DOMDocument $package): array
    {
        $xpath = new DOMXPath($package);
        $xpath->registerNamespace('o', self::OPF_NS);

        $order = [];

        foreach ($xpath->query('//o:spine/o:itemref') as $itemref) {
            /** @var DOMElement $itemref */
            $order[] = $itemref->getAttribute('idref');
        }

        return $order;
    }

    /**
     * Walk the spine until the word budget runs out, truncating the document
     * that crosses it.
     *
     * @param  array<string, array{href: string, type: string, properties: string}>  $manifest
     * @param  array<int, string>  $spine
     * @return array<int, array{id: string, href: string, path: string, html: string}>
     */
    private static function collectDocuments(
        ZipArchive $zip,
        string $base,
        array $manifest,
        array $spine,
        int $budget,
    ): array {
        $kept = [];
        $spent = 0;

        foreach ($spine as $id) {
            if ($spent >= $budget) {
                break;
            }

            $item = $manifest[$id] ?? null;

            if ($item === null || ! str_contains($item['type'], 'xhtml')) {
                continue;
            }

            $path = self::resolve($base, $item['href']);
            $html = $zip->getFromName($path);

            if ($html === false) {
                continue;
            }

            // Front matter is skipped rather than merely not counted: leaving
            // it in would open the sample on a licence and a preface.
            $html = self::skipToStory($html);
            $words = self::wordCount($html);
            $remaining = $budget - $spent;

            if ($words > $remaining) {
                $html = self::truncate($html, $remaining);
                $spent = $budget;
            } else {
                $spent += $words;
            }

            $kept[] = ['id' => $id, 'href' => $item['href'], 'path' => $path, 'html' => $html];
        }

        return $kept;
    }

    /**
     * Drop everything before the story starts.
     *
     * Two anchors, in order of how much they prove. A chapter heading is the
     * strongest: whatever precedes it is front matter by definition. Failing
     * that, Gutenberg marks where its own boilerplate ends. Failing both, the
     * document is returned untouched — guessing would sometimes cut off the
     * first chapter, and a sample that starts slightly early is a far smaller
     * fault than one missing its opening.
     */
    private static function skipToStory(string $html): string
    {
        $dom = self::parseHtml($html);

        if ($dom === null) {
            return $html;
        }

        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return $html;
        }

        $children = array_values(array_filter(
            iterator_to_array($body->childNodes),
            fn ($node) => $node->nodeType === XML_ELEMENT_NODE,
        ));

        $start = self::indexOfFirstChapter($children) ?? self::indexAfterMarker($children);

        if ($start === null || $start <= 0) {
            return $html;
        }

        foreach (array_slice($children, 0, $start) as $node) {
            $node->parentNode?->removeChild($node);
        }

        return $dom->saveXML() ?: $html;
    }

    /**
     * @param  array<int, \DOMNode>  $children
     */
    private static function indexOfFirstChapter(array $children): ?int
    {
        foreach ($children as $index => $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $heading = self::headingWithin($node);

            if ($heading === null || ! preg_match(self::CHAPTER_HEADING, $heading)) {
                continue;
            }

            // A contents list says "Chapter" too, and is the thing most worth
            // skipping past. A real chapter opening is a short block — a
            // heading, perhaps an illustration caption above it. A list of
            // sixty chapter numbers is not.
            if (str_word_count($node->textContent) > 50) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * A heading's own text, or one nested a level down — Gutenberg wraps
     * chapter headings inside a block with the illustration above them.
     */
    private static function headingWithin(DOMElement $node): ?string
    {
        if (in_array(strtolower($node->nodeName), ['h1', 'h2', 'h3'], true)) {
            return trim($node->textContent);
        }

        foreach (['h1', 'h2', 'h3'] as $tag) {
            $found = $node->getElementsByTagName($tag)->item(0);

            if ($found !== null && trim($found->textContent) !== '') {
                return trim($found->textContent);
            }
        }

        return null;
    }

    /**
     * @param  array<int, \DOMNode>  $children
     */
    private static function indexAfterMarker(array $children): ?int
    {
        foreach ($children as $index => $node) {
            if (str_contains($node->textContent, self::TEXT_STARTS_AT)) {
                return $index + 1;
            }
        }

        return null;
    }

    private static function wordCount(string $html): int
    {
        return str_word_count(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Keep whole top-level blocks until the budget is met, then drop the rest.
     *
     * Block by block rather than word by word: cutting a paragraph in half
     * reads like a corrupt file, while stopping between paragraphs reads like
     * the end of an extract.
     */
    private static function truncate(string $html, int $budget): string
    {
        $dom = self::parseHtml($html);

        if ($dom === null) {
            return $html;
        }

        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return $html;
        }

        $spent = 0;
        $dropping = false;
        $doomed = [];

        foreach (iterator_to_array($body->childNodes) as $node) {
            if ($dropping) {
                $doomed[] = $node;

                continue;
            }

            $spent += str_word_count(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($spent >= $budget) {
                $dropping = true;
            }
        }

        foreach ($doomed as $node) {
            $node->parentNode?->removeChild($node);
        }

        return $dom->saveXML() ?: $html;
    }

    /**
     * Stylesheets, fonts and the images the kept pages actually reference.
     *
     * Referenced only: a Gutenberg EPUB can carry twenty megabytes of plates,
     * and a sample that drags all of them along is neither a sample nor a
     * quick download.
     *
     * @param  array<string, array{href: string, type: string, properties: string}>  $manifest
     * @param  array<int, array{id: string, href: string, path: string, html: string}>  $kept
     * @return array<string, string> manifest id => path in the archive
     */
    private static function collectAssets(ZipArchive $zip, string $base, array $manifest, array $kept): array
    {
        $referenced = [];

        foreach ($kept as $document) {
            $dom = self::parseHtml($document['html']);

            if ($dom === null) {
                continue;
            }

            foreach (['img' => 'src', 'image' => 'href', 'link' => 'href'] as $tag => $attribute) {
                foreach ($dom->getElementsByTagName($tag) as $element) {
                    $value = $element->getAttribute($attribute) ?: $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href');

                    if ($value !== '' && ! str_contains($value, '://')) {
                        $referenced[self::resolve(self::directoryOf($document['path']), $value)] = true;
                    }
                }
            }
        }

        $assets = [];

        foreach ($manifest as $id => $item) {
            $path = self::resolve($base, $item['href']);
            $isStyle = str_contains($item['type'], 'css') || str_contains($item['type'], 'font');

            if (($isStyle || isset($referenced[$path])) && $zip->locateName($path) !== false) {
                $assets[$id] = $path;
            }
        }

        return $assets;
    }

    /**
     * @param  array<string, array{href: string, type: string, properties: string}>  $manifest
     * @param  array<int, array{id: string, href: string, path: string, html: string}>  $kept
     * @param  array<string, string>  $assets
     */
    private static function build(
        ZipArchive $source,
        string $opfPath,
        string $base,
        DOMDocument $package,
        array $manifest,
        array $kept,
        array $assets,
        string $target,
    ): ?string {
        $out = new ZipArchive;

        if ($out->open($target, ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        // The spec wants mimetype first and uncompressed. Readers are lenient
        // about it; validators are not, and it costs one line.
        $out->addFromString('mimetype', 'application/epub+zip');
        $out->setCompressionName('mimetype', ZipArchive::CM_STORE);

        $container = $source->getFromName('META-INF/container.xml');
        $out->addFromString('META-INF/container.xml', $container !== false ? $container : self::containerXml($opfPath));

        foreach ($kept as $document) {
            $out->addFromString($document['path'], $document['html']);
        }

        foreach ($assets as $path) {
            $bytes = $source->getFromName($path);

            if ($bytes !== false) {
                $out->addFromString($path, $bytes);
            }
        }

        $navPath = self::resolve($base, 'sample-nav.xhtml');
        $out->addFromString($navPath, self::navDocument($kept, $base));
        $out->addFromString($opfPath, self::rewritePackage($package, $manifest, $kept, $assets));

        $out->close();

        $bytes = file_get_contents($target);

        return $bytes === false ? null : $bytes;
    }

    /**
     * @param  array<string, array{href: string, type: string, properties: string}>  $manifest
     * @param  array<int, array{id: string, href: string, path: string, html: string}>  $kept
     * @param  array<string, string>  $assets
     */
    private static function rewritePackage(DOMDocument $package, array $manifest, array $kept, array $assets): string
    {
        $dom = clone $package;
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('o', self::OPF_NS);

        $keptIds = array_column($kept, 'id');
        $allowed = array_flip(array_merge($keptIds, array_keys($assets)));

        // Anything not carried over has to leave the manifest: an item
        // pointing at a file that is not in the archive is a broken book.
        foreach (iterator_to_array($xpath->query('//o:manifest/o:item')) as $item) {
            /** @var DOMElement $item */
            if (! isset($allowed[$item->getAttribute('id')])) {
                $item->parentNode?->removeChild($item);
            }
        }

        $manifestNode = $xpath->query('//o:manifest')->item(0);

        if ($manifestNode instanceof DOMElement) {
            $nav = $dom->createElementNS(self::OPF_NS, 'item');
            $nav->setAttribute('id', 'sample-nav');
            $nav->setAttribute('href', 'sample-nav.xhtml');
            $nav->setAttribute('media-type', 'application/xhtml+xml');
            $nav->setAttribute('properties', 'nav');
            $manifestNode->appendChild($nav);
        }

        foreach (iterator_to_array($xpath->query('//o:spine/o:itemref')) as $itemref) {
            /** @var DOMElement $itemref */
            if (! in_array($itemref->getAttribute('idref'), $keptIds, true)) {
                $itemref->parentNode?->removeChild($itemref);
            }
        }

        // The NCX is gone with the rest of the manifest, so the spine must
        // stop pointing at it.
        $spineNode = $xpath->query('//o:spine')->item(0);

        if ($spineNode instanceof DOMElement) {
            $spineNode->removeAttribute('toc');
        }

        return $dom->saveXML() ?: '';
    }

    /**
     * @param  array<int, array{id: string, href: string, path: string, html: string}>  $kept
     */
    private static function navDocument(array $kept, string $base): string
    {
        $items = '';

        foreach ($kept as $document) {
            $href = htmlspecialchars(self::relativeTo($base, $document['path']), ENT_QUOTES);
            $label = htmlspecialchars(self::titleOf($document['html']), ENT_QUOTES);
            $items .= "      <li><a href=\"{$href}\">{$label}</a></li>\n";
        }

        return <<<XHTML
        <?xml version="1.0" encoding="utf-8"?>
        <html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
          <head><title>Contents</title></head>
          <body>
            <nav epub:type="toc" id="toc">
              <h1>Contents</h1>
              <ol>
        {$items}      </ol>
            </nav>
          </body>
        </html>
        XHTML;
    }

    private static function titleOf(string $html): string
    {
        $dom = self::parseHtml($html);

        if ($dom === null) {
            return 'Section';
        }

        foreach (['h1', 'h2', 'h3', 'title'] as $tag) {
            $node = $dom->getElementsByTagName($tag)->item(0);

            if ($node && trim($node->textContent) !== '') {
                return mb_substr(trim(preg_replace('/\s+/u', ' ', $node->textContent)), 0, 80);
            }
        }

        return 'Section';
    }

    private static function containerXml(string $opfPath): string
    {
        $path = htmlspecialchars($opfPath, ENT_QUOTES);

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<container xmlns="'.self::CONTAINER_NS.'" version="1.0"><rootfiles>'
            ."<rootfile full-path=\"{$path}\" media-type=\"application/oebps-package+xml\"/>"
            .'</rootfiles></container>';
    }

    private static function parseXml(string $xml): ?DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $dom : null;
    }

    /**
     * XHTML first, since that is what an EPUB holds; HTML as a fallback,
     * because plenty of real books are not quite well-formed and losing the
     * sample over a stray tag helps nobody.
     */
    private static function parseHtml(string $html): ?DOMDocument
    {
        $dom = self::parseXml($html);

        if ($dom !== null) {
            return $dom;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $dom : null;
    }

    private static function directoryOf(string $path): string
    {
        $directory = dirname($path);

        return $directory === '.' ? '' : $directory;
    }

    private static function resolve(string $base, string $href): string
    {
        $href = explode('#', $href)[0];
        $path = $base === '' ? $href : $base.'/'.$href;

        // Flatten ../ so the result matches the name held in the archive.
        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '.' && $segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }

    private static function relativeTo(string $base, string $path): string
    {
        return $base !== '' && str_starts_with($path, $base.'/')
            ? substr($path, strlen($base) + 1)
            : $path;
    }
}
