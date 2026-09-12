<?php

/**
 * The host terminates TLS at its own proxy, so the application itself only
 * ever sees plain HTTP. Everything it generates — canonical links, the
 * sitemap, Open Graph tags, the gateway's callback URL — has to come out as
 * https anyway, and that depends entirely on the forwarded headers being
 * trusted. Untrusted, nothing errors; the URLs are just quietly wrong.
 */
test('a forwarded https scheme is honoured', function () {
    $this->get('/sitemap.xml', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertSee('https://', false)
        ->assertDontSee('<loc>http://', false);
});

test('generated urls use https behind the proxy', function () {
    $this->get('/', ['X-Forwarded-Proto' => 'https'])->assertOk();

    expect(url('/books'))->toStartWith('https://');
});

test('the forwarded host is honoured, so links point at the real domain', function () {
    $this->get('/sitemap.xml', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'books.example.in',
    ])
        ->assertOk()
        ->assertSee('https://books.example.in', false);
});

test('without the header nothing is forced, so local http still works', function () {
    // Trusting the proxy must not mean pretending every request is secure.
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('http://', false);
});
