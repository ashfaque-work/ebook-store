<?php

/**
 * The example env files are the first thing a new clone copies, so a gap in
 * them is a broken first run rather than a cosmetic problem.
 *
 * Both of the bugs these guard against were real: APP_URL had no port while
 * `composer dev` serves on 8000, which made the public disk build cover URLs
 * on the wrong origin and broke every image; and two store settings were read
 * by config but listed in neither example file.
 */
function envKeys(string $path): array
{
    $keys = [];

    foreach (file(base_path($path)) as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=/', trim($line), $m)) {
            $keys[] = $m[1];
        }
    }

    return $keys;
}

/**
 * Settings this project owns, as opposed to the framework's.
 *
 * config/store.php is entirely ours. config/services.php is mostly stock
 * Laravel — Postmark, Resend, SES, Slack — none of which this application
 * uses, so only the gateway keys are checked out of it.
 */
function storeConfigEnvKeys(): array
{
    preg_match_all(
        "/env\(\s*'([A-Z][A-Z0-9_]*)'/",
        file_get_contents(config_path('store.php')),
        $matches,
    );

    return array_values(array_unique([
        ...$matches[1],
        'RAZORPAY_KEY',
        'RAZORPAY_SECRET',
        'RAZORPAY_WEBHOOK_SECRET',
    ]));
}

test('every store setting the config reads is listed in the example env', function () {
    $missing = array_diff(storeConfigEnvKeys(), envKeys('.env.example'));

    expect($missing)->toBeEmpty(
        'Read by config but missing from .env.example: '.implode(', ', $missing),
    );
});

test('the production example lists them too', function () {
    $missing = array_diff(storeConfigEnvKeys(), envKeys('.env.production.example'));

    expect($missing)->toBeEmpty(
        'Read by config but missing from .env.production.example: '.implode(', ', $missing),
    );
});

test('no key is defined twice', function (string $file) {
    $keys = envKeys($file);
    $duplicates = array_keys(array_filter(array_count_values($keys), fn ($n) => $n > 1));

    expect($duplicates)->toBeEmpty('Duplicated in '.$file.': '.implode(', ', $duplicates));
})->with(['.env.example', '.env.production.example']);

test('the example APP_URL carries the port the dev server actually uses', function () {
    $appUrl = collect(file(base_path('.env.example')))
        ->first(fn ($line) => str_starts_with(trim($line), 'APP_URL='));

    $appUrl = trim(explode('=', trim($appUrl), 2)[1]);

    // `composer dev` serves on 8000 and the README says to visit it there.
    // config/filesystems.php builds the public disk URL from APP_URL, so a
    // bare http://localhost points every cover at the wrong origin.
    expect($appUrl)->toContain(':8000');
});

test('the readme sends people to the same place APP_URL points', function () {
    $appUrl = collect(file(base_path('.env.example')))
        ->first(fn ($line) => str_starts_with(trim($line), 'APP_URL='));

    $appUrl = trim(explode('=', trim($appUrl), 2)[1]);

    expect(file_get_contents(base_path('README.md')))->toContain($appUrl);
});
