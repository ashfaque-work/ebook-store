<?php

use App\Mail\TestEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('the mail page is admin-only', function () {
    $this->get('/admin/mail')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get('/admin/mail')->assertForbidden();
});

test('it reports what the store is configured to do', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', 'smtp.gmail.com');
    config()->set('mail.from.address', 'shop@example.org');

    actingAsAdmin();

    $this->get('/admin/mail')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Mail')
            ->where('config.mailer', 'smtp')
            ->where('config.host', 'smtp.gmail.com')
        );
});

test('the password is never sent to the browser', function () {
    config()->set('mail.mailers.smtp.password', 'hunter2-app-password');
    config()->set('mail.mailers.smtp.username', 'someone@gmail.com');

    actingAsAdmin();

    $page = $this->get('/admin/mail');

    // Whether one is set is useful; what it is, is not.
    $page->assertInertia(fn ($p) => $p->where('config.hasPassword', true))
        ->assertDontSee('hunter2-app-password')
        ->assertDontSee('someone@gmail.com');
});

test('log mailer is reported as a problem, because nothing is sent', function () {
    config()->set('mail.default', 'log');

    actingAsAdmin();

    $this->get('/admin/mail')
        ->assertInertia(fn ($page) => $page->where('problems', fn ($problems) => collect($problems)
            ->contains(fn ($line) => str_contains($line, 'log'))));
});

test('a Gmail account sending as another address is flagged', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', 'smtp.gmail.com');
    config()->set('mail.mailers.smtp.username', 'me@gmail.com');
    config()->set('mail.from.address', 'shop@mystore.in');

    actingAsAdmin();

    // Gmail will not send as an address the account does not own, and fails in
    // a way that looks like the mail simply vanished.
    $this->get('/admin/mail')
        ->assertInertia(fn ($page) => $page->where('problems', fn ($problems) => collect($problems)
            ->contains(fn ($line) => str_contains($line, 'shop@mystore.in'))));
});

test('sending a test actually sends one', function () {
    Mail::fake();
    config()->set('mail.default', 'smtp');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/mail/test', ['email' => 'someone@example.org'])->assertRedirect();

    Mail::assertSentCount(1);
});

test('it falls back to the admin\'s own address', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create(['email' => 'owner@example.org']);

    $this->actingAs($admin)->post('/admin/mail/test');

    Mail::assertSent(TestEmail::class, fn (TestEmail $mail) => $mail->hasTo('owner@example.org'));
});

test('a rubbish address is refused before anything is sent', function () {
    Mail::fake();
    actingAsAdmin();

    $this->post('/admin/mail/test', ['email' => 'not-an-address'])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('sending a test is admin-only', function () {
    Mail::fake();

    $this->actingAs(User::factory()->create())->post('/admin/mail/test')->assertForbidden();

    Mail::assertNothingSent();
});
