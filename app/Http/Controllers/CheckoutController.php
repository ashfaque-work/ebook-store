<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Models\Book;
use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * Turn the current cart into a paid order via the (mock) payment gateway.
     */
    public function store(PaymentGateway $gateway): RedirectResponse
    {
        $user = auth()->user();
        $bookIds = Session::get('cart', []);

        if (empty($bookIds)) {
            return redirect()->route('cart.index')->with('toast', [
                'type' => 'error',
                'message' => 'Your cart is empty.',
            ]);
        }

        // Load real books and drop anything the user already owns. Prices are
        // taken from the database, never from the client.
        $books = Book::whereIn('id', $bookIds)->get()
            ->reject(fn (Book $book) => $user->hasPurchased($book));

        if ($books->isEmpty()) {
            Session::forget('cart');

            return redirect()->route('library.index')->with('toast', [
                'type' => 'info',
                'message' => 'You already own everything that was in your cart.',
            ]);
        }

        try {
            $order = DB::transaction(function () use ($user, $books, $gateway) {
                $order = $user->orders()->create([
                    'order_number' => 'ORD-'.strtoupper(Str::random(10)),
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

        Mail::to($user)->send(new OrderConfirmation($order->load('items', 'user')));

        return redirect()->route('checkout.success', $order)->with('toast', [
            'type' => 'success',
            'message' => 'Payment successful. Your books are ready to download.',
        ]);
    }

    /**
     * Order confirmation / receipt page.
     */
    public function success(Order $order): Response
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load('items');

        return Inertia::render('Checkout/Success', [
            'order' => $order,
        ]);
    }
}
