<?php

namespace App\Support;

use App\Models\Book;
use Illuminate\Support\Str;

/**
 * Page metadata for search engines and, more importantly here, for link
 * previews.
 *
 * This audience arrives from WhatsApp and Instagram. A book link pasted into a
 * group chat currently renders as a bare URL with no image, title or price;
 * fixing that is worth more than any ranking change.
 */
final class Meta
{
    /**
     * @param  array<string, mixed>  $extra
     */
    private function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly ?string $image = null,
        public readonly string $type = 'website',
        public readonly bool $noindex = false,
        public readonly array $extra = [],
    ) {}

    public static function make(
        string $title,
        string $description,
        ?string $canonical = null,
        ?string $image = null,
        string $type = 'website',
        bool $noindex = false,
    ): self {
        return new self(
            title: $title,
            description: self::trim($description),
            canonical: $canonical ?? url()->current(),
            image: $image,
            type: $type,
            noindex: $noindex,
        );
    }

    /**
     * A book, with the structured data that earns a rich result.
     */
    public static function forBook(Book $book): self
    {
        $meta = self::make(
            title: $book->title.' by '.($book->author?->name ?? 'Unknown'),
            description: (string) $book->description,
            canonical: route('books.show', $book),
            image: $book->cover_image_path,
            type: 'book',
            noindex: ! $book->is_published,
        );

        return new self(
            $meta->title, $meta->description, $meta->canonical, $meta->image,
            $meta->type, $meta->noindex,
            extra: ['jsonLd' => self::bookJsonLd($book)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function bookJsonLd(Book $book): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            'name' => $book->title,
            'author' => $book->author ? [
                '@type' => 'Person',
                'name' => $book->author->name,
            ] : null,
            'bookFormat' => 'https://schema.org/EBook',
            'isbn' => $book->isbn,
            'numberOfPages' => $book->page_count,
            'inLanguage' => $book->language,
            'description' => self::trim((string) $book->description),
            'image' => $book->cover_image_path,
            'url' => route('books.show', $book),
            // aggregateRating is deliberately absent until reviews exist:
            // fabricated ratings are a manual-action risk.
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($book->price_paise / 100, 2, '.', ''),
                'priceCurrency' => config('store.currency'),
                'availability' => $book->is_published
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => route('books.show', $book),
            ],
        ], fn ($value) => $value !== null);
    }

    /** Around 155 characters is what search results and previews show. */
    private static function trim(string $description): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 155);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'image' => $this->image,
            'type' => $this->type,
            'noindex' => $this->noindex,
        ], $this->extra);
    }
}
