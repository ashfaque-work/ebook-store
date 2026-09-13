<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Is email actually working?
 *
 * A question with no good answer until now. The free hosting plan has no
 * shell, so `php artisan` is unavailable on the live site, and mail is the one
 * piece of the store whose failure is invisible: a receipt that never arrives
 * looks exactly like a receipt nobody looked for, and the first report comes
 * from a customer who did not get their book.
 *
 * So the check lives where the person who can fix it already is.
 */
class MailController extends Controller
{
    public function index(): Response
    {
        $mailer = (string) config('mail.default');

        return Inertia::render('Admin/Mail', [
            'config' => [
                'mailer' => $mailer,
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'scheme' => config('mail.mailers.smtp.scheme'),
                // Enough to recognise, not enough to leak.
                'username' => $this->mask((string) config('mail.mailers.smtp.username')),
                'hasPassword' => (bool) config('mail.mailers.smtp.password'),
                'fromAddress' => config('mail.from.address'),
                'fromName' => config('mail.from.name'),
            ],
            'problems' => $this->problems($mailer),
        ]);
    }

    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        // ?? before ?:, because the key is absent rather than empty when the
        // field is left out entirely, and reading a missing key is a warning
        // Laravel turns into a 500.
        $to = ($validated['email'] ?? null) ?: $request->user()->email;

        try {
            Mail::to($to)->send(new TestEmail(
                (string) config('store.trading_name', config('app.name')),
                (string) config('mail.default'),
            ));
        } catch (TransportExceptionInterface $e) {
            // Reaching the provider failed, rather than the provider refusing.
            // On a host that blocks outbound SMTP this is the only symptom,
            // and it otherwise looks like the whole site broke.
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Could not reach '.config('mail.mailers.smtp.host').' on port '
                    .config('mail.mailers.smtp.port').'. Either the credentials are wrong or this host blocks '
                    .'outbound SMTP — in which case use a provider with an HTTP API, such as Resend or Brevo. '
                    .'('.Str::limit($e->getMessage(), 160).')',
            ]);
        } catch (Throwable $e) {
            // The provider's own words. "Could not send" tells an owner
            // nothing; "535 Username and Password not accepted" tells them
            // exactly which thing to go and fix.
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Could not send: '.Str::limit($e->getMessage(), 300),
            ]);
        }

        return back()->with('toast', [
            'type' => config('mail.default') === 'log' ? 'info' : 'success',
            'message' => config('mail.default') === 'log'
                ? "Written to the log rather than sent — MAIL_MAILER is 'log'."
                : "Sent to {$to}. If it does not arrive, check spam, then SPF and DKIM.",
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function problems(string $mailer): array
    {
        $problems = [];

        if ($mailer === 'log') {
            $problems[] = "MAIL_MAILER is 'log', so nothing is actually sent — receipts and password resets go to a log file.";
        }

        if ($mailer === 'smtp' && ! config('mail.mailers.smtp.host')) {
            $problems[] = 'MAIL_HOST is empty, so sending will fail.';
        }

        if (str_contains((string) config('mail.from.address'), 'example.com')) {
            $problems[] = 'MAIL_FROM_ADDRESS is still an example.com address; mail from it lands in spam.';
        }

        $username = (string) config('mail.mailers.smtp.username');
        $from = (string) config('mail.from.address');
        $password = (string) config('mail.mailers.smtp.password');

        // Gmail shows an app password in four groups of four for readability.
        // The spaces are not part of it, and a password with them in fails
        // with the same "username and password not accepted" as a wrong one —
        // so it is worth naming rather than leaving someone to guess.
        if ($password !== '' && str_contains($password, ' ')) {
            $problems[] = 'MAIL_PASSWORD contains spaces. Gmail displays app passwords in groups of four, '
                .'but they should be entered as sixteen characters with no spaces.';
        }

        if ($password !== '' && (str_starts_with($password, '"') || str_ends_with($password, '"'))) {
            $problems[] = 'MAIL_PASSWORD starts or ends with a quotation mark. The host stores the value '
                .'literally, so the quotes have become part of the password.';
        }

        if ($mailer === 'smtp' && str_contains($username, '@gmail.') && $password !== ''
            && mb_strlen(str_replace(' ', '', $password)) !== 16) {
            $problems[] = 'A Gmail app password is exactly sixteen characters. This one is '
                .mb_strlen(str_replace(' ', '', $password)).', which suggests an account password — '
                .'Gmail always refuses those for SMTP.';
        }

        if ($mailer === 'smtp' && str_contains($username, '@gmail.') && $username !== $from) {
            // Gmail will not send as an address the account does not own; it
            // silently rewrites the sender or rejects the message outright.
            $problems[] = "Gmail will not send as {$from} when the account is {$username}. Make MAIL_FROM_ADDRESS the same address.";
        }

        return $problems;
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('•', max(3, mb_strlen($local) - 2)).($domain ? '@'.$domain : '');
    }
}
