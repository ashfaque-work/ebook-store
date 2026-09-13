<?php

namespace App\Services\Search\Embeddings;

use RuntimeException;

/**
 * The provider could not be reached, or would not answer.
 *
 * Separate from a programming error on purpose: indexing should be able to
 * skip a batch, say so, and carry on rather than abandoning the run.
 */
class EmbeddingFailed extends RuntimeException {}
