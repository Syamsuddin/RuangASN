<?php
namespace App\Providers;

use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Contracts\EmbeddingProvider;
use App\Services\Ai\Contracts\VectorStore;
use App\Services\Ai\Providers\ClaudeProvider;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Ai\Providers\FakeEmbeddingProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use App\Services\Ai\RetrievalService;
use App\Services\Ai\Stores\DatabaseVectorStore;
use App\Services\Ai\Stores\QdrantVectorStore;
use App\Services\IntegrationSettingsService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Embedding provider (deterministic fake by default).
        $this->app->singleton(EmbeddingProvider::class, function (Application $app) {
            $cfg        = $this->effectiveAiConfig($app);
            $dimensions = (int) ($cfg['embedding']['dimensions'] ?? 64);

            return match ($cfg['embedding']['provider'] ?? 'fake') {
                default => new FakeEmbeddingProvider($dimensions),
            };
        });

        // Provider manager: built per-resolution from the EFFECTIVE AI config
        // (DB overlay over config('ai')). With no auth + no DB rows this is
        // identical to config('ai'), keeping tests deterministic (fake only).
        // NOTE: not a singleton — the bound closure runs each resolution so the
        // org-scoped DB overlay is fresh per request.
        $this->app->bind(AiProviderManager::class, function (Application $app) {
            $cfg = $this->effectiveAiConfig($app);

            // Overlay the effective fallback order into the config repository so
            // AiProviderManager::chatWithFallback() (which reads config at
            // runtime) honours the DB-configured order too.
            config(['ai.fallback_order' => $cfg['fallback_order'] ?? config('ai.fallback_order')]);

            $providers = $this->buildProviders((array) ($cfg['providers'] ?? []));
            $providers[] = new FakeAiProvider();

            return new AiProviderManager($providers);
        });

        // Vector store binding (config-gated; DatabaseVectorStore is the path).
        $this->app->singleton(VectorStore::class, function (Application $app) {
            $retrieval = $app->make(RetrievalService::class);
            $cfg       = $this->effectiveAiConfig($app);

            return match ($cfg['vector_store'] ?? 'database') {
                'qdrant' => new QdrantVectorStore($retrieval),
                default  => new DatabaseVectorStore($retrieval),
            };
        });
    }

    public function boot(): void
    {
        //
    }

    /**
     * Resolve the effective AI config for the current request. In CLI/tests with
     * no authenticated user, no DB overlay is applied → pure config('ai').
     *
     * @return array<string, mixed>
     */
    private function effectiveAiConfig(Application $app): array
    {
        $org = auth()->check() ? auth()->user()?->organization : null;

        if ($org === null) {
            return (array) config('ai');
        }

        return $app->make(IntegrationSettingsService::class)->aiConfig($org);
    }

    /**
     * Construct a provider instance per known provider with a non-empty api_key.
     * gemini/claude/openai have dedicated HTTP providers; the others are wired
     * once dedicated classes exist (they are skipped silently when keyless).
     *
     * @param array<string, array<string, mixed>> $providers
     * @return array<int, AiProvider>
     */
    private function buildProviders(array $providers): array
    {
        $out = [];

        if (! empty($providers['gemini']['api_key'])) {
            $out[] = new GeminiProvider($providers['gemini']);
        }
        if (! empty($providers['claude']['api_key'])) {
            $out[] = new ClaudeProvider($providers['claude']);
        }
        if (! empty($providers['openai']['api_key'])) {
            $out[] = new OpenAiProvider($providers['openai']);
        }

        return $out;
    }
}
