<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * Contract every payment gateway must satisfy.
 *
 * The earlier version of this interface was a single synchronous charge().
 * That shape does not fit any Indian gateway: Razorpay is create-session →
 * client modal → browser callback → webhook, and the webhook is the one that
 * matters, because it arrives whether or not the customer's browser survives
 * the payment.
 *
 * The mock gateway implements the same four steps, so local development and
 * the test suite exercise exactly the code path production uses.
 */
interface PaymentGateway
{
    /** Short identifier stored on the order, e.g. "razorpay". */
    public function provider(): string;

    /**
     * Create (or reuse) the gateway-side order and return what the browser
     * needs to open checkout.
     */
    public function createSession(Order $order): PaymentSession;

    /**
     * Verify what the browser handed back after payment.
     *
     * Never trust this payload without checking its signature, and never
     * without confirming the amount matches the order.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(Order $order, array $payload): PaymentResult;

    /**
     * Verify a webhook body against its signature header. Must be given the
     * raw request body: re-serialising it changes the bytes and the signature
     * will not match.
     */
    public function verifyWebhook(string $rawBody, ?string $signature): bool;

    /**
     * Refund a captured payment, fully or in part.
     */
    public function refund(Payment $payment, ?int $amountPaise = null): PaymentResult;
}
