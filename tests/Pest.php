<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
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
        signOut();

        return $order;
    }

    test()->actingAs($user)
        ->post(route('checkout.verify', $order), callbackPayloadFor($order));

    // actingAs() persists for the rest of the test, so leave the caller a
    // guest: a test that wants a user signed in says so itself.
    signOut();

    return $order->fresh();
}

/** Drop whatever actingAs() left behind. */
function signOut(): void
{
    app('auth')->forgetGuards();
}

/**
 * A correctly signed gateway callback for an order awaiting payment.
 *
 * @return array<string, string>
 */
function callbackPayloadFor(Order $order, ?string $paymentId = null): array
{
    $paymentId ??= 'fake_pay_'.strtolower(Str::random(14));

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
                    'id' => $paymentId ?? 'fake_pay_'.strtolower(Str::random(14)),
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

/**
 * Post a webhook the way the gateway does: no session, no CSRF token, and a
 * raw body whose exact bytes are what the signature covers.
 *
 * @param  array<string, mixed>  $payload
 */
function postWebhook(array $payload, ?string $signature = null, ?string $eventId = null)
{
    [$raw, $sig] = signedWebhook($payload);

    return test()->call(
        'POST',
        route('webhooks.razorpay'),
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? $sig,
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId ?? 'evt_'.bin2hex(random_bytes(8)),
        ],
        $raw,
    );
}

/**
 * Request a deferred prop the way the browser does, after first paint.
 *
 * Deferred props are invisible to an ordinary page assertion — the closure
 * does not run on the first response — so without this, a broken query inside
 * one stays green forever. The version header matters: Inertia answers 409 to
 * a partial reload that does not carry the current asset version.
 *
 * @param  array<int, string>|string  $props
 */
function inertiaPartial(string $uri, string $component, array|string $props)
{
    // Ask the middleware itself rather than the facade: the version resolver
    // is only bound while a request is being handled.
    $version = app(HandleInertiaRequests::class)->version(request());

    return test()->get($uri, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => is_array($props) ? implode(',', $props) : $props,
    ]);
}

function actingAsAdmin(): TestCase
{
    return test()->actingAs(User::factory()->admin()->create());
}
