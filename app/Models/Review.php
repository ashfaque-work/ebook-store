<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reader's opinion of one book.
 */
class Review extends Model
{
    use HasFactory;

    public const MIN_RATING = 1;

    public const MAX_RATING = 5;

    protected $fillable = [
        'book_id',
        'user_id',
        'rating',
        'title',
        'body',
        'verified',
        'percent_read',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'percent_read' => 'integer',
            'verified' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What a customer is allowed to see. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * The reviewer's name, or something usable when there is none.
     *
     * Only a first name and an initial reach the page: a review is public
     * forever, and nobody agrees to publish their full name by buying a book.
     */
    public function displayName(): string
    {
        $name = trim((string) ($this->user?->name ?? ''));

        if ($name === '') {
            return 'A reader';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = array_shift($parts) ?? '';
        $initial = $parts === [] ? '' : ' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';

        return $first.$initial;
    }
}
