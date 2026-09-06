<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingProgress extends Model
{
    protected $table = 'reading_progress';

    protected $fillable = ['user_id', 'book_id', 'location', 'percent', 'last_read_at'];

    protected function casts(): array
    {
        return ['percent' => 'integer', 'last_read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function isFinished(): bool
    {
        return $this->percent >= 98;
    }
}
