<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * Anyone may look at a published book. Admins can preview drafts.
     */
    public function view(?User $user, Book $book): bool
    {
        return $book->is_published || (bool) $user?->isAdmin();
    }

    /**
     * The gate on every paid asset. Nothing else may decide this.
     */
    public function download(User $user, Book $book): bool
    {
        return $user->hasPurchased($book);
    }

    /**
     * A sold book can be unpublished but never deleted — removing it would
     * strand every customer who paid for it, and the restrictOnDelete
     * constraint on order_items would fail the delete anyway.
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->isAdmin() && ! $book->hasBeenPurchased();
    }
}
