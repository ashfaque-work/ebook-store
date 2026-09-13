<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Readers' opinions of a book.
 *
 * Only people who own it may write one. That is not gatekeeping for its own
 * sake: a store that can prove its reviewers actually have the book is worth
 * more than one with more reviews and no idea who wrote them, and it makes the
 * obvious spam route — sign up, rate everything — pointless.
 */
class ReviewController extends Controller
{
    public function store(Request $request, Book $book): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasPurchased($book)) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Only readers who have this book can review it.',
            ]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', Rule::in(range(Review::MIN_RATING, Review::MAX_RATING))],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:4000'],
        ]);

        $progress = ReadingProgress::where('user_id', $user->id)->where('book_id', $book->id)->first();

        Review::updateOrCreate(
            ['book_id' => $book->id, 'user_id' => $user->id],
            [
                ...$validated,
                // Recorded as it was at the time of writing. Whether they had
                // the book when they said this is a fact about the review.
                'verified' => true,
                'percent_read' => (int) ($progress->percent ?? 0),
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Thank you — your review is on the book page.',
        ]);
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        $request->user()->reviews()->where('book_id', $book->id)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Your review has been removed.',
        ]);
    }
}
