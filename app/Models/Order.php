<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'order_number',
        'idempotency_key',
        'status',
        'total',
        'payment_reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * A stable fingerprint of "this buyer, buying exactly these books".
     *
     * Two rapid submissions of the same cart produce the same key, and the
     * unique index on the column stops the second one becoming a second order.
     *
     * @param  Collection<int, Book>  $books
     */
    public static function idempotencyKeyFor(User $user, Collection $books): string
    {
        return hash('sha256', $user->id.'|'.$books->pluck('id')->sort()->implode(','));
    }
}
