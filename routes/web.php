<?php

use App\Http\Controllers\Admin\AuthorController as AdminAuthorController;
use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', HomeController::class)->name('home');
Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');

// Policy pages. Payment gateways require these to be live before they will
// activate an account, so they are plain public routes with no dependencies.
Route::get('/{page}', [LegalController::class, 'show'])
    ->whereIn('page', array_keys(LegalController::PAGES))
    ->name('legal');

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
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Checkout. Deliberately NOT behind `verified` — making someone complete an
// email round-trip before they can pay costs sales, and the account is already
// authenticated. Verification gates the admin panel, where it matters.
Route::middleware('auth')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/{order}/pay', [CheckoutController::class, 'pay'])->name('checkout.pay');
    Route::post('/checkout/{order}/verify', [CheckoutController::class, 'verify'])->name('checkout.verify');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
});

// The gateway's own report of what happened, and the source of truth for
// payment. Signature-verified and idempotent; no session, no CSRF token.
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.razorpay');

// Customer library (purchased books + gated downloads).
Route::middleware('auth')->group(function () {
    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{book}/download', [LibraryController::class, 'download'])
        ->middleware('throttle:20,1')
        ->name('library.download');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

// Admin Routes
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('authors', AdminAuthorController::class);
    Route::resource('genres', AdminGenreController::class);
    Route::resource('books', AdminBookController::class)->except('show');

    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');
});

require __DIR__.'/auth.php';
