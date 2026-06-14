<?php
namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Ai\Providers\HttpAiProvider;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a usable AI provider by walking the resolved fallback order,
 * skipping unavailable ones, and trying each in turn. On a provider
 * exception it catches and tries the next. FakeAiProvider is guaranteed as
 * the terminal fallback so chatWithFallback never hard-fails (P-05).
 *
 * The fallback order is passed in at construction (per-resolution, org-scoped)
 * rather than read from global config() at runtime — under persistent workers
 * (Octane/queue) a global mutation would bleed one org's order into the next
 * job (L1).
 */
class AiProviderManager
{
    /** @var array<int, AiProvider> */
    private array $providers;

    /** @var array<int, string> */
    private array $fallbackOrder;

    /**
     * @param array<int, AiProvider> $providers
     * @param array<int, string> $fallbackOrder Resolved (org-scoped) order; defaults to ['fake'].
     */
    public function __construct(array $providers, array $fallbackOrder = ['fake'])
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

        $this->providers     = $providers;
        $this->fallbackOrder = $fallbackOrder !== [] ? array_values($fallbackOrder) : ['fake'];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return array{result: AiResult, provider: string, fellBack: bool}
     */
    public function chatWithFallback(array $messages, array $options = []): array
    {
        $order   = $this->fallbackOrder;
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
                // Scrub any secret that may have reached the exception message
                // (defence in depth — providers already redact, but a custom
                // provider may not) before it touches the log (H2).
                Log::warning("AI provider [{$provider->name()}] failed, falling back.", [
                    'provider' => $provider->name(),
                    'error'    => HttpAiProvider::redact($e->getMessage()),
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
