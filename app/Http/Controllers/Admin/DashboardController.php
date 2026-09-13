<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\Order;
use App\Models\ReadingProgress;
use App\Models\Refund;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What is actually happening in the shop.
 *
 * Until now an admin signing in landed on the authors list, which answers a
 * question nobody has. The first thing a shopkeeper wants is takings, what
 * sold, and whether anything is broken — in that order.
 *
 * Every figure here is derived, never stored. A cached total that drifts from
 * the orders table is worse than a slow one, and at this size the queries cost
 * nothing.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'takings' => $this->takings(),
            'catalogue' => $this->catalogue(),
            // Below the fold and a good deal more work than the headline
            // numbers, so the page paints without waiting for them.
            'topBooks' => Inertia::defer(fn () => $this->topBooks()),
            'recentOrders' => Inertia::defer(fn () => $this->recentOrders()),
            'beingRead' => Inertia::defer(fn () => $this->beingRead()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function takings(): array
    {
        $paid = Order::where('status', Order::STATUS_PAID);

        $last30 = (clone $paid)->where('paid_at', '>=', now()->subDays(30));
        $prior30 = Order::where('status', Order::STATUS_PAID)
            ->whereBetween('paid_at', [now()->subDays(60), now()->subDays(30)]);

        $recent = (int) $last30->sum('total_paise');
        $previous = (int) $prior30->sum('total_paise');

        return [
            'grossPaise' => (int) (clone $paid)->sum('total_paise'),
            'last30Paise' => $recent,
            // Against the thirty days before it, so the number says whether
            // things are moving rather than merely how big they are. Null when
            // there is no earlier period to compare against — a percentage
            // change from zero is not a fact.
            'changePercent' => $previous > 0 ? (int) round((($recent - $previous) / $previous) * 100) : null,
            'paidOrders' => (clone $paid)->count(),
            'awaitingPayment' => Order::where('status', Order::STATUS_PENDING)->count(),
            'refundedPaise' => (int) Refund::sum('amount_paise'),
            'customers' => User::where('role', User::ROLE_CUSTOMER)->count(),
        ];
    }

    /**
     * The catalogue's own health, which is the half of the job that has no
     * revenue attached and so tends to go unlooked at.
     *
     * @return array<string, mixed>
     */
    private function catalogue(): array
    {
        return [
            'books' => Book::count(),
            'published' => Book::where('is_published', true)->count(),
            'drafts' => Book::where('is_published', false)->count(),
            'free' => Book::where('is_published', true)->where('price_paise', 0)->count(),
            // Each of these shows a customer something worse than nothing: a
            // blank jacket on the shelf, or a reader that cannot say how long
            // the book is.
            'missingCovers' => Book::where('is_published', true)->whereNull('cover_image_path')->count(),
            'missingPageCounts' => Book::where('is_published', true)->whereNull('page_count')->count(),
            'authors' => Author::count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topBooks(): array
    {
        return Book::query()
            ->select('books.*')
            ->selectRaw('COUNT(order_items.id) as sold')
            ->selectRaw('COALESCE(SUM(order_items.subtotal_paise), 0) as earned_paise')
            ->join('order_items', 'order_items.book_id', '=', 'books.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', Order::STATUS_PAID)
            ->groupBy('books.id')
            ->orderByDesc('sold')
            ->limit(8)
            ->with('author')
            ->get()
            ->map(fn (Book $book) => [
                'id' => $book->id,
                'slug' => $book->slug,
                'title' => $book->title,
                'author' => $book->author?->name,
                'cover' => $book->cover_image_path,
                'sold' => (int) $book->sold,
                'earnedPaise' => (int) $book->earned_paise,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Order::with('user')
            ->withCount('items')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->order_number,
                'customer' => $order->user?->name ?? $order->user?->email,
                'status' => $order->status,
                'totalPaise' => $order->total_paise,
                'items' => $order->items_count,
                'placedAt' => $order->created_at?->toDayDateTimeString(),
            ])
            ->all();
    }

    /**
     * Who is actually reading, which is the number that predicts the next sale
     * and appears on no accounting report.
     *
     * @return array<int, array<string, mixed>>
     */
    private function beingRead(): array
    {
        return ReadingProgress::with('book')
            ->where('percent', '>', 0)
            ->where('percent', '<', 99)
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->filter(fn (ReadingProgress $progress) => $progress->book !== null)
            ->map(fn (ReadingProgress $progress) => [
                'id' => $progress->id,
                'title' => $progress->book->title,
                'percent' => (int) $progress->percent,
                'when' => $progress->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }
}
