<?php

namespace App\Services\Payments;

/**
 * The outcome of a payment attempt, independent of any specific gateway.
 */
class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
        public readonly ?int $amountPaise = null,
        public readonly ?string $method = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function success(
        string $reference,
        ?int $amountPaise = null,
        ?string $method = null,
        array $raw = [],
    ): self {
        return new self(true, $reference, null, $amountPaise, $method, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, $message, null, null, $raw);
    }
}
