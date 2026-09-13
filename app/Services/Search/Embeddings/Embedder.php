<?php

namespace App\Services\Search\Embeddings;

/**
 * Turn text into a vector that can be compared with other vectors.
 *
 * Two implementations reach a network and one does not, which is the point of
 * the interface: the indexing, storing and ranking around it can be exercised
 * without an API key, a bill, or a provider being up.
 */
interface Embedder
{
    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>> one vector per text, in order
     *
     * @throws EmbeddingFailed when the provider cannot be reached or refuses
     */
    public function embed(array $texts): array;

    /** How wide the vectors are. Must match the database column. */
    public function dimensions(): int;

    /** Named in logs and on the admin dashboard, so "which model made these" is answerable. */
    public function name(): string;
}
