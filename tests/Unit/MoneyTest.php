<?php

use App\Support\Money;

test('rupee strings convert to paise without float drift', function () {
    // The reason this class exists: (int) (19.99 * 100) is 1998 in PHP, and a
    // one-paisa drift fails a payment gateway's signature check.
    expect(Money::fromRupees('19.99')->paise)->toBe(1999)
        ->and(Money::fromRupees('0.01')->paise)->toBe(1)
        ->and(Money::fromRupees('1299.50')->paise)->toBe(129950)
        ->and(Money::fromRupees('0')->paise)->toBe(0);
});

test('paise convert back to a rupee string', function () {
    expect(Money::fromPaise(129950)->rupees())->toBe('1299.50')
        ->and(Money::fromPaise(1)->rupees())->toBe('0.01');
});

test('amounts are formatted in Indian rupees', function () {
    expect(Money::fromPaise(29900)->inr())->toBe('₹299.00')
        ->and(Money::fromPaise(0)->inr())->toBe('₹0.00');
});

test('amounts add without losing paise', function () {
    $total = Money::fromRupees('19.99')
        ->plus(Money::fromRupees('0.01'))
        ->plus(Money::fromRupees('299.50'));

    expect($total->paise)->toBe(31950)
        ->and($total->rupees())->toBe('319.50');
});

test('a non-numeric amount is rejected rather than silently zeroed', function () {
    expect(fn () => Money::fromRupees('not money'))
        ->toThrow(InvalidArgumentException::class);
});
