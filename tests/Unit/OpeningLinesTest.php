<?php

use App\Support\OpeningLines;

/**
 * Every rejected string below is one the home page actually led with before
 * this existed, taken from the samples in the live catalogue.
 */
test('it keeps whole sentences up to the budget', function () {
    $austen = 'IT is a truth universally acknowledged, that a single man in possession of a good fortune '
        .'must be in want of a wife. However little known the feelings or views of such a man may be on his '
        .'first entering a neighbourhood, this truth is so well fixed in the minds of the surrounding families, '
        .'that he is considered as the rightful property of some one or other of their daughters. '
        .'"My dear Mr. Bennet," said his lady to him one day.';

    $opening = OpeningLines::from($austen, 58);

    expect($opening)->toStartWith('IT is a truth universally acknowledged')
        ->and($opening)->toEndWith('of their daughters.')
        ->and($opening)->not->toContain('My dear Mr. Bennet');
});

test('a short opening sentence is not held against it', function () {
    $melville = 'Call me Ishmael. Some years ago—never mind how long precisely—having little or no money in '
        .'my purse, and nothing particular to interest me on shore, I thought I would sail about a little and '
        .'see the watery part of the world. It is a way I have of driving off the spleen.';

    expect(OpeningLines::from($melville))->toStartWith('Call me Ishmael.');
});

test('the author\'s own opening quotation mark is left alone', function () {
    $forster = '“The Signora had no business to do it,” said Miss Bartlett, “no business at all. She promised '
        .'us south rooms with a view close together, instead of which here are north rooms, looking into a '
        .'courtyard, and a long way apart. Oh, Lucy!” "And a Cockney, besides!" said Lucy.';

    expect(OpeningLines::from($forster))->toStartWith('“The Signora had no business');
});

test('it refuses a contents list', function () {
    $contents = 'CONTENTS TRANSLATOR’S PREFACE CRIME AND PUNISHMENT PART I CHAPTER I CHAPTER II CHAPTER III '
        .'CHAPTER IV CHAPTER V CHAPTER VI CHAPTER VII PART II CHAPTER I CHAPTER II CHAPTER III CHAPTER IV '
        .'CHAPTER V CHAPTER VI CHAPTER VII PART III CHAPTER I CHAPTER II CHAPTER III';

    expect(OpeningLines::from($contents))->toBeNull();
});

test('it refuses a transcriber\'s note', function () {
    $note = "Transcriber's Note: The cover image was created by the transcriber and is placed in the public "
        .'domain. Minor typographical errors have been corrected without note. Inconsistent spelling and '
        .'hyphenation have been retained as they appear in the original publication.';

    expect(OpeningLines::from($note))->toBeNull();
});

test('it refuses a title page', function () {
    $title = 'ILLUSTRATED WITH PORTRAITS Philadelphia J. B. LIPPINCOTT COMPANY London: GEORGE ALLEN AND '
        .'UNWIN RUSKIN HOUSE CHARING CROSS ROAD CHISWICK PRESS CHARLES WHITTINGHAM AND CO TOOKS COURT '
        .'CHANCERY LANE LONDON MDCCCXCIV';

    expect(OpeningLines::from($title))->toBeNull();
});

test('it refuses a copyright notice', function () {
    $notice = 'Copyright, 1897, in the United States of America. All rights reserved under the International '
        .'Copyright Convention. Published simultaneously in the Dominion of Canada by the publishers and '
        .'printed in the United States of America by the Riverside Press.';

    expect(OpeningLines::from($notice))->toBeNull();
});

test('it refuses a fragment too short to show', function () {
    expect(OpeningLines::from('Chapter One.'))->toBeNull();
});

test('prose is recognised and lists are not', function (string $text, bool $expected) {
    expect(OpeningLines::readsAsProse($text))->toBe($expected);
})->with([
    'novel' => [
        'In the late summer of that year we lived in a house in a village that looked across the river and '
        .'the plain to the mountains. In the bed of the river there were pebbles and boulders, dry and white '
        .'in the sun, and the water was clear and swiftly moving and blue in the channels.',
        true,
    ],
    'contents' => [
        'CHAPTER I. Down the Rabbit-Hole CHAPTER II. The Pool of Tears CHAPTER III. A Caucus-Race '
        .'CHAPTER IV. The Rabbit Sends in a Little Bill CHAPTER V. Advice from a Caterpillar '
        .'CHAPTER VI. Pig and Pepper CHAPTER VII. A Mad Tea-Party',
        false,
    ],
]);
