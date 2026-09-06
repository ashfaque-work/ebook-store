<?php

namespace App\Services\Checkout;

use App\Mail\OrderConfirmation;
use App\Models\InvoiceSequence;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Mark an order paid and deliver it.
 *
 * This is the single place "paid" is decided. It is called from the browser
 * callback *and* from the gateway webhook, may well run twice within a second
 * of itself, and must be a no-op the second time — the customer who pays and
 * immediately closes the tab is served entirely by the webhook.
 *
 * Nearly every payment bug in a store like this comes from having two code
 * paths that disagree about what fulfilment means. There is only one here.
 */
class FulfilOrder
{
    /**
     * @param  array{reference?: string|null, payment_id?: string|null}  $payment
     * @return bool true if this call is the one that fulfilled the order
     */
    public function __invoke(Order $order, array $payment = []): bool
    {
        $fulfilled = DB::transaction(function () use ($order, $payment) {
            // Lock the row so a simultaneous webhook waits rather than racing.
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->isPaid()) {
                return false;
            }

            $locked->update([
                'status' => Order::STATUS_PAID,
                'payment_reference' => $payment['reference'] ?? $locked->payment_reference,
                'paid_at' => now(),
                // Receipt numbering is a gapless per-financial-year series.
                // Reserved inside this transaction so the number is never
                // burned by an order that then rolls back.
                'invoice_number' => $locked->invoice_number ?: InvoiceSequence::next(),
            ]);

            return true;
        });

        if (! $fulfilled) {
            return false;
        }

        $order->refresh();

        $this->sendReceipt($order);

        return true;
    }

    /**
     * A receipt that fails to send is never a reason to fail a paid order. On
     * the free tier QUEUE_CONNECTION=sync means this runs inline, so a mail
     * outage would otherwise become a checkout outage.
     */
    private function sendReceipt(Order $order): void
    {
        try {
            Mail::to($order->user)->send(new OrderConfirmation($order->load('items', 'user')));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
