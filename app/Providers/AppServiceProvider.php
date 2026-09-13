<?php

namespace App\Providers;

use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayPaymentGateway;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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

        $this->registerBrevoMailer();
    }

    /**
     * Teach the mailer what 'brevo' means.
     *
     * Laravel ships transports for the providers it knows about; Brevo is not
     * one of them, but Symfony has a bridge and the manager takes extensions.
     *
     * It earns its place because the host blocks outbound SMTP — a connection
     * to port 587 times out at the socket, before any password is offered — so
     * email has to leave over HTTPS or not at all. Brevo is the one that will
     * verify a single sender address rather than requiring a domain.
     */
    private function registerBrevoMailer(): void
    {
        Mail::extend('brevo', function (array $config) {
            $key = $config['key'] ?? null;

            if (! $key) {
                throw new RuntimeException(
                    'MAIL_MAILER is brevo but BREVO_API_KEY is not set, so nothing could be sent.'
                );
            }

            return (new BrevoTransportFactory)->create(new Dsn('brevo+api', 'default', $key));
        });
    }
}
