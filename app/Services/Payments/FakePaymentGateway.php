<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * The gateway used in local development and in the test suite.
 *
 * It implements the same four steps as the real one and signs its callbacks
 * with real HMAC, so the signature-checking path is exercised rather than
 * skipped. Nothing here talks to the network.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** Not a secret — this gateway only ever runs locally and in tests. */
    public const SECRET = 'fake-gateway-secret';

    public function provider(): string
    {
        return 'fake';
    }

    public function createSession(Order $order): PaymentSession
    {
        $gatewayOrderId = $order->gateway_order_id ?: 'fake_order_'.Str::lower(Str::random(14));

        if (! $order->gateway_order_id) {
            $order->update([
                'gateway' => $this->provider(),
                'gateway_order_id' => $gatewayOrderId,
            ]);
        }

        return new PaymentSession(
            provider: $this->provider(),
            gatewayOrderId: $gatewayOrderId,
            amountPaise: $order->total_paise,
            currency: $order->currency,
            publicKey: 'fake_key',
            prefill: [
                'name' => $order->user?->name,
                'email' => $order->user?->email,
            ],
        );
    }

    public function verifyCallback(Order $order, array $payload): PaymentResult
    {
        $gatewayOrderId = (string) ($payload['razorpay_order_id'] ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? '');

        if ($gatewayOrderId !== $order->gateway_order_id) {
            return PaymentResult::failure('This confirmation belongs to a different order.');
        }

        if (! hash_equals(self::sign($gatewayOrderId, $paymentId), $signature)) {
            return PaymentResult::failure('Payment confirmation failed verification.');
        }

        return PaymentResult::success(
            reference: $paymentId,
            amountPaise: $order->total_paise,
            method: 'upi',
            raw: ['simulated' => true],
        );
    }

    public function verifyWebhook(string $rawBody, ?string $signature): bool
    {
        return $signature !== null
            && hash_equals(hash_hmac('sha256', $rawBody, self::SECRET), $signature);
    }

    public function refund(Payment $payment, ?int $amountPaise = null): PaymentResult
    {
        $amount = $amountPaise ?? ($payment->amount_paise - $payment->refunded_paise);

        if ($amount <= 0) {
            return PaymentResult::failure('There is nothing left to refund on this payment.');
        }

        return PaymentResult::success(
            reference: 'fake_rfnd_'.Str::lower(Str::random(14)),
            amountPaise: $amount,
            raw: ['simulated' => true],
        );
    }

    /** The signature the simulated checkout page sends back. */
    public static function sign(string $gatewayOrderId, string $paymentId): string
    {
        return hash_hmac('sha256', $gatewayOrderId.'|'.$paymentId, self::SECRET);
    }
}
