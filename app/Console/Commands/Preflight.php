<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Everything that quietly ruins a launch, checked in one place.
 *
 * Most of these fail silently in production rather than throwing: a store
 * branded "Laravel", covers pointing at the wrong origin, a gateway that
 * takes no money, receipts going to a log file nobody reads. Each one is
 * cheap to check and expensive to discover from a customer.
 */
class Preflight extends Command
{
    protected $signature = 'store:preflight';

    protected $description = 'Check the configuration before going live';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->info('Pre-flight checks');
        $this->newLine();

        $this->checkApp();
        $this->checkDatabase();
        $this->checkStorage();
        $this->checkPayments();
        $this->checkMail();
        $this->checkStoreIdentity();
        $this->checkAssets();

        $this->newLine();

        if ($this->failures > 0) {
            $this->error("{$this->failures} problem(s) would break the store. Fix these before launch.");

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->warn("{$this->warnings} thing(s) worth a look, but nothing blocking.");

            return self::SUCCESS;
        }

        $this->info('All clear.');

        return self::SUCCESS;
    }

    private function pass(string $message): void
    {
        $this->line("  <fg=green>OK</>    {$message}");
    }

    private function caution(string $message, string $fix): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow>WARN</>  {$message}");
        $this->line("        <fg=gray>{$fix}</>");
    }

    private function problem(string $message, string $fix): void
    {
        $this->failures++;
        $this->line("  <fg=red>FAIL</>  {$message}");
        $this->line("        <fg=gray>{$fix}</>");
    }

    private function checkApp(): void
    {
        $this->line('<options=bold>Application</>');

        $production = app()->environment('production');

        $production
            ? $this->pass('APP_ENV is production')
            : $this->caution('APP_ENV is '.app()->environment(), 'Set APP_ENV=production on the live host.');

        if (config('app.debug')) {
            $production
                ? $this->problem('APP_DEBUG is on', 'A stack trace on a payment error leaks your gateway keys. Set APP_DEBUG=false.')
                : $this->pass('APP_DEBUG is on (fine outside production)');
        } else {
            $this->pass('APP_DEBUG is off');
        }

        empty(config('app.key'))
            ? $this->problem('APP_KEY is empty', 'Run php artisan key:generate.')
            : $this->pass('APP_KEY is set');

        $url = (string) config('app.url');

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            $production
                ? $this->problem("APP_URL is {$url}", 'Cover URLs, the sitemap and canonical links all derive from this.')
                : $this->pass("APP_URL is {$url}");
        } elseif ($production && ! str_starts_with($url, 'https://')) {
            $this->problem("APP_URL is not https ({$url})", 'Razorpay and the browser both expect TLS in production.');
        } else {
            $this->pass("APP_URL is {$url}");
        }
    }

    private function checkDatabase(): void
    {
        $this->line('<options=bold>Database</>');

        try {
            DB::connection()->getPdo();
            $this->pass('Connected ('.DB::getDriverName().')');
        } catch (Throwable $e) {
            $this->problem('Cannot connect: '.$e->getMessage(), 'Check the DB_* values.');

            return;
        }

        try {
            $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
                ->keys()
                ->diff(app('migrator')->getRepository()->getRan())
                ->count();

            $pending === 0
                ? $this->pass('No pending migrations')
                : $this->problem("{$pending} migration(s) not run", 'Run php artisan migrate --force.');
        } catch (Throwable) {
            $this->problem('Migrations have never run', 'Run php artisan migrate --force.');
        }
    }

    private function checkStorage(): void
    {
        $this->line('<options=bold>Storage</>');

        foreach (['private books' => Book::fileDisk(), 'public covers' => Book::coverDisk()] as $label => $disk) {
            $driver = config("filesystems.disks.{$disk}.driver");

            try {
                Storage::disk($disk)->exists('preflight-probe');
                $this->pass("{$label}: '{$disk}' reachable ({$driver})");
            } catch (Throwable $e) {
                $this->problem("{$label}: '{$disk}' unreachable", $e->getMessage());
            }
        }

        // The trap that loses paid-for books to a routine redeploy.
        if (app()->environment('production') && config('filesystems.disks.'.Book::fileDisk().'.driver') === 'local') {
            $this->problem(
                'Books are on the local disk in production',
                'Free hosts rebuild the container on every deploy and take storage/app with it. '
                .'Set PRIVATE_DISK/PUBLIC_DISK to object storage and run books:migrate-storage first.',
            );
        }
    }

    private function checkPayments(): void
    {
        $this->line('<options=bold>Payments</>');

        // A deliberate hold during KYC, not a misconfiguration: checkout is
        // closed, so an absent gateway is expected rather than alarming.
        if (! config('store.payments_enabled')) {
            $this->caution(
                'Selling is switched off (STORE_PAYMENTS_ENABLED=false)',
                'Correct while Razorpay reviews the account. Set it to true once live keys are in.',
            );

            return;
        }

        try {
            $gateway = app(PaymentGateway::class);
        } catch (Throwable $e) {
            $this->problem('No gateway configured', $e->getMessage());

            return;
        }

        if ($gateway instanceof FakePaymentGateway) {
            app()->environment('production')
                ? $this->problem('The simulated gateway is active', 'It takes no money. Set RAZORPAY_KEY and RAZORPAY_SECRET.')
                : $this->pass('Simulated gateway (correct outside production)');

            return;
        }

        $this->pass('Razorpay configured');

        empty(config('services.razorpay.webhook_secret'))
            ? $this->problem(
                'RAZORPAY_WEBHOOK_SECRET is empty',
                'Every webhook will be rejected, so a customer who closes the tab mid-payment never gets their book.',
            )
            : $this->pass('Webhook secret set');

        if (str_starts_with((string) config('services.razorpay.key'), 'rzp_test_')) {
            $this->caution('Using Razorpay TEST keys', 'Fine for a rehearsal; live keys start rzp_live_.');
        }
    }

    private function checkMail(): void
    {
        $this->line('<options=bold>Mail</>');

        $mailer = config('mail.default');

        if ($mailer === 'log' && app()->environment('production')) {
            $this->problem('MAIL_MAILER is log', 'Receipts would be written to a log file instead of sent.');
        } else {
            $this->pass("Mailer is {$mailer}");
        }

        $from = (string) config('mail.from.address');

        str_contains($from, 'example.com')
            ? $this->caution("MAIL_FROM_ADDRESS is {$from}", 'Receipts from example.com land in spam.')
            : $this->pass("Sending from {$from}");
    }

    private function checkStoreIdentity(): void
    {
        $this->line('<options=bold>Store identity</>');

        $placeholders = [
            'STORE_LEGAL_NAME' => ['store.legal_name', 'Your Registered Business Name'],
            'STORE_SUPPORT_EMAIL' => ['store.support_email', 'support@example.com'],
        ];

        foreach ($placeholders as $key => [$path, $placeholder]) {
            config($path) === $placeholder
                ? $this->problem("{$key} is still the placeholder", 'It is printed on the legal pages, which Razorpay checks during KYC.')
                : $this->pass("{$key} set");
        }

        foreach (['store.jurisdiction' => 'STORE_JURISDICTION', 'store.address.city' => 'STORE_CITY'] as $path => $key) {
            empty(config($path))
                ? $this->caution("{$key} is empty", 'It appears on the Terms and Contact pages.')
                : $this->pass("{$key} set");
        }

        if (config('store.gst_enabled') && empty(config('store.gstin'))) {
            $this->problem('GST is on but STORE_GSTIN is empty', 'Do not charge tax without a GSTIN.');
        }
    }

    private function checkAssets(): void
    {
        $this->line('<options=bold>Assets</>');

        file_exists(public_path('build/manifest.json'))
            ? $this->pass('Front-end assets are built')
            : $this->problem('public/build/manifest.json is missing', 'Run npm run build.');
    }
}
