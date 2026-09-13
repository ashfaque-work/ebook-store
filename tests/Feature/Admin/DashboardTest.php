<?php

use App\Models\Book;
use App\Models\Order;
use App\Models\User;

test('an admin lands on the dashboard, not the authors list', function () {
    actingAsAdmin();

    $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
});

test('the dashboard is admin-only', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('takings come from paid orders only', function () {
    $customer = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 19900]);

    completeCheckout($customer, [$book->id]);

    // A pending order is money that has not arrived, and must not be counted.
    Order::create([
        'user_id' => $customer->id,
        'order_number' => 'ORD-PENDING-1',
        'status' => Order::STATUS_PENDING,
        'subtotal_paise' => 50000,
        'total_paise' => 50000,
    ]);

    actingAsAdmin();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('takings.grossPaise', 19900)
            ->where('takings.paidOrders', 1)
            ->where('takings.awaitingPayment', 1)
        );
});

test('an empty shop reports zeroes rather than breaking', function () {
    actingAsAdmin();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('takings.grossPaise', 0)
            ->where('takings.paidOrders', 0)
            // No prior period to compare with: a percentage change from zero
            // is not a fact, so it is null rather than 0 or 100.
            ->where('takings.changePercent', null)
        );
});

test('the catalogue counts what needs attention', function () {
    Book::factory()->count(2)->create(['is_published' => true, 'cover_image_path' => null]);
    Book::factory()->create(['is_published' => false]);
    Book::factory()->create(['is_published' => true, 'cover_image_path' => 'covers/x.jpg', 'page_count' => 300]);

    actingAsAdmin();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('catalogue.published', 3)
            ->where('catalogue.drafts', 1)
            ->where('catalogue.missingCovers', 2)
        );
});

test('best sellers and recent orders load as deferred props', function () {
    $customer = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 9900]);

    completeCheckout($customer, [$book->id]);

    actingAsAdmin();

    // Deferred props never run in an ordinary visit, so the query behind them
    // is only exercised by asking for them the way the browser does.
    $page = inertiaPartial(route('admin.dashboard'), 'Admin/Dashboard', ['topBooks', 'recentOrders']);

    $page->assertOk();

    expect($page->json('props.topBooks'))->toHaveCount(1)
        ->and($page->json('props.topBooks.0.title'))->toBe($book->title)
        ->and($page->json('props.topBooks.0.sold'))->toBe(1)
        ->and($page->json('props.recentOrders'))->toHaveCount(1);
});

test('a book nobody bought is not a best seller', function () {
    Book::factory()->count(3)->create();

    actingAsAdmin();

    $page = inertiaPartial(route('admin.dashboard'), 'Admin/Dashboard', ['topBooks']);

    expect($page->json('props.topBooks'))->toBe([]);
});
