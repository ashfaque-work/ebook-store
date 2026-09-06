<?php

namespace App\Services\Checkout;

use App\Exceptions\CheckoutException;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turn a cart into a pending order.
 *
 * Everything that decides what is owed happens here and only here: which books
 * are actually purchasable, what they cost, and how the tax breaks down. The
 * client sends book IDs and nothing else.
 */
class PlaceOrder
{
    public function __construct(private readonly TaxCalculator $tax) {}

    /**
     * @param  array<int, int|string>  $cartBookIds
     *
     * @throws CheckoutException
     */
    public function __invoke(User $user, array $cartBookIds, ?string $buyerStateCode = null): Order
    {
        if (empty($cartBookIds)) {
            throw CheckoutException::emptyCart();
        }

        $books = $this->purchasableBooks($user, $cartBookIds);

        if ($books->isEmpty()) {
            throw CheckoutException::nothingToBuy();
        }

        $idempotencyKey = Order::idempotencyKeyFor($user, $books);

        // The key is unique, so an identical order already exists or it does
        // not. A paid one is returned as-is rather than charged again; an
        // unpaid one is the same attempt being retried, so reuse it instead of
        // colliding with its own key.
        $existing = Order::where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            if (! $existing->isPaid() && $existing->status !== Order::STATUS_PENDING) {
                $existing->update(['status' => Order::STATUS_PENDING]);
            }

            return $existing;
        }

        return DB::transaction(function () use ($user, $books, $idempotencyKey, $buyerStateCode) {
            $lines = [];

            foreach ($books as $book) {
                $split = $this->tax->split($book->price_paise, $book->tax_rate);

                $lines[] = [
                    'book_id' => $book->id,
                    'title' => $book->title,
                    'price_paise' => $book->price_paise,
                    'subtotal_paise' => $split['subtotal_paise'],
                    'tax_paise' => $split['tax_paise'],
                    'tax_rate' => $split['tax_rate'],
                ];
            }

            $totals = $this->tax->totals($lines);

            $order = $user->orders()->create([
                'order_number' => 'ORD-'.strtoupper(Str::random(10)),
                'idempotency_key' => $idempotencyKey,
                'status' => Order::STATUS_PENDING,
                'currency' => config('store.currency'),
                'total_paise' => $totals['total_paise'],
                'subtotal_paise' => $totals['subtotal_paise'],
                'tax_paise' => $totals['tax_paise'],
                'tax_type' => $this->tax->typeFor($buyerStateCode),
                'buyer_state_code' => $buyerStateCode,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });
    }

    /**
     * Books that are on sale and that this user does not already own. Prices
     * come from these rows, never from the request.
     *
     * @param  array<int, int|string>  $cartBookIds
     * @return Collection<int, Book>
     */
    private function purchasableBooks(User $user, array $cartBookIds): Collection
    {
        return Book::published()
            ->whereIn('id', $cartBookIds)
            ->get()
            ->reject(fn (Book $book) => $user->hasPurchased($book))
            ->values();
    }
}
