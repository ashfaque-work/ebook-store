<?php

/**
 * An apostrophe inside a single-quoted @section argument ends the string early,
 * and Blade then prints the directive itself instead of the page. Each error
 * view is rendered directly so a broken one fails here, not in production.
 */
test('every error page renders its title instead of raw blade', function (int $code) {
    // The exception handler registers the errors:: namespace the layout uses.
    (new \Illuminate\Foundation\Exceptions\RegisterErrorViewPaths)();

    $html = view("errors.{$code}")->render();

    expect($html)
        ->not->toContain('@section')
        ->not->toContain('@endsection')
        ->toContain("<span class=\"code\">{$code}</span>");
})->with([403, 404, 419, 429, 500, 503]);

test('a missing page shows the friendly 404', function () {
    $this->get('/books/no-such-book-anywhere')
        ->assertNotFound()
        ->assertSee('We can’t find that page', escape: false)
        ->assertDontSee('@section', escape: false);
});
