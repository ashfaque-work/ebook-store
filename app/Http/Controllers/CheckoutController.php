<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Services\Checkout\FulfilOrder;
use App\Services\Checkout\PlaceOrder;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout is three steps, because that is the shape of every Indian gateway:
 *
 *   store()  build the order, open a gateway session, hand off to the browser
 *   pay()    the page that opens the gateway's checkout
 *   verify() the browser comes back; confirm it, then fulfil
 *
 * The webhook (see Webhooks\RazorpayWebhookController) is the safety net for
 * everyone whose browser never makes it back to verify().
 */
class CheckoutController extends Controller
{
    public function store(
        PaymentGateway $gateway,
        PlaceOrder $placeOrder,
    ): RedirectResponse {
        $user = auth()->user();

        // Serialise checkouts per user. Two rapid submissions would otherwise
        // both read "not yet owned" before either committed, and both charge.
        $lock = Cache::lock('checkout:'.$user->id, 15);

        if (! $lock->get()) {
            return redirect()->route('cart.index')->with('toast', [
                'type' => 'info',
                'message' => 'Your checkout is already being processed. Give it a moment.',
            ]);
        }

        try {
            $order = $placeOrder($user, Session::get('cart', []), $user->state_code);

            if ($order->isPaid()) {
                Session::forget('cart');

                return redirect()->route('checkout.success', $order)->with('toast', [
                    'type' => 'info',
                    'message' => 'This order has already been paid for.',
                ]);
            }

            $gateway->createSession($order);

            return redirect()->route('checkout.pay', $order);
        } catch (CheckoutException $e) {
            if ($e->redirectRoute === 'library.index') {
                Session::forget('cart');
            }

            return redirect()->route($e->redirectRoute)->with('toast', [
                'type' => $e->toastType,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('cart.index')->with('toast', [
                'type' => 'error',
                'message' => 'We could not reach the payment provider. Nothing has been charged — please try again.',
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * The hand-off page. Opens the gateway's own checkout in the browser.
     */
    public function pay(Order $order, PaymentGateway $gateway): RedirectResponse|Response
    {
        $this->authorize('view', $order);

        if ($order->isPaid()) {
            return redirect()->route('checkout.success', $order);
        }

        $order->load('items');

        $session = $gateway->createSession($order);

        return Inertia::render('Checkout/Pay', [
            'order' => $order,
            'session' => $session->toArray(),
            // The mock gateway has no hosted checkout, so the page needs a
            // pre-signed payload to play the part of one. Only ever present
            // when the mock gateway is bound.
            'simulation' => $gateway instanceof FakePaymentGateway
                ? $this->simulationPayload($session->gatewayOrderId)
                : null,
        ]);
    }

    /**
     * A valid callback payload for the mock gateway, so local development
     * exercises the same verify() path production uses.
     *
     * @return array<string, string>
     */
    private function simulationPayload(string $gatewayOrderId): array
    {
        $paymentId = 'fake_pay_'.strtolower(Str::random(14));

        return [
            'razorpay_order_id' => $gatewayOrderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => FakePaymentGateway::sign($gatewayOrderId, $paymentId),
        ];
    }

    /**
     * The browser has come back from the gateway. Verify, then fulfil.
     */
    public function verify(
        Request $request,
        Order $order,
        PaymentGateway $gateway,
        FulfilOrder $fulfilOrder,
    ): RedirectResponse {
        $this->authorize('view', $order);

        if ($order->isPaid()) {
            Session::forget('cart');

            return redirect()->route('checkout.success', $order);
        }

        $payload = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $result = $gateway->verifyCallback($order, $payload);

        if (! $result->successful) {
            $order->update([
                'status' => Order::STATUS_FAILED,
                'failure_reason' => $result->message,
            ]);

            return redirect()->route('cart.index')->with('toast', [
                'type' => 'error',
                'message' => $result->message ?? 'We could not confirm that payment.',
            ]);
        }

        $fulfilOrder($order, $result);

        Session::forget('cart');

        return redirect()->route('checkout.success', $order)->with('toast', [
            'type' => 'success',
            'message' => 'Payment successful. Your books are ready to read.',
        ]);
    }

    /**
     * Order confirmation / receipt page.
     */
    public function success(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load('items');

        return Inertia::render('Checkout/Success', [
            'order' => $order,
        ]);
    }
}
