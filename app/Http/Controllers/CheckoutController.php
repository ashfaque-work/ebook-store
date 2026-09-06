<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Services\Checkout\FulfilOrder;
use App\Services\Checkout\PlaceOrder;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * Turn the current cart into a paid order.
     *
     * The controller only orchestrates: PlaceOrder decides what is owed and
     * FulfilOrder decides what "paid" means, because the gateway webhook needs
     * to reach exactly the same conclusions.
     */
    public function store(
        PaymentGateway $gateway,
        PlaceOrder $placeOrder,
        FulfilOrder $fulfilOrder,
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
            $order = $placeOrder($user, Session::get('cart', []));

            // An identical order that was already paid for: send them to it
            // rather than charging a second time.
            if ($order->isPaid()) {
                Session::forget('cart');

                return redirect()->route('checkout.success', $order)->with('toast', [
                    'type' => 'info',
                    'message' => 'This order has already been paid for.',
                ]);
            }

            $result = $gateway->charge($order);

            if (! $result->successful) {
                $order->update(['status' => Order::STATUS_FAILED]);

                throw CheckoutException::paymentFailed($result->message ?? 'Payment failed.');
            }

            $fulfilOrder($order, ['reference' => $result->reference]);

            Session::forget('cart');

            return redirect()->route('checkout.success', $order)->with('toast', [
                'type' => 'success',
                'message' => 'Payment successful. Your books are ready to read.',
            ]);
        } catch (CheckoutException $e) {
            if ($e->redirectRoute === 'library.index') {
                Session::forget('cart');
            }

            return redirect()->route($e->redirectRoute)->with('toast', [
                'type' => $e->toastType,
                'message' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }
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
