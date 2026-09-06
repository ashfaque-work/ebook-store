<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * A gateway webhook we have seen.
 *
 * The row is written *before* the event is acted on. Gateways retry, and
 * Razorpay will keep retrying for hours if it does not get a 200, so the same
 * event arriving twice is normal rather than exceptional. The unique index on
 * event_id is what makes the second delivery a no-op.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'gateway',
        'event_id',
        'event_type',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Claim an event for processing.
     *
     * Returns null if we have already recorded it — meaning this is a replay
     * and the caller should acknowledge it and do nothing else.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function claim(string $gateway, string $eventId, string $type, array $payload): ?self
    {
        try {
            return static::create([
                'gateway' => $gateway,
                'event_id' => $eventId,
                'event_type' => $type,
                'payload' => $payload,
            ]);
        } catch (QueryException) {
            // Unique violation: another delivery of the same event got here first.
            return null;
        }
    }

    public function markProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }
}
