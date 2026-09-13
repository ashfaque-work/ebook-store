<?php

namespace App\Services\Search\Embeddings;

/**
 * A vector made from the words themselves, with no model behind it.
 *
 * This is not semantic search and does not pretend to be: two passages about
 * the same thing in different words score no better than chance. It exists so
 * that everything around the model — chunking, storing, the vector index, the
 * ranking, the fusion with full-text — can be built and tested without an API
 * key, a bill, or a provider being available.
 *
 * Hashing words into a fixed number of buckets is the standard trick for this,
 * and it is deterministic, so a test that indexes and then searches gets the
 * same answer every run.
 */
class BagOfWordsEmbedder implements Embedder
{
    public function __construct(private readonly int $dimensions) {}

    public function embed(array $texts): array
    {
        return array_map(fn (string $text) => $this->vector($text), array_values($texts));
    }

    /**
     * @return array<int, float>
     */
    private function vector(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        foreach (preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $bucket = abs(crc32($word)) % $this->dimensions;
            $vector[$bucket] += 1.0;
        }

        // Normalised, so cosine distance behaves and a long passage does not
        // outrank a short one purely for being long.
        $length = sqrt(array_sum(array_map(fn ($value) => $value ** 2, $vector)));

        if ($length <= 0.0) {
            return $vector;
        }

        return array_map(fn ($value) => $value / $length, $vector);
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function name(): string
    {
        return 'bag-of-words (not semantic)';
    }
}
