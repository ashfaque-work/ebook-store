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
use App\Http\Controllers\Reader\AnnotationController;
use App\Http\Controllers\Reader\ReaderController;
use App\Http\Controllers\SimulatedGatewayController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', HomeController::class)->name('home');
Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');

// Crawlability. Generated rather than stored: a stale sitemap is worse than
// none, and robots.txt has to know the real APP_URL.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', fn () => response()
    ->view('robots')
    ->header('Content-Type', 'text/plain'))->name('robots');

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

    // The simulated gateway's checkout. 404s unless the mock gateway is the
    // one bound, so it is registered unconditionally and routes stay cacheable.
    Route::post('/checkout/{order}/simulate', SimulatedGatewayController::class)->name('checkout.simulate');
});

// The gateway's own report of what happened, and the source of truth for
// payment. Signature-verified and idempotent; no session, no CSRF token.
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.razorpay');

// Samples are open on purpose: a reader who finishes a free chapter converts
// far better than one reading a blurb, and a signup wall in front of that is
// the wrong trade.
Route::get('/read/{book:slug}/sample', [ReaderController::class, 'sample'])->name('reader.sample');
Route::get('/read/{book:slug}/sample/asset', [ReaderController::class, 'sampleAsset'])
    ->middleware('throttle:60,1')
    ->name('reader.sample.asset');

// Reading a book you own.
Route::middleware('auth')->group(function () {
    Route::get('/read/{book:slug}', [ReaderController::class, 'show'])->name('reader.show');
    Route::get('/read/{book:slug}/asset', [ReaderController::class, 'asset'])
        ->middleware('throttle:120,1')
        ->name('reader.asset');
    Route::post('/read/{book}/progress', [ReaderController::class, 'progress'])->name('reader.progress');

    Route::post('/read/{book}/bookmarks', [AnnotationController::class, 'storeBookmark'])->name('reader.bookmarks.store');
    Route::delete('/read/{book}/bookmarks/{bookmark}', [AnnotationController::class, 'destroyBookmark'])->name('reader.bookmarks.destroy');

    Route::post('/read/{book}/highlights', [AnnotationController::class, 'storeHighlight'])->name('reader.highlights.store');
    Route::patch('/read/{book}/highlights/{highlight}', [AnnotationController::class, 'updateHighlight'])->name('reader.highlights.update');
    Route::delete('/read/{book}/highlights/{highlight}', [AnnotationController::class, 'destroyHighlight'])->name('reader.highlights.destroy');
});

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
