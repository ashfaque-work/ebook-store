<?php

namespace App\Services\Search\Embeddings;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI embeddings.
 *
 * Asked for a fixed width rather than its native one, so vectors from either
 * provider fit the same column: text-embedding-3 models are trained so that a
 * truncated vector is still a usable one.
 */
class OpenAiEmbedder implements Embedder
{
    public function __construct(
        private readonly string $key,
        private readonly string $model,
        private readonly int $dimensions,
    ) {}

    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        try {
            $response = Http::withToken($this->key)
                ->timeout(60)
                // Retry what might pass a moment later, but not a 429: the
                // daily allowance being spent will not have changed in 1.5s.
                ->retry(2, 1500, fn ($e) => ! ($e instanceof RequestException && $e->response->status() === 429), throw: false)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => array_values($texts),
                    'dimensions' => $this->dimensions,
                ]);
        } catch (Throwable $e) {
            throw new EmbeddingFailed('Could not reach OpenAI: '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 429) {
            throw EmbeddingFailed::rateLimited('OpenAI rate limit reached: '.$response->body());
        }

        if (! $response->successful()) {
            throw new EmbeddingFailed('OpenAI returned '.$response->status().': '.$response->body());
        }

        $data = $response->json('data');

        if (! is_array($data) || count($data) !== count($texts)) {
            throw new EmbeddingFailed('OpenAI returned '.(is_array($data) ? count($data) : 0).' vectors for '.count($texts).' passages');
        }

        // The API documents that order is preserved, but it also returns an
        // index with each vector; sorting by it costs nothing and removes the
        // assumption.
        usort($data, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(fn ($row) => array_map('floatval', $row['embedding'] ?? []), $data);
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function name(): string
    {
        return 'openai:'.$this->model;
    }
}
