<?php

namespace App\Support\Epub;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;
use ZipArchive;

/**
 * An EPUB, opened.
 *
 * The container points at a package document; the package lists every file and
 * the order they are read in. Two features need that — cutting a sample and
 * indexing the text for search — and they need to agree about it, so it lives
 * in one place rather than being parsed twice slightly differently.
 */
class Package
{
    public const OPF_NS = 'http://www.idpf.org/2007/opf';

    public const CONTAINER_NS = 'urn:oasis:names:tc:opendocument:xmlns:container';

    private function __construct(
        public readonly ZipArchive $zip,
        public readonly string $opfPath,
        public readonly DOMDocument $opf,
        public readonly string $base,
        private readonly string $tempFile,
    ) {}

    /**
     * @return static|null null when the bytes are not a readable EPUB, which
     *                     is a thing that happens to real uploads
     */
    public static function open(string $bytes): ?self
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'epub');

        if ($temp === false) {
            return null;
        }

        try {
            file_put_contents($temp, $bytes);

            $zip = new ZipArchive;

            if ($zip->open($temp) !== true) {
                @unlink($temp);

                return null;
            }

            $opfPath = self::locateOpf($zip);
            $opf = $opfPath === null ? false : $zip->getFromName($opfPath);
            $document = $opf === false ? null : self::parseXml($opf);

            if ($opfPath === null || $document === null) {
                $zip->close();
                @unlink($temp);

                return null;
            }

            return new self($zip, $opfPath, $document, self::directoryOf($opfPath), $temp);
        } catch (Throwable) {
            @unlink($temp);

            return null;
        }
    }

    public function close(): void
    {
        try {
            $this->zip->close();
        } catch (Throwable) {
            // Already closed; nothing to salvage either way.
        }

        @unlink($this->tempFile);
    }

    /**
     * @return array<string, array{href: string, type: string, properties: string}>
     */
    public function manifest(): array
    {
        $xpath = new DOMXPath($this->opf);
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
     * Manifest ids in reading order.
     *
     * @return array<int, string>
     */
    public function spine(): array
    {
        $xpath = new DOMXPath($this->opf);
        $xpath->registerNamespace('o', self::OPF_NS);

        $order = [];

        foreach ($xpath->query('//o:spine/o:itemref') as $itemref) {
            /** @var DOMElement $itemref */
            $order[] = $itemref->getAttribute('idref');
        }

        return $order;
    }

    /**
     * The readable documents, in reading order.
     *
     * @return array<int, array{id: string, href: string, path: string}>
     */
    public function documents(): array
    {
        $manifest = $this->manifest();
        $documents = [];

        foreach ($this->spine() as $id) {
            $item = $manifest[$id] ?? null;

            if ($item === null || ! str_contains($item['type'], 'xhtml')) {
                continue;
            }

            $documents[] = [
                'id' => $id,
                'href' => $item['href'],
                'path' => $this->resolve($this->base, $item['href']),
            ];
        }

        return $documents;
    }

    public function read(string $path): ?string
    {
        $contents = $this->zip->getFromName($path);

        return $contents === false ? null : $contents;
    }

    public function resolve(string $base, string $href): string
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

    public static function directoryOf(string $path): string
    {
        $directory = dirname($path);

        return $directory === '.' ? '' : $directory;
    }

    public static function parseXml(string $xml): ?DOMDocument
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
     * chapter over a stray tag helps nobody.
     */
    public static function parseHtml(string $html): ?DOMDocument
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

    private static function locateOpf(ZipArchive $zip): ?string
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
}
