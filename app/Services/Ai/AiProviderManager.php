<?php
namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a usable AI provider by walking config('ai.fallback_order'),
 * skipping unavailable ones, and trying each in turn. On a provider
 * exception it catches and tries the next. FakeAiProvider is guaranteed as
 * the terminal fallback so chatWithFallback never hard-fails (P-05).
 */
class AiProviderManager
{
    /** @var array<int, AiProvider> */
    private array $providers;

    /** @param array<int, AiProvider> $providers */
    public function __construct(array $providers)
    {
        // Ensure a FakeAiProvider terminal fallback always exists.
        $hasFake = false;
        foreach ($providers as $p) {
            if ($p instanceof FakeAiProvider) {
                $hasFake = true;
                break;
            }
        }
        if (! $hasFake) {
            $providers[] = new FakeAiProvider();
        }

        $this->providers = $providers;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return array{result: AiResult, provider: string, fellBack: bool}
     */
    public function chatWithFallback(array $messages, array $options = []): array
    {
        $order   = config('ai.fallback_order', ['fake']);
        $primary = $order[0] ?? null;

        // Reorder available providers to match the configured fallback_order,
        // always keeping the FakeAiProvider as a terminal entry.
        $ordered = $this->orderedProviders($order);

        $lastError = null;
        foreach ($ordered as $provider) {
            if (! $provider->isAvailable()) {
                continue;
            }

            try {
                $result   = $provider->chat($messages, $options);
                $fellBack = $provider->name() !== $primary;

                return [
                    'result'   => $result,
                    'provider' => $provider->name(),
                    'fellBack' => $fellBack,
                ];
            } catch (AiProviderException $e) {
                $lastError = $e;
                Log::warning("AI provider [{$provider->name()}] failed, falling back.", [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // Unreachable in practice (Fake never throws / is always available),
        // but guard anyway.
        $fake   = new FakeAiProvider();
        $result = $fake->chat($messages, $options);

        return [
            'result'   => $result,
            'provider' => $fake->name(),
            'fellBack' => true,
        ];
    }

    /**
     * @param array<int, string> $order
     * @return array<int, AiProvider>
     */
    private function orderedProviders(array $order): array
    {
        $byName = [];
        foreach ($this->providers as $p) {
            $byName[$p->name()] = $p;
        }

        $ordered = [];
        foreach ($order as $name) {
            if (isset($byName[$name])) {
                $ordered[] = $byName[$name];
                unset($byName[$name]);
            }
        }
        // Append any providers not named in the order (keeps Fake terminal).
        foreach ($byName as $p) {
            $ordered[] = $p;
        }

        return $ordered;
    }
}
