<?php

use App\Models\User;

test('a customer cannot access the admin panel', function () {
    $customer = User::factory()->create(); // role defaults to customer

    $this->actingAs($customer)
        ->get('/admin/authors')
        ->assertForbidden();
});

test('an admin can access the admin panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/authors')
        ->assertOk();
});

test('a guest is redirected to login from the admin panel', function () {
    $this->get('/admin/authors')->assertRedirect('/login');
});

test('registration never grants the admin role', function () {
    $this->post('/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin', // attempted mass-assignment
    ]);

    expect(User::where('email', 'sneaky@example.com')->first()->isAdmin())->toBeFalse();
});
