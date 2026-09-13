<?php

namespace App\Services\Search\Embeddings;

/**
 * Build whichever embedder is configured, or none at all.
 *
 * Returning null rather than throwing is deliberate: no provider configured is
 * the normal state of a store that has not signed up for one, and the search
 * has to keep working without it.
 */
class EmbedderFactory
{
    public static function make(): ?Embedder
    {
        $config = config('search.embeddings');
        $dimensions = (int) ($config['dimensions'] ?? 768);

        return match ($config['driver'] ?? 'none') {
            'cloudflare' => self::cloudflare($config, $dimensions),
            'openai' => self::openai($config, $dimensions),
            // Named for what it is. Useful in tests and for a local run-through
            // of the pipeline; it would be a poor search in production.
            'bag-of-words' => new BagOfWordsEmbedder($dimensions),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function cloudflare(array $config, int $dimensions): ?Embedder
    {
        $account = $config['cloudflare']['account_id'] ?? null;
        $token = $config['cloudflare']['token'] ?? null;

        if (! $account || ! $token) {
            return null;
        }

        return new CloudflareEmbedder($account, $token, $config['cloudflare']['model'], $dimensions);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function openai(array $config, int $dimensions): ?Embedder
    {
        $key = $config['openai']['key'] ?? null;

        return $key ? new OpenAiEmbedder($key, $config['openai']['model'], $dimensions) : null;
    }
}
