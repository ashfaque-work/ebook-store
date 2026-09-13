<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * The reader fetches the book with XHR from its own page.
 *
 * That makes a redirect to object storage a trap: the target has to be in the
 * page's connect-src and has to answer with CORS headers, and when it does
 * neither the request dies silently — no error, no log, a reader left staring
 * at "Opening the book…". These tests pin the response to this origin on every
 * disk, because the bug lived in a branch that only production ever took.
 */
function ownedBook(User $user, array $attributes = []): Book
{
    $book = Book::factory()->free()->create($attributes);
    Storage::disk(Book::fileDisk())->put($book->getRawOriginal('file_path'), 'BOOK-BYTES');

    test()->actingAs($user)->post(route('books.claim', $book));

    return $book;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('s3');
});

test('the book is served from this origin, not redirected away', function () {
    $user = User::factory()->create();
    $book = ownedBook($user);

    $response = $this->actingAs($user)->get(route('reader.asset', $book->slug));

    $response->assertOk();
    expect($response->headers->get('Location'))->toBeNull();
});

test('it is still served from this origin on object storage', function () {
    // The branch that broke. A presigned redirect passes every server-side
    // check and fails only in the browser.
    config()->set('store.disks.private', 's3');

    $user = User::factory()->create();
    $book = ownedBook($user);

    $response = $this->actingAs($user)->get(route('reader.asset', $book->slug));

    $response->assertOk();
    expect($response->headers->get('Location'))->toBeNull();
    expect($response->streamedContent())->toBe('BOOK-BYTES');
});

test('the bytes and content type are right', function () {
    $user = User::factory()->create();
    $book = ownedBook($user, ['file_format' => 'epub']);

    $response = $this->actingAs($user)->get(route('reader.asset', $book->slug));

    expect($response->streamedContent())->toBe('BOOK-BYTES')
        ->and($response->headers->get('Content-Type'))->toContain('epub');
});

test('somebody else\'s book is refused', function () {
    $owner = User::factory()->create();
    $book = ownedBook($owner);
    $stranger = User::factory()->create();

    // Serving from our own origin must not become serving to anyone.
    $this->actingAs($stranger)->get(route('reader.asset', $book->slug))->assertForbidden();
});

test('a guest is refused', function () {
    $user = User::factory()->create();
    $book = ownedBook($user);

    signOut();

    $this->get(route('reader.asset', $book->slug))->assertRedirect(route('login'));
});

test('a missing file is a 404 rather than an empty book', function () {
    $user = User::factory()->create();
    $book = ownedBook($user);

    Storage::disk(Book::fileDisk())->delete($book->getRawOriginal('file_path'));

    $this->actingAs($user)->get(route('reader.asset', $book->slug))->assertNotFound();
});

test('the content security policy allows the reader to fetch it', function () {
    // The policy is only attached in production, which is exactly where this
    // bug lived.
    config()->set('app.env', 'production');

    $user = User::factory()->create();
    $book = ownedBook($user);

    $csp = $this->actingAs($user)
        ->get(route('reader.show', $book->slug))
        ->headers->get('Content-Security-Policy');

    // Same origin, so 'self' is the whole requirement — and it must stay that
    // way, because no object-storage host is listed here.
    expect($csp)->toContain("connect-src 'self'");
});
