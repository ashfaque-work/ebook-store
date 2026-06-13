<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Contract every payment gateway must satisfy.
 *
 * Swapping the mock gateway for a real one (e.g. Stripe) is just a matter of
 * providing another implementation and re-binding it in a service provider —
 * no checkout code needs to change.
 */
interface PaymentGateway
{
    /**
     * Attempt to charge for the given order.
     *
     * @param  array<string, mixed>  $paymentDetails  Gateway-specific payload (token, etc.)
     */
    public function charge(Order $order, array $paymentDetails = []): PaymentResult;
}
