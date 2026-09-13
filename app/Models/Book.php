<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Book extends Model
{
    use HasFactory;

    /**
     * The disk holding the paid ebook files — private, so they are only ever
     * served through an ownership-checked route.
     *
     * Configured rather than hardcoded: free hosting has an ephemeral disk, so
     * production points this at object storage and uploads survive a redeploy.
     */
    public static function fileDisk(): string
    {
        return config('store.disks.private', 'local');
    }

    /**
     * The disk holding cover images. Public — covers are marketing assets.
     *
     * Named explicitly because `Storage::url()` resolves against the *default*
     * disk, which is the private one. Always go through this.
     */
    public static function coverDisk(): string
    {
        return config('store.disks.public', 'public');
    }

    protected $fillable = [
        'author_id',
        'genre_id',
        'title',
        'slug',
        'description',
        'excerpt',
        'language',
        'isbn',
        'page_count',
        'price',
        'price_paise',
        'tax_rate',
        'is_published',
        'published_at',
        'is_featured',
        'cover_image_path',
        'file_path',
        'file_format',
        'file_size',
        'sample_path',
    ];

    /**
     * Never expose the private storage keys of the ebook files to the frontend.
     *
     * @var list<string>
     */
    protected $hidden = [
        'file_path',
        'sample_path',
    ];

    protected function casts(): array
    {
        return [
            'price_paise' => 'integer',
            'tax_rate' => 'float',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'page_count' => 'integer',
            'file_size' => 'integer',
        ];
    }

    /**
     * Price as a Money object, and settable from a rupee amount.
     *
     * `price_paise` is the storage and wire format; `price` is the domain API
     * so PHP never juggles a float. Admin forms submit rupees and are
     * converted here, once.
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => Money::fromPaise((int) ($attributes['price_paise'] ?? 0)),
            set: fn ($value) => [
                'price_paise' => $value instanceof Money ? $value->paise : Money::fromRupees($value)->paise,
            ],
        );
    }

    /** Is there a free preview to read without buying? */
    public function hasSample(): bool
    {
        return (bool) $this->getRawOriginal('sample_path');
    }

    public function readingProgress(): HasMany
    {
        return $this->hasMany(ReadingProgress::class);
    }

    public function isFree(): bool
    {
        return $this->price_paise === 0;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    /**
     * The book's own text, in searchable passages. Written by the indexer,
     * rebuilt whenever the file changes.
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(BookChunk::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Only books that are live in the catalogue. Every public-facing query
     * must go through this scope.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Has this book ever been bought? Sold books can be unpublished but never
     * deleted — removing one would strand everyone who paid for it.
     */
    public function hasBeenPurchased(): bool
    {
        return $this->orderItems()->exists();
    }

    /**
     * Build a URL-safe slug from a title that is unique across books.
     * Appends -2, -3, … on collision. Pass $ignoreId when updating a book so
     * it doesn't collide with its own existing slug.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Get the full public URL for the book's cover image.
     * This accessor rewrites the stored key into a URL on the cover disk.
     */
    protected function coverImagePath(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Storage::disk(self::coverDisk())->url($value) : null,
        );
    }
}
