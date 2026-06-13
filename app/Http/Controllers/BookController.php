<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Book $book): Response
    {
        $book->load('author', 'genre');

        // Check if the book's ID is in the cart session array
        $isBookInCart = in_array($book->id, Session::get('cart', []));

        // Has the signed-in user already bought this book?
        $isPurchased = (bool) auth()->user()?->hasPurchased($book);

        return Inertia::render('Books/Show', [
            'book' => $book,
            'isBookInCart' => $isBookInCart, // Pass this boolean to the frontend
            'isPurchased' => $isPurchased,
        ]);
    }
}
