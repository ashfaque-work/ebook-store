<?php

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Run a complete purchase the way a customer does: cart → order → gateway
 * session → signed callback → fulfilment.
 *
 * Checkout is three steps because that is the shape of every Indian gateway,
 * and the mock gateway signs its callbacks with real HMAC, so this exercises
 * the signature-checking path rather than stepping around it.
 *
 * @param  array<int, int>  $bookIds
 */
function completeCheckout(User $user, array $bookIds): ?Order
{
    test()->actingAs($user)
        ->withSession(['cart' => $bookIds])
        ->post('/checkout');

    $order = Order::where('user_id', $user->id)->latest('id')->first();

    if (! $order || $order->isPaid()) {
        return $order;
    }

    test()->actingAs($user)
        ->post(route('checkout.verify', $order), callbackPayloadFor($order));

    return $order->fresh();
}

/**
 * A correctly signed gateway callback for an order awaiting payment.
 *
 * @return array<string, string>
 */
function callbackPayloadFor(Order $order, ?string $paymentId = null): array
{
    $paymentId ??= 'fake_pay_'.strtolower(Illuminate\Support\Str::random(14));

    return [
        'razorpay_order_id' => (string) $order->gateway_order_id,
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature' => FakePaymentGateway::sign((string) $order->gateway_order_id, $paymentId),
    ];
}

/**
 * Sign a webhook body the way the gateway would.
 *
 * @param  array<string, mixed>  $payload
 * @return array{0: string, 1: string} [raw body, signature]
 */
function signedWebhook(array $payload): array
{
    $raw = json_encode($payload);

    return [$raw, hash_hmac('sha256', $raw, FakePaymentGateway::SECRET)];
}

/**
 * A Razorpay-shaped payment.captured webhook body for an order.
 *
 * @return array<string, mixed>
 */
function capturedWebhookFor(Order $order, ?string $paymentId = null, ?int $amountPaise = null): array
{
    return [
        'event' => 'payment.captured',
        'payload' => [
            'payment' => [
                'entity' => [
                    'id' => $paymentId ?? 'fake_pay_'.strtolower(Illuminate\Support\Str::random(14)),
                    'order_id' => $order->gateway_order_id,
                    'amount' => $amountPaise ?? $order->total_paise,
                    'currency' => $order->currency,
                    'status' => 'captured',
                    'method' => 'upi',
                ],
            ],
        ],
    ];
}

function actingAsAdmin(): Tests\TestCase
{
    return test()->actingAs(User::factory()->admin()->create());
}
