<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Razorpay, over its REST API.
 *
 * Deliberately not using the razorpay/razorpay SDK: this integration touches
 * three endpoints, signature verification is two lines of hash_hmac, and
 * Laravel's HTTP client gives hermetic tests through Http::fake() without
 * wrapping a third-party client.
 *
 * Amounts are integer paise throughout, which is what the API expects.
 */
class RazorpayPaymentGateway implements PaymentGateway
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $webhookSecret,
    ) {}

    public function provider(): string
    {
        return 'razorpay';
    }

    public function createSession(Order $order): PaymentSession
    {
        // Reuse the gateway order if this checkout is being retried, so a
        // customer who reloads the pay page does not leave orphans behind.
        $gatewayOrderId = $order->gateway_order_id;

        if (! $gatewayOrderId) {
            $response = $this->client()->post(self::BASE_URL.'/orders', [
                'amount' => $order->total_paise,
                'currency' => $order->currency,
                'receipt' => $order->order_number,
                'notes' => [
                    'order_number' => $order->order_number,
                    'user_id' => (string) $order->user_id,
                ],
            ]);

            $response->throw();

            $gatewayOrderId = $response->json('id');

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
            publicKey: $this->key,
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

        if ($gatewayOrderId === '' || $paymentId === '' || $signature === '') {
            return PaymentResult::failure('Incomplete payment confirmation.');
        }

        // The callback must be for the order we think it is.
        if ($gatewayOrderId !== $order->gateway_order_id) {
            return PaymentResult::failure('This confirmation belongs to a different order.');
        }

        $expected = hash_hmac('sha256', $gatewayOrderId.'|'.$paymentId, $this->secret);

        if (! hash_equals($expected, $signature)) {
            return PaymentResult::failure('Payment confirmation failed verification.');
        }

        // Signature proves the payload is ours; only the API can tell us what
        // was actually captured, so the amount is checked against the source.
        $payment = $this->client()->get(self::BASE_URL.'/payments/'.$paymentId);

        if ($payment->failed()) {
            return PaymentResult::failure('Could not confirm the payment with Razorpay.');
        }

        $amount = (int) $payment->json('amount');
        $status = (string) $payment->json('status');

        if ($amount !== $order->total_paise) {
            return PaymentResult::failure('The amount paid does not match this order.');
        }

        if (! in_array($status, ['captured', 'authorized'], true)) {
            return PaymentResult::failure('This payment has not been completed.');
        }

        return PaymentResult::success(
            reference: $paymentId,
            amountPaise: $amount,
            method: $payment->json('method'),
            raw: $payment->json() ?? [],
        );
    }

    public function verifyWebhook(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $this->webhookSecret === '') {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $rawBody, $this->webhookSecret),
            $signature,
        );
    }

    public function refund(Payment $payment, ?int $amountPaise = null): PaymentResult
    {
        $amount = $amountPaise ?? ($payment->amount_paise - $payment->refunded_paise);

        if ($amount <= 0) {
            return PaymentResult::failure('There is nothing left to refund on this payment.');
        }

        $response = $this->client()->post(
            self::BASE_URL.'/payments/'.$payment->gateway_payment_id.'/refund',
            ['amount' => $amount],
        );

        if ($response->failed()) {
            return PaymentResult::failure(
                $response->json('error.description') ?? 'Razorpay refused the refund.',
                $response->json() ?? [],
            );
        }

        return PaymentResult::success(
            reference: (string) $response->json('id'),
            amountPaise: (int) $response->json('amount'),
            raw: $response->json() ?? [],
        );
    }

    private function client()
    {
        return Http::withBasicAuth($this->key, $this->secret)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 200, throw: false);
    }
}
