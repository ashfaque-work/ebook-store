<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A checkout that cannot proceed for a reason the customer should see —
 * an empty cart, a book that went off sale, a payment that was declined.
 *
 * Carries where to send them and what to say, so the controller stays a thin
 * translation from domain outcome to HTTP response.
 */
class CheckoutException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $redirectRoute = 'cart.index',
        public readonly string $toastType = 'error',
    ) {
        parent::__construct($message);
    }

    public static function emptyCart(): self
    {
        return new self('Your cart is empty.');
    }

    public static function nothingToBuy(): self
    {
        return new self(
            'You already own everything that was in your cart.',
            'library.index',
            'info',
        );
    }

    public static function paymentFailed(string $reason): self
    {
        return new self($reason);
    }
}
