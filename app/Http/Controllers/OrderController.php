<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * List the authenticated user's orders.
     */
    public function index(): Response
    {
        $orders = auth()->user()->orders()
            ->withCount('items')
            ->latest()
            ->paginate(10);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Show a single order receipt.
     */
    public function show(Order $order): Response
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load('items');

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }
}
