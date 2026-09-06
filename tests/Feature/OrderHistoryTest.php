<?php

use App\Models\Book;
use App\Models\User;

test('a user sees only their own orders', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $book = Book::factory()->create();

    completeCheckout($user, [$book->id]);
    completeCheckout($other, [$book->id]);

    $this->actingAs($user)
        ->get('/orders')
        ->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 1)
        );
});

test('a user cannot view another user\'s order', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $book = Book::factory()->create();

    completeCheckout($other, [$book->id]);
    $order = $other->orders()->first();

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

test('a guest cannot see orders', function () {
    $this->get('/orders')->assertRedirect('/login');
});
