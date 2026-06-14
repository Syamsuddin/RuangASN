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

    /**
     * Truncate an upstream error body to a short prefix and scrub any secrets
     * before it can be embedded into an exception message / log (H2).
     */
    protected function safeBody(string $body): string
    {
        return self::redact(mb_substr($body, 0, 200));
    }

    /**
     * Strip credential-bearing patterns (api keys, bearer tokens, auth headers)
     * from arbitrary text so they never reach logs (H1/H2). Shared by every
     * HTTP provider + AiProviderManager.
     */
    public static function redact(string $text): string
    {
        $patterns = [
            // Authorization: Bearer <value> (consume the optional Bearer prefix
            // AND its value so no orphan token survives). Run before bare Bearer.
            '/Authorization\s*[:=]\s*(?:Bearer\s+)?\S+/i',
            // bare Bearer <value>
            '/Bearer\s+\S+/i',
            // x-goog-api-key: <value> / x-api-key: <value> headers
            '/x-(?:goog-)?api-key\s*[:=]\s*[^\s&"\']+/i',
            // key=... / api_key=... / access_token=... query/body params
            '/\b(?:api[_-]?key|key|access[_-]?token|token)\b\s*[=:]\s*[^\s&"\']+/i',
        ];

        return (string) preg_replace($patterns, '[REDACTED]', $text);
    }
}
