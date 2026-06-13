<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

    /**
     * The disk holding the paid ebook files. Private (storage/app/private),
     * so files are only ever served through the gated download route.
     */
    public const FILE_DISK = 'local';

    protected $fillable = [
        'author_id',
        'genre_id',
        'title',
        'slug',
        'description',
        'price',
        'cover_image_path',
        'file_path',
    ];

    /**
     * Never expose the private storage key of the ebook file to the frontend.
     *
     * @var list<string>
     */
    protected $hidden = [
        'file_path',
    ];

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

    /**
     * Build a URL-safe slug from a title that is unique across books.
     * Appends -2, -3, … on collision. Pass $ignoreId when updating a book so
     * it doesn't collide with its own existing slug.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($title);
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
     * This accessor modifies the original cover_image_path attribute.
     */
    protected function coverImagePath(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Storage::url($value) : null,
        );
    }
}
