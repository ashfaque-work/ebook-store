<?php

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;

/**
 * This audience arrives from WhatsApp and Instagram. A book link that renders
 * as a bare URL costs more than any ranking ever would.
 */
test('a book page carries everything a link preview needs', function () {
    $book = Book::factory()->create([
        'title' => 'The Unquiet House',
        'description' => 'A novel about the hour of the unexpected guest.',
    ]);

    $response = $this->get(route('books.show', $book));

    $response->assertSee('property="og:title"', false)
        ->assertSee($book->title, false)
        ->assertSee('property="og:type" content="book"', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('rel="canonical" href="'.route('books.show', $book).'"', false)
        ->assertSee('A novel about the hour of the unexpected guest.', false);
});

test('a book page emits structured data', function () {
    $book = Book::factory()->create(['price_paise' => 29900, 'page_count' => 312]);

    $response = $this->get(route('books.show', $book));

    $response->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Book"', false)
        ->assertSee('"bookFormat":"https://schema.org/EBook"', false)
        // Price in the structured data is rupees, not paise.
        ->assertSee('"price":"299.00"', false)
        ->assertSee('"priceCurrency":"INR"', false);
});

test('ratings are not fabricated before there are reviews', function () {
    $book = Book::factory()->create();

    // An invented aggregateRating is a manual-action risk.
    $this->get(route('books.show', $book))->assertDontSee('aggregateRating', false);
});

test('a draft book tells crawlers to stay away', function () {
    $draft = Book::factory()->unpublished()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('books.show', $draft))
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('search results are not indexed', function () {
    Book::factory()->create(['title' => 'Salt and Monsoon']);

    // Assert on the tag, not the bare word: Inertia serialises the whole prop
    // bag into data-page, so "noindex":false appears either way.
    $robots = '<meta name="robots" content="noindex, nofollow">';

    // One of infinitely many query strings; the catalogue is the page that
    // deserves to rank.
    $this->get('/?search=Salt')->assertSee($robots, false);

    $this->get('/')->assertDontSee($robots, false);
});

test('policy pages describe themselves', function () {
    $this->get('/refunds')
        ->assertSee('Refunds &amp; Cancellations', false)
        ->assertSee('rel="canonical" href="'.route('legal', 'refunds').'"', false);
});

test('the sitemap lists published books, samples and policies', function () {
    $live = Book::factory()->create(['sample_path' => 'samples/one.pdf']);
    $draft = Book::factory()->unpublished()->create();

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/xml');

    $response->assertSee(route('books.show', $live), false)
        // A sample is a real page of real prose on our own domain — the best
        // SEO asset a store this size has.
        ->assertSee(route('reader.sample', $live), false)
        ->assertSee(route('legal', 'terms'), false)
        ->assertSee(route('home', ['genre' => Genre::first()->slug]), false)
        ->assertDontSee(route('books.show', $draft), false);
});

test('the sitemap is valid xml', function () {
    Book::factory()->count(3)->create();

    $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('urlset');
});

test('robots keeps crawlers out of private pages and points at the sitemap', function () {
    $response = $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8');

    $response->assertSee('Disallow: /library')
        ->assertSee('Disallow: /orders')
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /webhooks')
        ->assertSee('Sitemap: '.route('sitemap'));
});

test('the sample reader stays crawlable', function () {
    // Deliberately absent from the disallow list: samples are the funnel.
    $this->get('/robots.txt')->assertDontSee('Disallow: /read/*/sample');
});
