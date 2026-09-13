<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moderation, after the fact.
 *
 * Reviews appear immediately and are hidden if they have to be, rather than
 * waiting in a queue for approval. A queue nobody empties is the same as
 * having no reviews at all, and a reviewer who never sees their words appear
 * does not write a second one.
 *
 * Hidden rather than deleted, so a decision can be looked at again — and so
 * the reason for it survives the person who made it.
 */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'search']);

        $reviews = Review::with(['book:id,slug,title', 'user:id,name,email'])
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'hidden' => $q->whereNotNull('hidden_at'),
                'visible' => $q->whereNull('hidden_at'),
                'critical' => $q->where('rating', '<=', 2)->whereNull('hidden_at'),
                default => $q,
            })
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('body', "%{$search}%")
                ->orWhereHas('book', fn ($b) => $b->whereLike('title', "%{$search}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $filters,
            'counts' => [
                'all' => Review::count(),
                'hidden' => Review::whereNotNull('hidden_at')->count(),
                'critical' => Review::where('rating', '<=', 2)->whereNull('hidden_at')->count(),
            ],
        ]);
    }

    public function hide(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        // forceFill, because these are deliberately not mass-assignable: no
        // request from a reader should ever be able to set them, and update()
        // would otherwise drop them on the floor without saying so.
        $review->forceFill([
            'hidden_at' => now(),
            'hidden_reason' => $validated['reason'] ?: null,
        ])->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Review hidden.']);
    }

    public function restore(Review $review): RedirectResponse
    {
        $review->forceFill(['hidden_at' => null, 'hidden_reason' => null])->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Review is visible again.']);
    }
}
