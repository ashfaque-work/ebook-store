<?php

use App\Models\Book;
use App\Models\User;

test('a user sees only their own orders', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');
    $this->actingAs($other)->withSession(['cart' => [$book->id]])->post('/checkout');

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

    $this->actingAs($other)->withSession(['cart' => [$book->id]])->post('/checkout');
    $order = $other->orders()->first();

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

test('a guest cannot see orders', function () {
    $this->get('/orders')->assertRedirect('/login');
});
