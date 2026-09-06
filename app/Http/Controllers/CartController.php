<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(): Response
    {
        $bookIds = Session::get('cart', []);
        $cartItems = Book::whereIn('id', $bookIds)->get(); // Re-fetch books from DB
        $total = $cartItems->sum('price');

        return Inertia::render('Cart/Index', [
            'cartItems' => $cartItems,
            'total' => $total,
        ]);
    }

    public function store(Request $request, Book $book): RedirectResponse
    {
        Session::push('cart', $book->id); // Store only the ID
        Session::put('cart', array_unique(Session::get('cart', []))); // Prevent duplicates

        return redirect()->route('books.show', $book->slug)->with('toast', [
            'type' => 'success',
            'message' => 'Book added to cart.',
        ]);
    }

    public function destroy(Book $book): RedirectResponse
    {
        $cart = Session::get('cart', []);
        // Remove the book ID from the array
        Session::put('cart', array_diff($cart, [$book->id]));

        return redirect()->route('cart.index')->with('toast', [
            'type' => 'success',
            'message' => 'Book removed from cart.',
        ]);
    }

    public function clear(): RedirectResponse
    {
        Session::forget('cart');

        return redirect()->route('cart.index')->with('toast', [
            'type' => 'success',
            'message' => 'Cart cleared successfully.',
        ]);
    }
}
