<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Checkout\FulfilOrder;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The gateway's own report of what happened, and the source of truth.
 *
 * The browser callback is a convenience: it tells us quickly, but only if the
 * customer's tab survives the payment. This endpoint fires either way, which
 * is why fulfilment lives in a shared, idempotent service rather than in the
 * controller that happens to hear the news first.
 *
 * Anything successfully received is acknowledged with a 200, including events
 * we choose to ignore — Razorpay retries a non-200 for hours.
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentGateway $gateway,
        FulfilOrder $fulfilOrder,
    ): JsonResponse {
        // Must be the raw body: re-serialising changes the bytes and the
        // signature will not match.
        $raw = $request->getContent();

        if (! $gateway->verifyWebhook($raw, $request->header('X-Razorpay-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $payload = $request->json()->all();
        $type = (string) ($payload['event'] ?? 'unknown');

        // Razorpay carries the event id in a header. Fall back to a hash of the
        // body so a delivery without one is still deduplicated.
        $eventId = $request->header('X-Razorpay-Event-Id') ?: 'sha:'.hash('sha256', $raw);

        $event = WebhookEvent::claim($gateway->provider(), $eventId, $type, $payload);

        if (! $event) {
            // Already seen. Acknowledge and change nothing.
            return response()->json(['message' => 'Duplicate event ignored.']);
        }

        match ($type) {
            'payment.captured', 'order.paid' => $this->handleCaptured($payload, $fulfilOrder),
            'payment.failed' => $this->handleFailed($payload),
            'refund.processed' => $this->handleRefunded($payload),
            default => null,
        };

        $event->markProcessed();

        return response()->json(['message' => 'ok']);
    }

    /** @param array<string, mixed> $payload */
    private function handleCaptured(array $payload, FulfilOrder $fulfilOrder): void
    {
        $entity = $this->paymentEntity($payload);

        $order = $this->findOrder($entity);

        if (! $order) {
            return;
        }

        $amount = (int) ($entity['amount'] ?? 0);

        // A mismatch means the payment is not for what we think it is. Do not
        // fulfil; leave it for a human to look at.
        if ($amount !== $order->total_paise) {
            $order->update(['failure_reason' => 'Webhook amount did not match the order total.']);

            return;
        }

        $fulfilOrder($order, PaymentResult::success(
            reference: (string) ($entity['id'] ?? ''),
            amountPaise: $amount,
            method: $entity['method'] ?? null,
            raw: $entity,
        ));
    }

    /** @param array<string, mixed> $payload */
    private function handleFailed(array $payload): void
    {
        $entity = $this->paymentEntity($payload);
        $order = $this->findOrder($entity);

        // Never downgrade an order that is already paid: a failed attempt can
        // arrive after a successful retry.
        if (! $order || $order->isPaid()) {
            return;
        }

        $order->update([
            'status' => Order::STATUS_FAILED,
            'failure_reason' => $entity['error_description'] ?? 'The payment failed.',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function handleRefunded(array $payload): void
    {
        $entity = $payload['payload']['refund']['entity'] ?? [];
        $paymentId = (string) ($entity['payment_id'] ?? '');

        $payment = Payment::where('gateway_payment_id', $paymentId)->first();

        if (! $payment) {
            return;
        }

        $refunded = $payment->refunded_paise + (int) ($entity['amount'] ?? 0);
        $full = $refunded >= $payment->amount_paise;

        $payment->update([
            'refunded_paise' => $refunded,
            'status' => $full ? Payment::STATUS_REFUNDED : Payment::STATUS_PARTIALLY_REFUNDED,
        ]);

        $order = $payment->order;

        $order->update([
            'refunded_paise' => $refunded,
            'refunded_at' => now(),
            // A full refund revokes library access, because hasPurchased()
            // only counts paid orders.
            'status' => $full ? Order::STATUS_REFUNDED : $order->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function paymentEntity(array $payload): array
    {
        return $payload['payload']['payment']['entity'] ?? [];
    }

    /** @param array<string, mixed> $entity */
    private function findOrder(array $entity): ?Order
    {
        $gatewayOrderId = $entity['order_id'] ?? null;

        return $gatewayOrderId
            ? Order::where('gateway_order_id', $gatewayOrderId)->first()
            : null;
    }
}
