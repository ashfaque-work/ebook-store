<?php

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\Review;
use App\Models\User;

test('a reader who owns the book can review it', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();
    completeCheckout($user, [$book->id]);

    $this->actingAs($user)
        ->post(route('reviews.store', $book), ['rating' => 5, 'title' => 'Wonderful', 'body' => 'Read it twice.'])
        ->assertRedirect();

    $review = Review::sole();

    expect($review->rating)->toBe(5)
        ->and($review->verified)->toBeTrue()
        ->and($review->book_id)->toBe($book->id);
});

test('somebody who does not own the book cannot review it', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 19900]);

    // The point of reviews here is that the reviewer actually read it. It also
    // makes the obvious spam route — sign up, rate everything — pointless.
    $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 5]);

    expect(Review::count())->toBe(0);
});

test('a guest is sent to sign in', function () {
    $book = Book::factory()->create();

    $this->post(route('reviews.store', $book), ['rating' => 5])->assertRedirect(route('login'));
});

test('a rating outside one to five is refused', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();
    completeCheckout($user, [$book->id]);

    foreach ([0, 6, -1, 99] as $rating) {
        $this->actingAs($user)
            ->post(route('reviews.store', $book), ['rating' => $rating])
            ->assertSessionHasErrors('rating');
    }

    expect(Review::count())->toBe(0);
});

test('reviewing twice edits the first review rather than failing', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();
    completeCheckout($user, [$book->id]);

    $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 3, 'title' => 'Fine']);
    $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 5, 'title' => 'Better on reread']);

    // The unique key would otherwise turn a second opinion into a 500.
    expect(Review::count())->toBe(1)
        ->and(Review::sole()->rating)->toBe(5)
        ->and(Review::sole()->title)->toBe('Better on reread');
});

test('how much had been read is recorded with the review', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();
    completeCheckout($user, [$book->id]);

    ReadingProgress::create(['user_id' => $user->id, 'book_id' => $book->id, 'percent' => 64, 'location' => 'x']);

    $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 4]);

    // A fact about the review at the time of writing, so a later refund does
    // not rewrite what it said.
    expect(Review::sole()->percent_read)->toBe(64);
});

test('a reader can delete their own review', function () {
    $user = User::factory()->create();
    $book = Book::factory()->free()->create();
    completeCheckout($user, [$book->id]);

    $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 2]);
    $this->actingAs($user)->delete(route('reviews.destroy', $book));

    expect(Review::count())->toBe(0);
});

test('one reader cannot delete another reader\'s review', function () {
    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $book = Book::factory()->free()->create();

    Review::factory()->create(['book_id' => $book->id, 'user_id' => $author->id]);

    $this->actingAs($stranger)->delete(route('reviews.destroy', $book));

    expect(Review::count())->toBe(1);
});

test('the book page shows the reviews and their spread', function () {
    $book = Book::factory()->create();
    Review::factory()->count(3)->create(['book_id' => $book->id, 'rating' => 5]);
    Review::factory()->create(['book_id' => $book->id, 'rating' => 3]);

    $page = inertiaPartial(route('books.show', $book->slug), 'Books/Show', ['reviews']);

    expect($page->json('props.reviews.count'))->toBe(4)
        ->and($page->json('props.reviews.average'))->toBe(4.5)
        // Every star from five down to one, so the chart has a shape and not
        // gaps where nobody voted.
        ->and($page->json('props.reviews.spread'))->toHaveCount(5)
        ->and($page->json('props.reviews.items'))->toHaveCount(4);
});

test('a hidden review is not shown and does not count towards the average', function () {
    $book = Book::factory()->create();
    Review::factory()->create(['book_id' => $book->id, 'rating' => 5]);
    Review::factory()->hidden()->create(['book_id' => $book->id, 'rating' => 1]);

    $page = inertiaPartial(route('books.show', $book->slug), 'Books/Show', ['reviews']);

    expect($page->json('props.reviews.count'))->toBe(1)
        ->and((float) $page->json('props.reviews.average'))->toBe(5.0)
        ->and($page->json('props.reviews.items'))->toHaveCount(1);
});

test('only a first name and an initial are published', function () {
    $book = Book::factory()->create();
    $user = User::factory()->create(['name' => 'Jaswinder Singh Bhalla']);
    Review::factory()->create(['book_id' => $book->id, 'user_id' => $user->id]);

    // A review is public forever, and nobody agreed to publish their full name
    // by buying a book.
    $page = inertiaPartial(route('books.show', $book->slug), 'Books/Show', ['reviews']);

    expect($page->json('props.reviews.items.0.name'))->toBe('Jaswinder B.');
});

test('an admin can hide a review and show it again', function () {
    $book = Book::factory()->create();
    $review = Review::factory()->create(['book_id' => $book->id]);

    actingAsAdmin();

    $this->post(route('admin.reviews.hide', $review), ['reason' => 'Spoilers']);
    expect($review->fresh()->isHidden())->toBeTrue()
        ->and($review->fresh()->hidden_reason)->toBe('Spoilers');

    $this->post(route('admin.reviews.restore', $review));
    expect($review->fresh()->isHidden())->toBeFalse();
});

test('moderation is admin-only', function () {
    $review = Review::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.reviews.hide', $review))
        ->assertForbidden();

    expect($review->fresh()->isHidden())->toBeFalse();
});

test('the moderation list can be filtered down to the critical ones', function () {
    Review::factory()->count(2)->create(['rating' => 5]);
    Review::factory()->create(['rating' => 1]);

    actingAsAdmin();

    $this->get('/admin/reviews?status=critical')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reviews/Index')
            ->has('reviews.data', 1)
            ->where('reviews.data.0.rating', 1)
        );
});

test('deleting a book takes its reviews with it', function () {
    $book = Book::factory()->create();
    Review::factory()->count(2)->create(['book_id' => $book->id]);

    $book->delete();

    expect(Review::count())->toBe(0);
});
