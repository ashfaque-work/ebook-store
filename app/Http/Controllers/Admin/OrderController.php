<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Checkout\RefundOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * You cannot run a store without being able to see what has been sold, or
 * refund it when something goes wrong.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'search']);

        $orders = Order::with('user')
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'stats' => $this->stats(),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['user', 'items', 'payments', 'refunds.refundedBy']);

        $payment = $order->capturedPayment();

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'refundablePaise' => $payment?->refundablePaise() ?? 0,
        ]);
    }

    /**
     * Refund an order, fully or in part.
     */
    public function refund(Request $request, Order $order, RefundOrder $refundOrder): RedirectResponse
    {
        $validated = $request->validate([
            // Rupees in the form; the service works in paise.
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $amountPaise = isset($validated['amount'])
            ? (int) bcmul((string) $validated['amount'], '100', 0)
            : null;

        $result = $refundOrder(
            $order,
            $amountPaise,
            $validated['reason'] ?? null,
            $request->user()->id,
        );

        return back()->with('toast', [
            'type' => $result->successful ? 'success' : 'error',
            'message' => $result->successful
                ? 'Refund issued. It reaches the customer in '.config('store.refund_processing_days').'.'
                : ($result->message ?? 'The refund could not be issued.'),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        $paid = Order::where('status', Order::STATUS_PAID);

        return [
            'paidCount' => (clone $paid)->count(),
            'grossPaise' => (int) (clone $paid)->sum('total_paise'),
            'refundedPaise' => (int) Order::sum('refunded_paise'),
            'pendingCount' => Order::where('status', Order::STATUS_PENDING)->count(),
            'last30Paise' => (int) (clone $paid)
                ->where('paid_at', '>=', now()->subDays(30))
                ->sum('total_paise'),
            'customers' => (int) Order::where('status', Order::STATUS_PAID)
                ->distinct()
                ->count(DB::raw('user_id')),
        ];
    }
}
