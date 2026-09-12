<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Record a refund the gateway has accepted, and revoke access if it is full.
 *
 * The same refund is reported twice — once by the admin action that requested
 * it, once by the gateway's webhook — so this is keyed on the gateway's own
 * refund id and is a no-op the second time. Exactly the shape of FulfilOrder,
 * and for the same reason.
 *
 * The order's refunded total is recomputed by summing refund rows rather than
 * incremented in place, so it cannot drift.
 */
class ApplyRefund
{
    /**
     * @return bool true if this call is the one that recorded the refund
     */
    public function __invoke(
        Payment $payment,
        string $gatewayRefundId,
        int $amountPaise,
        ?string $reason = null,
        ?int $refundedBy = null,
    ): bool {
        return DB::transaction(function () use ($payment, $gatewayRefundId, $amountPaise, $reason, $refundedBy) {
            $now = now();

            // insertOrIgnore rather than catching the unique violation. This
            // runs inside a transaction, and PostgreSQL aborts the whole
            // transaction when a statement fails — so catching the exception
            // would leave recalculate() throwing "current transaction is
            // aborted" instead of quietly doing nothing.
            $inserted = Refund::query()->insertOrIgnore([
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'gateway_refund_id' => $gatewayRefundId,
                'amount_paise' => $amountPaise,
                'reason' => $reason,
                'refunded_by' => $refundedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 0) {
                // We have already recorded this refund.
                return false;
            }

            $this->recalculate($payment->fresh());

            return true;
        });
    }

    private function recalculate(Payment $payment): void
    {
        $refunded = (int) Refund::where('payment_id', $payment->id)->sum('amount_paise');
        $full = $refunded >= $payment->amount_paise;

        $payment->update([
            'refunded_paise' => $refunded,
            'status' => $full ? Payment::STATUS_REFUNDED : Payment::STATUS_PARTIALLY_REFUNDED,
        ]);

        $order = $payment->order;
        $orderRefunded = (int) Refund::where('order_id', $order->id)->sum('amount_paise');
        $orderFull = $orderRefunded >= $order->total_paise;

        $order->update([
            'refunded_paise' => $orderRefunded,
            'refunded_at' => now(),
            // A full refund revokes the library, because hasPurchased() counts
            // only paid orders — no separate bookkeeping to fall out of sync.
            'status' => $orderFull ? Order::STATUS_REFUNDED : $order->status,
        ]);
    }
}
