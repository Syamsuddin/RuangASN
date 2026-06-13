<?php
namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiResult;

interface AiProvider
{
    /** Provider key, e.g. 'gemini', 'fake'. */
    public function name(): string;

    /** True only if the provider can actually be used (e.g. api_key present). */
    public function isAvailable(): bool;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     *
     * @throws \App\Services\Ai\AiProviderException on transport/upstream failure
     */
    public function chat(array $messages, array $options = []): AiResult;
}
