<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Turn an existing account into an administrator.
 *
 * The first admin has to come from somewhere, and the alternatives are worse:
 * a seeder that ships a known password, or a registration form that hands out
 * the admin role to whoever signs up first. This promotes an account that a
 * real person already created with a password nobody else has seen.
 *
 * `role` is deliberately not mass assignable on the model, so this is the only
 * supported way in.
 */
class PromoteAdmin extends Command
{
    protected $signature = 'store:promote-admin {email} {--demote : Remove admin rights instead}';

    protected $description = 'Grant or revoke admin rights for an existing account';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::firstWhere('email', $email);

        if (! $user) {
            $this->error("No account with the email {$email}.");
            $this->line('Register on the site first, then run this.');

            return self::FAILURE;
        }

        if ($this->option('demote')) {
            $user->role = User::ROLE_CUSTOMER;
            $user->save();

            $this->info("{$email} is no longer an admin.");

            return self::SUCCESS;
        }

        $user->role = User::ROLE_ADMIN;

        // The admin panel is behind `verified`, and on a fresh deploy the
        // verification mail may have gone to a log file. Promoting someone is
        // a stronger statement about the address than a clicked link anyway.
        if (! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
            $this->line('  Marked the email verified — /admin is gated on it.');
        }

        $user->save();

        $this->info("{$email} is now an admin.");

        return self::SUCCESS;
    }
}
