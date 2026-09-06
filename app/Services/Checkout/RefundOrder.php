<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;

/**
 * Refund an order through the gateway, then record what came back.
 *
 * The money moves first and is recorded second. If recording somehow fails,
 * the gateway's webhook reports the same refund id and ApplyRefund picks it up
 * — which is why that step is idempotent rather than assumed to run once.
 */
class RefundOrder
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyRefund $applyRefund,
    ) {}

    public function __invoke(
        Order $order,
        ?int $amountPaise = null,
        ?string $reason = null,
        ?int $refundedBy = null,
    ): PaymentResult {
        if (! $order->isPaid() && ! $order->refunded_paise) {
            return PaymentResult::failure('Only a paid order can be refunded.');
        }

        $payment = $order->capturedPayment();

        if (! $payment) {
            return PaymentResult::failure('This order has no captured payment to refund.');
        }

        $outstanding = $payment->refundablePaise();

        if ($outstanding <= 0) {
            return PaymentResult::failure('This payment has already been refunded in full.');
        }

        if ($amountPaise !== null && ($amountPaise <= 0 || $amountPaise > $outstanding)) {
            return PaymentResult::failure('That refund amount is more than is left on this payment.');
        }

        $result = $this->gateway->refund($payment, $amountPaise);

        if (! $result->successful) {
            return $result;
        }

        ($this->applyRefund)(
            $payment,
            $result->reference ?? 'manual-'.$payment->id.'-'.now()->timestamp,
            $result->amountPaise ?? $amountPaise ?? $outstanding,
            $reason,
            $refundedBy,
        );

        return $result;
    }
}
