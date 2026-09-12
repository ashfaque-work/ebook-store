<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hold the checkout closed while the gateway account is still in review.
 *
 * Everything up to paying stays open — browsing, samples, the cart, free
 * downloads — because the point of the window is to have the store live and
 * readable while Razorpay reviews it.
 */
class EnsurePaymentsAreEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('store.payments_enabled')) {
            return $next($request);
        }

        // Back to the cart rather than an error page: nothing has gone wrong,
        // and whatever they picked is still waiting for them.
        return redirect()->route('cart.index')->with('toast', [
            'type' => 'info',
            'message' => 'Purchasing opens shortly. Your cart is saved.',
        ]);
    }
}
