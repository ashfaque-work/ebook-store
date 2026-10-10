<?php

use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayPaymentGateway;

/**
 * The demonstration checkout: the simulated gateway, run in production on
 * purpose, with every price labelled as charging nothing.
 *
 * What these pin down is the pair of ways it could do harm — running without
 * saying so, and running alongside a gateway that does take money.
 */
function rebindGateway(): void
{
    app()->forgetInstance(PaymentGateway::class);
}

test('production still refuses the simulated gateway by default', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('services.razorpay', ['key' => '', 'secret' => '']);
    config()->set('store.demo_payments', false);
    rebindGateway();

    expect(fn () => app(PaymentGateway::class))
        ->toThrow(RuntimeException::class, 'STORE_DEMO_PAYMENTS');
});

test('production runs the simulated gateway when it is asked for in so many words', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('services.razorpay', ['key' => '', 'secret' => '']);
    config()->set('store.demo_payments', true);
    rebindGateway();

    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

test('real keys still win, whatever the demo flag says', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('services.razorpay', ['key' => 'rzp_live_x', 'secret' => 'shh', 'webhook_secret' => 'w']);
    config()->set('store.demo_payments', true);
    rebindGateway();

    expect(app(PaymentGateway::class))->toBeInstanceOf(RazorpayPaymentGateway::class);
});

test('preflight refuses a live gateway behind a demonstration notice', function () {
    config()->set('store.payments_enabled', true);
    config()->set('services.razorpay', ['key' => 'rzp_live_x', 'secret' => 'shh', 'webhook_secret' => 'w']);
    config()->set('store.demo_payments', true);
    rebindGateway();

    $this->artisan('store:preflight')
        ->expectsOutputToContain('STORE_DEMO_PAYMENTS is on with live gateway keys')
        ->assertFailed();
});

test('the notice is shared with every page, so no page with a price can miss it', function () {
    config()->set('store.demo_payments', true);

    $this->get('/')->assertInertia(fn ($page) => $page->where('store.demoPayments', true));
});

test('it is off unless switched on', function () {
    expect(config('store.demo_payments'))->toBeFalse();

    $this->get('/')->assertInertia(fn ($page) => $page->where('store.demoPayments', false));
});
