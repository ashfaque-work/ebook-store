<?php

use App\Http\Controllers\Admin\AuthorController as AdminAuthorController;
use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', HomeController::class)->name('home');
Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');

// Cart Routes
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{book}', [CartController::class, 'store'])->name('cart.store');
Route::delete('/cart/{book}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

// Authenticated User Routes
Route::get('/dashboard', function () {
    // Send admins to the admin panel; customers to their library.
    return auth()->user()->isAdmin()
        ? redirect()->route('admin.authors.index')
        : redirect()->route('library.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Checkout (mock payment) — must be a verified, authenticated user.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
});

// Customer library (purchased books + gated downloads).
Route::middleware('auth')->group(function () {
    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{book}/download', [LibraryController::class, 'download'])->name('library.download');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

// Admin Routes
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('authors', AdminAuthorController::class);
    Route::resource('genres', AdminGenreController::class);
    Route::resource('books', AdminBookController::class);
});

require __DIR__.'/auth.php';
