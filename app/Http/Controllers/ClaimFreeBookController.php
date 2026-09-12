<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Models\Book;
use App\Services\Checkout\FulfilOrder;
use App\Services\Checkout\PlaceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Take a free book straight to the reader.
 *
 * A cart, a checkout and a payment page for something that costs nothing is a
 * queue with nothing at the end of it. Every free book a reader opens is a
 * reader who now has an account, a library and a habit — so the path to it
 * should be one click.
 *
 * It still places a real order underneath. The library, the reader, order
 * history and the download gate all read from orders, and a second mechanism
 * for "owns this book" is how the two drift apart.
 */
class ClaimFreeBookController extends Controller
{
    public function __invoke(Book $book, PlaceOrder $placeOrder, FulfilOrder $fulfil): RedirectResponse
    {
        $user = auth()->user();

        // Not a paid book behind a forged price: the check is on what the book
        // costs now, in the database, not on anything the request carries.
        if (! $book->isFree()) {
            return redirect()->route('books.show', $book->slug)->with('toast', [
                'type' => 'info',
                'message' => 'That book is not free.',
            ]);
        }

        if ($user->hasPurchased($book)) {
            return redirect()->route('reader.show', $book->slug);
        }

        // The same lock checkout uses. A double-click should produce one order,
        // not two, and the reader should not have to care which.
        $lock = Cache::lock('checkout:'.$user->id, 15);

        if (! $lock->get()) {
            return redirect()->route('books.show', $book->slug)->with('toast', [
                'type' => 'info',
                'message' => 'Just a moment — that is already being added.',
            ]);
        }

        try {
            $order = $placeOrder($user, [$book->id]);
            $fulfil($order);
        } catch (CheckoutException $e) {
            return redirect()->route($e->redirectRoute)->with('toast', [
                'type' => $e->toastType,
                'message' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }

        return redirect()->route('reader.show', $book->slug)->with('toast', [
            'type' => 'success',
            'message' => 'Added to your library. Enjoy.',
        ]);
    }
}
