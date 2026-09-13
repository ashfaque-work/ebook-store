<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Semantic search
    |--------------------------------------------------------------------------
    |
    | Full-text search matches words. Someone looking for "the bit where Darcy
    | proposes" uses none of the words on that page, and gets nothing. Comparing
    | embeddings finds it, because the passage means what the question means.
    |
    | This is off until a provider is configured, and the store keeps working
    | without it: full-text alone is a good search, just a literal one.
    |
    */

    'embeddings' => [

        'driver' => env('EMBEDDINGS_DRIVER', 'none'),

        /*
         | Every provider here is asked for the same width, so the stored
         | vectors stay comparable and the database column does not have to
         | change when the provider does. 768 is what bge-base returns
         | natively, and what OpenAI's models can be asked to return.
         */
        'dimensions' => 768,

        /*
         | Passages per request. Large enough that indexing a whole catalogue
         | is not thousands of round trips, small enough to stay under request
         | size limits and to lose little when one batch fails.
         */
        'batch' => (int) env('EMBEDDINGS_BATCH', 32),

        'cloudflare' => [
            'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
            'token' => env('CLOUDFLARE_AI_TOKEN'),
            'model' => env('CLOUDFLARE_EMBEDDING_MODEL', '@cf/baai/bge-base-en-v1.5'),
        ],

        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
    ],

];
