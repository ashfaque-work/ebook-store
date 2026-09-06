<?php

namespace App\Models;

use App\Support\Money;
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

    /** Buyer in our own state: the tax splits into CGST and SGST. */
    public const TAX_CGST_SGST = 'cgst_sgst';

    /** Buyer in another state: a single IGST line. */
    public const TAX_IGST = 'igst';

    protected $fillable = [
        'user_id',
        'order_number',
        'idempotency_key',
        'invoice_number',
        'status',
        'total_paise',
        'subtotal_paise',
        'tax_paise',
        'currency',
        'tax_type',
        'buyer_state_code',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'failure_reason',
        'payment_reference',
        'paid_at',
        'refunded_at',
        'refunded_paise',
    ];

    protected function casts(): array
    {
        return [
            'total_paise' => 'integer',
            'subtotal_paise' => 'integer',
            'tax_paise' => 'integer',
            'refunded_paise' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** The captured payment a refund would be issued against. */
    public function capturedPayment(): ?Payment
    {
        return $this->payments()
            ->whereIn('status', [Payment::STATUS_CAPTURED, Payment::STATUS_PARTIALLY_REFUNDED])
            ->latest('id')
            ->first();
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function total(): Money
    {
        return Money::fromPaise($this->total_paise);
    }

    public function subtotal(): Money
    {
        return Money::fromPaise($this->subtotal_paise);
    }

    public function tax(): Money
    {
        return Money::fromPaise($this->tax_paise);
    }

    /** CGST and SGST are each half of the total tax on an intra-state sale. */
    public function halfTax(): Money
    {
        return Money::fromPaise((int) round($this->tax_paise / 2));
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
