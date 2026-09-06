<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per delivered ebook download. Without this there is no way to tell a
 * shared account from a busy reader, and no evidence behind a refund decision.
 */
class DownloadLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'book_id',
        'ip',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
