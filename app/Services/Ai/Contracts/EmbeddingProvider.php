<?php
namespace App\Services\Ai\Contracts;

interface EmbeddingProvider
{
    /** @return array<int, float> A dense float vector. */
    public function embed(string $text): array;

    public function isAvailable(): bool;
}
