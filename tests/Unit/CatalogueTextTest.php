<?php

use App\Support\CatalogueText;

test('the summariser stops signing its work', function () {
    $blurb = '"Pride and Prejudice" by Jane Austen is a novel published in 1813. '
        .'(This is an automatically generated summary.)';

    expect(CatalogueText::description($blurb))
        ->toBe('"Pride and Prejudice" by Jane Austen is a novel published in 1813.');
});

test('a blurb nobody wrote stays empty rather than becoming an empty string', function () {
    expect(CatalogueText::description(null))->toBeNull()
        ->and(CatalogueText::description('   '))->toBeNull();
});

test('MARC subfield markers come out of titles', function () {
    expect(CatalogueText::title('Eloisa : $b or, A series of original letters'))
        ->toBe('Eloisa: Or, a Series of Original Letters');
});

test('a title the publisher styled is left exactly alone', function (string $title) {
    expect(CatalogueText::title($title))->toBe($title);
})->with([
    'Pride and Prejudice',
    'The Adventures of Sherlock Holmes',
    'Little Women; Or, Meg, Jo, Beth, and Amy',
    'The Man Who Was Thursday: A Nightmare',
    'Moby Dick; Or, The Whale',
    // Two words, so there is nothing to infer from.
    'Manon Lescaut',
]);

test('a title left in sentence case by the catalogue is restyled', function () {
    expect(CatalogueText::title('A farewell to arms'))->toBe('A Farewell to Arms');
});

test('restyling keeps the capitals a name or an abbreviation already has', function () {
    expect(CatalogueText::title('the collected letters of mrs. gaskell'))
        ->toBe('The Collected Letters of Mrs. Gaskell');
});

test('a title that is only half in sentence case is left for a person', function () {
    // "The strange case of Dr. Jekyll and Mr. Hyde" needs two words fixed and
    // has four proper nouns that must not be touched. A rule that is willing
    // to restyle this is a rule that will eventually restyle something it
    // should not, so it declines and `store:tidy-catalogue` reports it.
    expect(CatalogueText::title('The strange case of Dr. Jekyll and Mr. Hyde'))
        ->toBe('The strange case of Dr. Jekyll and Mr. Hyde');
});

test('a word after a colon or semicolon starts a title of its own', function () {
    expect(CatalogueText::title('Frankenstein; or, the modern prometheus'))
        ->toBe('Frankenstein; Or, the Modern Prometheus');
});

test('the last word is capitalised however minor it is', function () {
    expect(CatalogueText::title('the world we live in'))->toBe('The World We Live In');
});

test('a catalogue expansion of a forename is dropped', function () {
    expect(CatalogueText::authorName('E. M. (Edward Morgan) Forster'))->toBe('E. M. Forster')
        ->and(CatalogueText::authorName('G. K. (Gilbert Keith) Chesterton'))->toBe('G. K. Chesterton')
        ->and(CatalogueText::authorName('L. M. (Lucy Maud) Montgomery'))->toBe('L. M. Montgomery');
});

test('an author with nothing wrong with them is untouched', function () {
    expect(CatalogueText::authorName('Jane Austen'))->toBe('Jane Austen')
        ->and(CatalogueText::authorName('Charlotte Brontë'))->toBe('Charlotte Brontë');
});
