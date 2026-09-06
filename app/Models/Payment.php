<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt against the gateway. An order may have several — a declined
 * card, then a UPI payment that works — so this is the audit trail, separate
 * from the order's own status.
 */
class Payment extends Model
{
    public const STATUS_CAPTURED = 'captured';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $fillable = [
        'order_id',
        'gateway',
        'gateway_payment_id',
        'status',
        'amount_paise',
        'refunded_paise',
        'method',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'refunded_paise' => 'integer',
            'payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function amount(): Money
    {
        return Money::fromPaise($this->amount_paise);
    }

    public function refundablePaise(): int
    {
        return max(0, $this->amount_paise - $this->refunded_paise);
    }
}
