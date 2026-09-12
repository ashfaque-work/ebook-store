<?php

use App\Models\User;

test('an existing account can be promoted', function () {
    $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

    $this->artisan('store:promote-admin', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('promotion verifies the email, because /admin is gated on it', function () {
    $user = User::factory()->unverified()->create(['role' => User::ROLE_CUSTOMER]);

    $this->artisan('store:promote-admin', ['email' => $user->email]);

    // Otherwise the first admin on a fresh deploy is locked out of the panel,
    // waiting for a verification mail that went to a log file.
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a promoted admin can actually reach the panel', function () {
    $user = User::factory()->unverified()->create(['role' => User::ROLE_CUSTOMER]);

    // Unverified, so the `verified` middleware redirects before the admin
    // check is ever reached.
    $this->actingAs($user)->get('/admin/books')->assertRedirect(route('verification.notice'));

    $this->artisan('store:promote-admin', ['email' => $user->email]);

    $this->actingAs($user->fresh())->get('/admin/books')->assertOk();
});

test('an unknown email fails rather than creating an account', function () {
    $this->artisan('store:promote-admin', ['email' => 'nobody@example.org'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('an admin can be demoted', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->artisan('store:promote-admin', ['email' => $user->email, '--demote' => true])
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeFalse();
});

test('demoting closes the panel immediately', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->artisan('store:promote-admin', ['email' => $user->email, '--demote' => true]);

    $this->actingAs($user->fresh())->get('/admin/books')->assertForbidden();
});
