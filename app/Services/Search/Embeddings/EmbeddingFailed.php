<?php

namespace App\Services\Search\Embeddings;

use RuntimeException;
use Throwable;

/**
 * The provider could not be reached, or would not answer.
 *
 * Separate from a programming error on purpose: indexing should be able to
 * skip a batch, say so, and carry on rather than abandoning the run.
 *
 * Except when the provider says "not today". A rate limit is not a fault in
 * the passages that happened to be next in line, and treating it as one is how
 * a nightly run marked twelve hundred perfectly good passages as failed the
 * moment its free daily allowance ran out.
 */
class EmbeddingFailed extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly bool $rateLimited = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function rateLimited(string $message): self
    {
        return new self($message, rateLimited: true);
    }
}
