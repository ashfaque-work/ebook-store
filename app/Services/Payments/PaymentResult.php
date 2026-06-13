<?php

namespace App\Services\Payments;

/**
 * The outcome of a payment attempt, independent of any specific gateway.
 */
class PaymentResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
    ) {}

    public static function success(string $reference): self
    {
        return new self(true, $reference);
    }

    public static function failure(string $message): self
    {
        return new self(false, null, $message);
    }
}
