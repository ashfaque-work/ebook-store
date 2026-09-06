<?php

namespace App\Services\Payments;

/**
 * Everything the browser needs to open a checkout, and nothing more.
 *
 * The API secret never appears here — only the publishable key.
 */
final class PaymentSession
{
    /**
     * @param  array<string, string|null>  $prefill
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $gatewayOrderId,
        public readonly int $amountPaise,
        public readonly string $currency,
        public readonly ?string $publicKey = null,
        public readonly array $prefill = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'gatewayOrderId' => $this->gatewayOrderId,
            'amountPaise' => $this->amountPaise,
            'currency' => $this->currency,
            'publicKey' => $this->publicKey,
            'prefill' => $this->prefill,
        ];
    }
}
