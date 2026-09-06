<?php

use App\Models\Book;

test('security headers are on every response', function () {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
});

test('the content security policy only applies in production', function () {
    // Locally the debug page is worth more than the policy.
    $this->get('/')->assertHeaderMissing('Content-Security-Policy');

    config()->set('app.env', 'production');

    $this->get('/')->assertHeader('Content-Security-Policy');
});

test('the policy lets the payment gateway open', function () {
    config()->set('app.env', 'production');

    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    // Get this wrong and Razorpay's checkout silently fails to open with
    // nothing in the console explaining why.
    expect($csp)->toContain('https://checkout.razorpay.com')
        ->toContain('frame-src https://api.razorpay.com');
});

test('the policy lets the reader work', function () {
    config()->set('app.env', 'production');

    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    // pdf.js needs blob: for rendering; epub.js renders chapters in an iframe.
    expect($csp)->toContain('img-src')
        ->toContain('blob:')
        ->toContain("worker-src 'self' blob:")
        ->toContain("child-src 'self' blob:");
});

test('the policy allows the font host we actually use', function () {
    config()->set('app.env', 'production');

    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain('https://fonts.bunny.net');
});

test('an asset origin is added to the policy when one is configured', function () {
    config()->set('app.env', 'production');
    config()->set('store.asset_origin', 'https://cdn.example.com');

    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain('img-src \'self\' https://cdn.example.com');
});

test('storage disks are configurable rather than hardcoded', function () {
    // Free hosting rebuilds the container on every deploy and takes
    // storage/app with it, so production has to point elsewhere.
    expect(Book::fileDisk())->toBe('local')
        ->and(Book::coverDisk())->toBe('public');

    config()->set('store.disks.private', 'r2');
    config()->set('store.disks.public', 'r2-public');

    expect(Book::fileDisk())->toBe('r2')
        ->and(Book::coverDisk())->toBe('r2-public');
});

test('the object storage disks are defined', function () {
    expect(config('filesystems.disks.r2.driver'))->toBe('s3')
        ->and(config('filesystems.disks.r2.visibility'))->toBe('private')
        ->and(config('filesystems.disks.r2-public.visibility'))->toBe('public');
});

test('the storage migration refuses to run against itself', function () {
    // Source and destination are both `local` until the env says otherwise.
    $this->artisan('books:migrate-storage')
        ->expectsOutputToContain('Nothing to do')
        ->assertSuccessful();
});

test('the health check responds', function () {
    // Render and the uptime monitor both poll this.
    $this->get('/up')->assertOk();
});
