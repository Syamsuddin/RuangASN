<?php
namespace App\Services\Ai;

/**
 * Immutable result of a provider chat() call.
 */
class AiResult
{
    /**
     * @param array<int, array<string, mixed>> $proposedActions
     * @param array<int, array<string, mixed>> $citations
     */
    public function __construct(
        public readonly string $content,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?string $modelName = null,
        public readonly ?string $finishReason = null,
        public readonly array $proposedActions = [],
        public readonly array $citations = [],
    ) {}

    public function totalTokens(): int
    {
        return (int) $this->promptTokens + (int) $this->completionTokens;
    }
}
