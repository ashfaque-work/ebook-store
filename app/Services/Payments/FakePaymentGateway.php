<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Simulated gateway used for the mock checkout. It always succeeds and returns
 * a fake reference. Replace the binding with a real gateway when going live.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function charge(Order $order, array $paymentDetails = []): PaymentResult
    {
        return PaymentResult::success('MOCK-'.Str::upper(Str::random(16)));
    }
}
