<?php

namespace App\Services\Search\Embeddings;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Workers AI.
 *
 * Chosen because the store already keeps its books in R2, so the account
 * exists and one token covers it. bge-base-en-v1.5 returns 768 dimensions
 * natively, which is the width everything else here is built around.
 */
class CloudflareEmbedder implements Embedder
{
    public function __construct(
        private readonly string $accountId,
        private readonly string $token,
        private readonly string $model,
        private readonly int $dimensions,
    ) {}

    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$this->model}";

        try {
            $response = Http::withToken($this->token)
                ->timeout(60)
                // Retry what might pass a moment later, but not a 429: the
                // daily allowance being spent will not have changed in 1.5s.
                ->retry(2, 1500, fn ($e) => ! ($e instanceof RequestException && $e->response->status() === 429), throw: false)
                ->post($url, ['text' => array_values($texts)]);
        } catch (Throwable $e) {
            throw new EmbeddingFailed('Could not reach Workers AI: '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 429) {
            throw EmbeddingFailed::rateLimited('Workers AI rate limit reached: '.$response->body());
        }

        if (! $response->successful()) {
            throw new EmbeddingFailed('Workers AI returned '.$response->status().': '.$response->body());
        }

        $vectors = $response->json('result.data');

        if (! is_array($vectors) || count($vectors) !== count($texts)) {
            // A partial answer silently mis-assigns vectors to passages, which
            // is worse than no answer: the search would be confidently wrong.
            throw new EmbeddingFailed('Workers AI returned '.(is_array($vectors) ? count($vectors) : 0).' vectors for '.count($texts).' passages');
        }

        return array_map(fn ($vector) => array_map('floatval', $vector), $vectors);
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function name(): string
    {
        return 'cloudflare:'.$this->model;
    }
}
