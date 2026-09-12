<?php

use App\Models\Book;

/**
 * Stand in for a built front end, and say whether we made it.
 *
 * Every check but the asset one is pure configuration. That one reads a build
 * artifact, which is gitignored — so on a developer's machine it is there and
 * in CI it is not, and the outcome of these tests would otherwise depend on
 * which of the two is running them.
 */
function withBuiltAssets(): bool
{
    if (file_exists(public_path('build/manifest.json'))) {
        return false;
    }

    @mkdir(public_path('build'), 0755, true);
    file_put_contents(public_path('build/manifest.json'), '{}');

    return true;
}

test('preflight passes once the business details are filled in', function () {
    // Everything else is already healthy locally; the placeholders are the
    // only thing standing between a fresh clone and a clean run.
    config()->set('store.legal_name', 'Real Business Pvt Ltd');
    config()->set('store.support_email', 'hello@realbusiness.in');

    $stubbed = withBuiltAssets();

    try {
        $this->artisan('store:preflight')->assertSuccessful();
    } finally {
        if ($stubbed) {
            unlink(public_path('build/manifest.json'));
        }
    }
});

test('preflight catches a front end that was never built', function () {
    config()->set('store.legal_name', 'Real Business Pvt Ltd');
    config()->set('store.support_email', 'hello@realbusiness.in');

    $manifest = public_path('build/manifest.json');
    $saved = file_exists($manifest) ? file_get_contents($manifest) : null;

    if ($saved !== null) {
        unlink($manifest);
    }

    // Without it every page is a 500, and the deploy still reports success.
    try {
        $this->artisan('store:preflight')->assertFailed();
    } finally {
        if ($saved !== null) {
            file_put_contents($manifest, $saved);
        }
    }
});

test('preflight fails a fresh clone, because the placeholders are still there', function () {
    $this->artisan('store:preflight')->assertFailed();
});

test('preflight refuses a production setup that would give books away', function () {
    // The exact shape of a deploy that looks healthy and takes no money: no
    // gateway keys, so the simulated gateway would serve real customers.
    app()->detectEnvironment(fn () => 'production');
    config()->set('services.razorpay.key', null);
    config()->set('services.razorpay.secret', null);

    $this->artisan('store:preflight')->assertFailed();
});

test('preflight catches debug mode in production', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('app.debug', true);

    // A stack trace on a payment error leaks the gateway keys.
    $this->artisan('store:preflight')->assertFailed();
});

test('preflight catches books left on an ephemeral disk', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('store.disks.private', 'local');

    // Free hosts rebuild the container on deploy; this is how paid-for books
    // disappear.
    expect(config('filesystems.disks.'.Book::fileDisk().'.driver'))->toBe('local');

    $this->artisan('store:preflight')->assertFailed();
});

test('preflight catches placeholder business details', function () {
    config()->set('store.legal_name', 'Your Registered Business Name');

    // These print on the legal pages that Razorpay reads during KYC.
    $this->artisan('store:preflight')->assertFailed();
});

test('preflight catches GST switched on without a GSTIN', function () {
    config()->set('store.legal_name', 'Real Business');
    config()->set('store.support_email', 'real@example.org');
    config()->set('store.gst_enabled', true);
    config()->set('store.gstin', '');

    $this->artisan('store:preflight')->assertFailed();
});
