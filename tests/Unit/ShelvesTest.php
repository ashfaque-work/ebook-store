<?php

use App\Support\Shelves;

/**
 * Every input below is a real Project Gutenberg bookshelf or subject string
 * taken from the catalogue this shop imported.
 */
test('a library subject lands on a shelf somebody browses', function (array $descriptions, string $expected) {
    expect(Shelves::slugFor($descriptions))->toBe($expected);
})->with([
    'vampires' => [['Gothic Fiction', 'Vampires -- Fiction'], 'gothic-and-horror'],
    'detectives' => [['Detective and Mystery Fiction', 'Private investigators -- England'], 'mystery-and-detection'],
    'courtship' => [['Best Books Ever Listings', 'Courtship -- Fiction'], 'romance'],
    'arthur' => [['Arthurian Legends', 'Arthur, King -- Legends'], 'myth-and-fable'],
    'shakespeare' => [['British Literature', 'English drama -- Tragedies'], 'plays-and-poetry'],
    'letters' => [['Biographies', 'Authors, English -- Correspondence'], 'lives-and-letters'],
    'whaling' => [['Best Books Ever Listings', 'Whaling -- Fiction'], 'adventure-and-the-sea'],
]);

test('a shelf name nobody browses by does not become a shelf', function () {
    // This is a real one, and it used to hold exactly one book.
    expect(Shelves::slugFor(["Banned Books from Anne Haight's list"]))->toBe('classics')
        ->and(Shelves::slugFor(['Bestsellers, American, 1895-1923']))->toBe('classics');
});

test('a book described by nothing at all still lands somewhere', function () {
    expect(Shelves::slugFor([]))->toBe('classics')
        ->and(Shelves::slugFor(['', ' ']))->toBe('classics');
});

test('the more specific shelf wins where a book could sit on two', function () {
    // A ghost story is horror before it is a story; a detective novel is
    // detection before it is an adventure.
    expect(Shelves::slugFor(['Adventure', 'Ghost stories']))->toBe('gothic-and-horror')
        ->and(Shelves::slugFor(['Adventure stories', 'Detective and mystery stories']))->toBe('mystery-and-detection');
});

test('every shelf it can return is one it knows the name of', function () {
    foreach (Shelves::SHELVES as $slug => $name) {
        expect($name)->toBeString()->not->toBeEmpty()
            ->and($slug)->toMatch('/^[a-z-]+$/');
    }

    expect(Shelves::SHELVES)->toHaveKey(Shelves::DEFAULT);
});
