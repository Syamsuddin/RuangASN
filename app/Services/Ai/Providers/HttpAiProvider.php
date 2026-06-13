<?php
namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\Contracts\AiProvider;

/**
 * Base for real HTTP-backed providers. Holds the per-provider config block
 * and gates availability on api_key presence. Subclasses implement chat().
 */
abstract class HttpAiProvider implements AiProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(protected array $config = [])
    {
    }

    public function isAvailable(): bool
    {
        return ! empty($this->config['api_key']);
    }

    protected function apiKey(): string
    {
        $key = $this->config['api_key'] ?? null;
        if (empty($key)) {
            throw new AiProviderException("{$this->name()}: API key tidak dikonfigurasi.");
        }

        return (string) $key;
    }

    protected function model(): string
    {
        return (string) ($this->config['model'] ?? 'default');
    }

    protected function baseUri(): string
    {
        return rtrim((string) ($this->config['base_uri'] ?? ''), '/');
    }
}
