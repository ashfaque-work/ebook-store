<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use App\Models\Order;
use App\Services\Checkout\FulfilOrder;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The simulated gateway's checkout, standing in for Razorpay's modal.
 *
 * A real gateway does not only succeed or fail a signature check — it declines
 * cards, gets dismissed halfway, and sometimes reports a payment by webhook
 * while the customer's browser never comes back at all. Those are the paths
 * that break in production, and none of them can be rehearsed against live
 * keys without spending real money.
 *
 * Each outcome below drives the same code Razorpay would drive; nothing here
 * takes a shortcut around verification or fulfilment.
 */
class SimulatedGatewayController extends Controller
{
    public const OUTCOMES = ['paid', 'failed', 'abandoned', 'webhook'];

    public function __invoke(
        Request $request,
        Order $order,
        PaymentGateway $gateway,
        FulfilOrder $fulfilOrder,
    ): RedirectResponse {
        // Registered unconditionally so routes stay cacheable, but it only
        // exists while the simulated gateway is the one bound.
        abort_unless($gateway instanceof FakePaymentGateway, 404);

        $this->authorize('view', $order);

        $outcome = $request->validate([
            'outcome' => ['required', Rule::in(self::OUTCOMES)],
        ])['outcome'];

        if ($order->isPaid()) {
            return redirect()->route('checkout.success', $order);
        }

        return match ($outcome) {
            'paid' => $this->succeed($order, $gateway, $fulfilOrder),
            'failed' => $this->decline($order),
            'abandoned' => $this->abandon($order),
            'webhook' => $this->payByWebhookOnly($order, $gateway),
        };
    }

    /**
     * The ordinary happy path: the gateway hands a signed payload back to the
     * browser, which posts it to checkout.verify.
     */
    private function succeed(Order $order, FakePaymentGateway $gateway, FulfilOrder $fulfilOrder): RedirectResponse
    {
        $paymentId = $this->paymentId();

        $result = $gateway->verifyCallback($order, [
            'razorpay_order_id' => (string) $order->gateway_order_id,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => FakePaymentGateway::sign((string) $order->gateway_order_id, $paymentId),
        ]);

        if (! $result->successful) {
            return back()->with('toast', ['type' => 'error', 'message' => $result->message]);
        }

        $fulfilOrder($order, $result);

        Session::forget('cart');

        return redirect()->route('checkout.success', $order)->with('toast', [
            'type' => 'success',
            'message' => 'Simulated payment captured. Your books are ready to read.',
        ]);
    }

    /**
     * The bank says no. The order keeps its place so the customer can retry
     * with another method rather than rebuilding their cart.
     */
    private function decline(Order $order): RedirectResponse
    {
        $order->update([
            'status' => Order::STATUS_FAILED,
            'failure_reason' => 'Simulated decline: the card was refused by the issuing bank.',
        ]);

        return redirect()->route('cart.index')->with('toast', [
            'type' => 'error',
            'message' => 'Simulated decline. Nothing was charged — your cart is still here.',
        ]);
    }

    /** The customer closed the modal without paying. */
    private function abandon(Order $order): RedirectResponse
    {
        return redirect()->route('cart.index')->with('toast', [
            'type' => 'info',
            'message' => 'Payment cancelled. Your order is saved — pay for it whenever you are ready.',
        ]);
    }

    /**
     * The one worth rehearsing: money is taken, then the browser never comes
     * back — closed tab, dead battery, tunnel. Only the webhook can save this
     * customer, so it is driven through the real webhook controller, signature
     * check and event de-duplication included.
     */
    private function payByWebhookOnly(Order $order, FakePaymentGateway $gateway): RedirectResponse
    {
        $payload = [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => $this->paymentId(),
                'order_id' => $order->gateway_order_id,
                'amount' => $order->total_paise,
                'currency' => $order->currency,
                'status' => 'captured',
                'method' => 'upi',
            ]]],
        ];

        $raw = json_encode($payload);

        $request = Request::create(route('webhooks.razorpay'), 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, FakePaymentGateway::SECRET),
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_sim_'.Str::lower(Str::random(12)),
        ], $raw);

        app(RazorpayWebhookController::class)($request, $gateway, app(FulfilOrder::class));

        Session::forget('cart');

        return redirect()->route('library.index')->with('toast', [
            'type' => 'success',
            'message' => 'Paid by webhook, with the browser never returning. This is what saves a customer who closes the tab mid-payment.',
        ]);
    }

    private function paymentId(): string
    {
        return 'fake_pay_'.Str::lower(Str::random(14));
    }
}
