<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Models\Book;
use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * Turn the current cart into a paid order via the payment gateway.
     */
    public function store(PaymentGateway $gateway): RedirectResponse
    {
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
            return $this->placeOrder($user, $gateway);
        } finally {
            $lock->release();
        }
    }

    /**
     * The actual order flow, run under the per-user checkout lock.
     */
    private function placeOrder($user, PaymentGateway $gateway): RedirectResponse
    {
        $bookIds = Session::get('cart', []);

        if (empty($bookIds)) {
            return redirect()->route('cart.index')->with('toast', [
                'type' => 'error',
                'message' => 'Your cart is empty.',
            ]);
        }

        // Load real books and drop anything the user already owns or that is no
        // longer for sale. Prices are taken from the database, never the client.
        $books = Book::published()->whereIn('id', $bookIds)->get()
            ->reject(fn (Book $book) => $user->hasPurchased($book));

        if ($books->isEmpty()) {
            Session::forget('cart');

            return redirect()->route('library.index')->with('toast', [
                'type' => 'info',
                'message' => 'You already own everything that was in your cart.',
            ]);
        }

        // A stable fingerprint of this buyer plus this exact set of books. If an
        // identical order already went through, send them to it rather than
        // charging again.
        $idempotencyKey = Order::idempotencyKeyFor($user, $books);

        $existing = Order::where('idempotency_key', $idempotencyKey)->first();

        if ($existing?->isPaid()) {
            Session::forget('cart');

            return redirect()->route('checkout.success', $existing)->with('toast', [
                'type' => 'info',
                'message' => 'This order has already been paid for.',
            ]);
        }

        try {
            $order = DB::transaction(function () use ($user, $books, $gateway, $idempotencyKey) {
                $order = $user->orders()->create([
                    'order_number' => 'ORD-'.strtoupper(Str::random(10)),
                    'idempotency_key' => $idempotencyKey,
                    'status' => Order::STATUS_PENDING,
                    'total' => $books->sum('price'),
                ]);

                foreach ($books as $book) {
                    $order->items()->create([
                        'book_id' => $book->id,
                        'title' => $book->title,
                        'price' => $book->price,
                    ]);
                }

                $result = $gateway->charge($order);

                if (! $result->successful) {
                    // Roll back the whole transaction so a failed order isn't kept.
                    throw new \RuntimeException($result->message ?? 'Payment failed.');
                }

                $order->update([
                    'status' => Order::STATUS_PAID,
                    'payment_reference' => $result->reference,
                    'paid_at' => now(),
                ]);

                return $order;
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('cart.index')->with('toast', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        // Purchase complete — empty the cart and email a receipt.
        Session::forget('cart');

        // A receipt that fails to send is not a reason to fail a paid checkout.
        // With QUEUE_CONNECTION=sync on the free tier this runs inline, so a
        // mail outage would otherwise become a checkout outage.
        try {
            Mail::to($user)->send(new OrderConfirmation($order->load('items', 'user')));
        } catch (\Throwable $e) {
            report($e);
        }

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
