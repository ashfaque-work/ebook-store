<?php

use App\Http\Controllers\LegalController;

/**
 * Razorpay and every other Indian gateway require these pages to be live and
 * reachable before they will activate an account, so a broken policy page is a
 * revenue outage rather than a cosmetic problem.
 */
test('every policy page is publicly reachable', function (string $page, string $component) {
    $this->get("/{$page}")
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia->component($component));
})->with(fn () => collect(LegalController::PAGES)
    ->map(fn ($meta, $page) => [$page, $meta['component']])
    ->values()
    ->all()
);

test('policy pages do not require an account', function () {
    $this->assertGuest();

    foreach (array_keys(LegalController::PAGES) as $page) {
        $this->get("/{$page}")->assertOk();
    }
});

test('an unknown policy page is a 404, not a catch-all', function () {
    $this->get('/not-a-policy')->assertNotFound();
});

test('the policy routes do not shadow the rest of the store', function () {
    // The pages are served from a `/{page}` route; it must not swallow /cart.
    $this->get('/cart')->assertOk()
        ->assertInertia(fn ($inertia) => $inertia->component('Cart/Index'));
});

test('business identity is shared with the frontend', function () {
    $this->get('/contact')->assertInertia(fn ($inertia) => $inertia
        ->has('store', fn ($store) => $store
            ->hasAll([
                'legalName', 'tradingName', 'supportEmail', 'address',
                'jurisdiction', 'refundWindowDays', 'refundProcessingDays',
            ])
            ->etc()
        )
    );
});

test('the refund policy the pages describe matches the configured one', function () {
    // The prose reads these values, so a config change can never leave the
    // published policy saying something the code does not do.
    config()->set('store.refund_window_days', 14);

    $this->get('/refunds')->assertInertia(fn ($inertia) => $inertia
        ->where('store.refundWindowDays', 14)
    );
});
