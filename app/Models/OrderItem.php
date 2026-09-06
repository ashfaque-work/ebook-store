<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'book_id',
        'title',
        'price_paise',
        'subtotal_paise',
        'tax_paise',
        'tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'price_paise' => 'integer',
            'subtotal_paise' => 'integer',
            'tax_paise' => 'integer',
            'tax_rate' => 'float',
        ];
    }

    public function price(): Money
    {
        return Money::fromPaise($this->price_paise);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
