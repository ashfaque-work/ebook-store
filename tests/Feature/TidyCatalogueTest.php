<?php

use App\Models\Author;
use App\Models\Book;

test('a dry run reports the changes and writes nothing', function () {
    $book = Book::factory()->create([
        'title' => 'A farewell to arms',
        'description' => 'A short blurb. (This is an automatically generated summary.)',
    ]);

    $this->artisan('store:tidy-catalogue --dry-run')
        ->expectsOutputToContain('nothing written')
        ->assertSuccessful();

    expect($book->fresh()->title)->toBe('A farewell to arms')
        ->and($book->fresh()->description)->toContain('automatically generated');
});

test('it cleans titles, blurbs and author names', function () {
    $author = Author::factory()->create(['name' => 'E. M. (Edward Morgan) Forster']);

    $book = Book::factory()->for($author)->create([
        'title' => 'Eloisa : $b or, A series of original letters',
        'description' => 'A short blurb. (This is an automatically generated summary.)',
    ]);

    $this->artisan('store:tidy-catalogue')->assertSuccessful();

    expect($book->fresh()->title)->toBe('Eloisa: Or, a Series of Original Letters')
        ->and($book->fresh()->description)->toBe('A short blurb.')
        ->and($author->fresh()->name)->toBe('E. M. Forster');
});

test('running it twice changes nothing the second time', function () {
    Book::factory()->create([
        'title' => 'A farewell to arms',
        'description' => 'A short blurb. (This is an automatically generated summary.)',
    ]);

    $this->artisan('store:tidy-catalogue')->assertSuccessful();

    $this->artisan('store:tidy-catalogue')
        ->expectsOutputToContain('Nothing to tidy')
        ->assertSuccessful();
});

test('it names what it will not touch rather than guessing', function () {
    Author::factory()->create(['name' => 'Thomas, Sir Malory']);

    Book::factory()->create(['title' => 'Oliver Twist, Vol. 2 (of 3)']);
    Book::factory()->create(['title' => 'The strange case of Dr. Jekyll and Mr. Hyde']);

    $this->artisan('store:tidy-catalogue --dry-run')
        ->expectsOutputToContain('these need a person')
        ->expectsOutputToContain('Thomas, Sir Malory')
        ->expectsOutputToContain('Oliver Twist')
        ->expectsOutputToContain('The strange case of Dr. Jekyll')
        ->assertSuccessful();
});

test('a clean catalogue is left entirely alone', function () {
    $author = Author::factory()->create(['name' => 'Jane Austen']);

    $book = Book::factory()->for($author)->create([
        'title' => 'Pride and Prejudice',
        'description' => 'A novel published in 1813.',
    ]);

    $this->artisan('store:tidy-catalogue')
        ->expectsOutputToContain('Nothing to tidy')
        ->assertSuccessful();

    expect($book->fresh()->title)->toBe('Pride and Prejudice')
        ->and($author->fresh()->name)->toBe('Jane Austen');
});
