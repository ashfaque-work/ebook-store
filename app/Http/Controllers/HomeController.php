<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     * This is the special __invoke method that makes the controller "invokable".
     */
    public function __invoke(Request $request): Response
    {
        // Fetch all books, along with their author and genre relationships
        $books = Book::with(['author', 'genre'])->latest()->get();

        // Render the Welcome.vue component and pass the books data to it
        return Inertia::render('Welcome', [
            'books' => $books
        ]);
    }
}

