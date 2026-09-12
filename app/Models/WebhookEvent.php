<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        $now = now();

        // insertOrIgnore rather than catching the unique violation: PostgreSQL
        // aborts the entire transaction when a statement fails, so swallowing
        // the exception leaves every later query in that transaction throwing
        // "current transaction is aborted". MySQL and SQLite are forgiving
        // about it, which is exactly what made this easy to miss.
        $inserted = static::query()->insertOrIgnore([
            'gateway' => $gateway,
            'event_id' => $eventId,
            'event_type' => $type,
            'payload' => json_encode($payload),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            // Another delivery of the same event got here first.
            return null;
        }

        return static::firstWhere('event_id', $eventId);
    }

    public function markProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }
}
