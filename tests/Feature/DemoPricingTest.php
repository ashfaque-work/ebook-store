<?php

use App\Models\Book;
use App\Services\Payments\PaymentGateway;

beforeEach(fn () => config()->set('store.demo_payments', true));

test('it refuses to price anything unless the demonstration gateway is running', function () {
    config()->set('store.demo_payments', false);

    $book = Book::factory()->create(['price_paise' => 0]);

    $this->artisan('store:demo-pricing')
        ->expectsOutputToContain('STORE_DEMO_PAYMENTS is off')
        ->assertFailed();

    expect($book->fresh()->price_paise)->toBe(0);
});

test('it prices published books that have a cover and a sample', function () {
    $sellable = Book::factory()->count(3)->create([
        'price_paise' => 0,
        'is_published' => true,
        'cover_image_path' => 'covers/x.jpg',
        'sample_path' => 'samples/x.epub',
    ]);

    $noCover = Book::factory()->create([
        'price_paise' => 0,
        'is_published' => true,
        'cover_image_path' => null,
        'sample_path' => 'samples/y.epub',
    ]);

    $this->artisan('store:demo-pricing --books=3 --paise=19900')->assertSuccessful();

    expect($sellable->map->fresh()->pluck('price_paise')->all())->each->toBe(19900)
        ->and($noCover->fresh()->price_paise)->toBe(0);
});

test('running it again prices nothing further', function () {
    Book::factory()->count(2)->create([
        'price_paise' => 0,
        'is_published' => true,
        'cover_image_path' => 'covers/x.jpg',
        'sample_path' => 'samples/x.epub',
    ]);

    $this->artisan('store:demo-pricing --books=2')->assertSuccessful();

    $this->artisan('store:demo-pricing --books=2')
        ->expectsOutputToContain('already priced')
        ->assertSuccessful();
});

test('clear puts the catalogue back to free', function () {
    $book = Book::factory()->create(['price_paise' => 19900]);

    $this->artisan('store:demo-pricing --clear')
        ->expectsOutputToContain('back to free')
        ->assertSuccessful();

    expect($book->fresh()->price_paise)->toBe(0);
});

test('preflight asks about priced titles once the gateway is real', function () {
    config()->set('store.demo_payments', false);
    config()->set('store.payments_enabled', true);
    config()->set('services.razorpay', ['key' => 'rzp_live_x', 'secret' => 'shh', 'webhook_secret' => 'w']);
    app()->forgetInstance(PaymentGateway::class);

    Book::factory()->create(['price_paise' => 19900]);

    $this->artisan('store:preflight')
        ->expectsOutputToContain('store:demo-pricing --clear');
});
