<?php

namespace App\Console\Commands;

use App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Send one email and say plainly what happened.
 *
 * The same check as the admin page, for anywhere there is a shell.
 */
class TestMail extends Command
{
    protected $signature = 'store:test-mail {email : Where to send it}';

    protected $description = 'Send a test email and report the result';

    public function handle(): int
    {
        $to = (string) $this->argument('email');
        $mailer = (string) config('mail.default');

        $this->line("Mailer: {$mailer}");
        $this->line('From:   '.config('mail.from.address'));

        if ($mailer === 'log') {
            $this->warn("MAIL_MAILER is 'log' — this will be written to a log file, not sent.");
        }

        try {
            Mail::to($to)->send(new TestEmail(
                (string) config('store.trading_name', config('app.name')),
                $mailer,
            ));
        } catch (Throwable $e) {
            // The provider's own words: "535 Username and Password not
            // accepted" names the thing to fix; "could not send" does not.
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Handed to the {$mailer} mailer for {$to}.");

        if ($mailer !== 'log') {
            $this->line('  If it does not arrive, check spam, then SPF and DKIM.');
        }

        return self::SUCCESS;
    }
}
