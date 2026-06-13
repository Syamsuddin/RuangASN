<?php
namespace App\Providers;

use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Contracts\EmbeddingProvider;
use App\Services\Ai\Contracts\VectorStore;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Ai\Providers\FakeEmbeddingProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\RetrievalService;
use App\Services\Ai\Stores\DatabaseVectorStore;
use App\Services\Ai\Stores\QdrantVectorStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Embedding provider (deterministic fake by default).
        $this->app->singleton(EmbeddingProvider::class, function (Application $app) {
            $dimensions = (int) config('ai.embedding.dimensions', 64);

            return match (config('ai.embedding.provider', 'fake')) {
                default => new FakeEmbeddingProvider($dimensions),
            };
        });

        // Provider manager: build the configured chat providers + Fake terminal.
        $this->app->singleton(AiProviderManager::class, function (Application $app) {
            $providers = [
                new GeminiProvider((array) config('ai.providers.gemini', [])),
                // Claude/OpenAI share the GeminiProvider request shape closely
                // enough for the lightly-implemented stage; they are gated on key
                // presence and unreachable in tests. Wire dedicated providers when
                // those keys are configured.
                new FakeAiProvider(),
            ];

            return new AiProviderManager($providers);
        });

        // Vector store binding (config-gated; DatabaseVectorStore is the path).
        $this->app->singleton(VectorStore::class, function (Application $app) {
            $retrieval = $app->make(RetrievalService::class);

            return match (config('ai.vector_store', 'database')) {
                'qdrant' => new QdrantVectorStore($retrieval),
                default  => new DatabaseVectorStore($retrieval),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
