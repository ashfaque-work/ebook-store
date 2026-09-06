<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money as an integer number of paise.
 *
 * Nothing in this application should format a price by hand, and nothing
 * should multiply a float by 100. `(int) (19.99 * 100)` is 1998 in PHP, and a
 * one-paisa drift is enough to fail a payment gateway's signature check and
 * leave a customer charged for an order the app believes is unpaid.
 *
 * The database still stores rupees as decimal(8,2); Phase B migrates the
 * columns to paise. This class is the seam that makes that migration a change
 * of storage rather than a change of arithmetic.
 */
final class Money
{
    private function __construct(public readonly int $paise) {}

    public static function fromPaise(int $paise): self
    {
        return new self($paise);
    }

    /**
     * Build from a rupee amount. Accepts the decimal strings Eloquent returns
     * for a `decimal:2` cast — pass the value through as-is, never cast it to
     * float first.
     */
    public static function fromRupees(string|int|float $rupees): self
    {
        if (! is_numeric($rupees)) {
            throw new InvalidArgumentException('Not a numeric rupee amount: '.var_export($rupees, true));
        }

        // bcmul on the string form keeps the two decimal places exact.
        return new self((int) bcmul((string) $rupees, '100', 0));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->paise + $other->paise);
    }

    public function times(int $factor): self
    {
        return new self($this->paise * $factor);
    }

    /**
     * The rupee amount as a decimal string, for storage in a decimal column.
     */
    public function rupees(): string
    {
        return bcdiv((string) $this->paise, '100', 2);
    }

    /**
     * Display form, e.g. "₹1,299.00".
     */
    public function inr(): string
    {
        return '₹'.number_format($this->paise / 100, 2);
    }

    public function __toString(): string
    {
        return $this->inr();
    }
}
