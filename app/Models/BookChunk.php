<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A searchable passage of a book.
 *
 * Deliberately not mass-assignable from anything a request touches: these are
 * written by the indexer and read by search, never edited.
 */
class BookChunk extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'book_id',
        'position',
        'section',
        'heading',
        'content',
        'word_count',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'word_count' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
