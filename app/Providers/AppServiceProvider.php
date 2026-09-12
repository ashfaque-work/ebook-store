<?php

namespace App\Providers;

use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayPaymentGateway;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Razorpay whenever it is configured; the mock gateway otherwise, so
        // local development and the test suite never touch the network while
        // still exercising the same four-step flow and the same signature
        // checks. Stripe is not a practical option for a domestic Indian
        // business - see docs/04-PAYMENTS-INDIA.md.
        $this->app->singleton(PaymentGateway::class, function () {
            $config = config('services.razorpay');

            if (empty($config['key']) || empty($config['secret'])) {
                // Never in production. Without this, a deploy that forgot the
                // keys would quietly hand out free books through a gateway
                // that always says yes — and look completely healthy doing it.
                // Failing loudly at checkout is the cheaper mistake.
                if ($this->app->environment('production')) {
                    throw new RuntimeException(
                        'No payment gateway is configured. Set RAZORPAY_KEY and RAZORPAY_SECRET; '
                        .'the simulated gateway is refused in production because it takes no money.'
                    );
                }

                return new FakePaymentGateway;
            }

            return new RazorpayPaymentGateway(
                $config['key'],
                $config['secret'],
                $config['webhook_secret'] ?? '',
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
