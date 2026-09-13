<?php

use App\Services\Search\Embeddings\BagOfWordsEmbedder;
use App\Services\Search\Embeddings\CloudflareEmbedder;
use App\Services\Search\Embeddings\EmbedderFactory;
use App\Services\Search\Embeddings\EmbeddingFailed;
use App\Services\Search\Embeddings\OpenAiEmbedder;
use Illuminate\Support\Facades\Http;

test('no provider configured means no embedder, not an error', function () {
    config()->set('search.embeddings.driver', 'none');

    // The normal state of a store that has not signed up for one. The search
    // has to keep working without it.
    expect(EmbedderFactory::make())->toBeNull();
});

test('a provider named but not credentialled is still no embedder', function () {
    config()->set('search.embeddings.driver', 'cloudflare');
    config()->set('search.embeddings.cloudflare.account_id', null);
    config()->set('search.embeddings.cloudflare.token', null);

    // Half-configured is the state a deploy is in between adding the variable
    // and adding the secret, and it must not throw on every search.
    expect(EmbedderFactory::make())->toBeNull();
});

test('the fallback embedder produces vectors of the configured width', function () {
    $embedder = new BagOfWordsEmbedder(768);

    $vectors = $embedder->embed(['the quick brown fox', 'something else entirely']);

    expect($vectors)->toHaveCount(2)
        ->and($vectors[0])->toHaveCount(768)
        ->and($vectors[1])->toHaveCount(768);
});

test('the fallback embedder is deterministic', function () {
    $embedder = new BagOfWordsEmbedder(64);

    // A test that indexes and then searches must get the same answer twice.
    expect($embedder->embed(['a passage of text'])[0])
        ->toBe($embedder->embed(['a passage of text'])[0]);
});

test('vectors are normalised, so length does not beat relevance', function () {
    $embedder = new BagOfWordsEmbedder(64);
    $vector = $embedder->embed([str_repeat('whale ', 300)])[0];

    $length = sqrt(array_sum(array_map(fn ($v) => $v ** 2, $vector)));

    expect(round($length, 6))->toBe(1.0);
});

test('cloudflare returns one vector per passage', function () {
    Http::fake(['https://api.cloudflare.com/*' => Http::response([
        'result' => ['data' => [[0.1, 0.2], [0.3, 0.4]]],
    ])]);

    $vectors = (new CloudflareEmbedder('acct', 'token', '@cf/baai/bge-base-en-v1.5', 2))
        ->embed(['first', 'second']);

    expect($vectors)->toBe([[0.1, 0.2], [0.3, 0.4]]);
});

test('a short answer from cloudflare is refused rather than mis-assigned', function () {
    Http::fake(['https://api.cloudflare.com/*' => Http::response([
        'result' => ['data' => [[0.1, 0.2]]],
    ])]);

    // Two passages, one vector. Accepting it would file the second passage
    // under the first one's meaning — confidently wrong, and invisible.
    expect(fn () => (new CloudflareEmbedder('acct', 'token', 'model', 2))->embed(['first', 'second']))
        ->toThrow(EmbeddingFailed::class);
});

test('an error from the provider becomes a failure the indexer can skip', function () {
    Http::fake(['https://api.cloudflare.com/*' => Http::response('rate limited', 429)]);

    expect(fn () => (new CloudflareEmbedder('acct', 'token', 'model', 2))->embed(['first']))
        ->toThrow(EmbeddingFailed::class);
});

test('openai vectors are ordered by the index it returns, not by luck', function () {
    Http::fake(['https://api.openai.com/*' => Http::response([
        'data' => [
            ['index' => 1, 'embedding' => [0.3, 0.4]],
            ['index' => 0, 'embedding' => [0.1, 0.2]],
        ],
    ])]);

    $vectors = (new OpenAiEmbedder('sk-test', 'text-embedding-3-small', 2))->embed(['first', 'second']);

    expect($vectors)->toBe([[0.1, 0.2], [0.3, 0.4]]);
});

test('openai is asked for the width the database column expects', function () {
    Http::fake(['https://api.openai.com/*' => Http::response([
        'data' => [['index' => 0, 'embedding' => array_fill(0, 768, 0.1)]],
    ])]);

    (new OpenAiEmbedder('sk-test', 'text-embedding-3-small', 768))->embed(['x']);

    Http::assertSent(fn ($request) => $request['dimensions'] === 768);
});

test('embedding an empty list costs nothing and asks nobody', function () {
    Http::fake();

    expect((new CloudflareEmbedder('acct', 'token', 'model', 2))->embed([]))->toBe([]);

    Http::assertNothingSent();
});

test('the embed command refuses clearly when nothing is configured', function () {
    config()->set('search.embeddings.driver', 'none');

    $this->artisan('store:embed-text')->assertFailed();
});

test('the embed command refuses on a database that cannot hold vectors', function () {
    // SQLite, in the test suite. Better to say so than to write nothing and
    // report success.
    config()->set('search.embeddings.driver', 'bag-of-words');

    $this->artisan('store:embed-text')->assertFailed();
})->skip(fn () => DB::getDriverName() === 'pgsql', 'This database does hold vectors');
