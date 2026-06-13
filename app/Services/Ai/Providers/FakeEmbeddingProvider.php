<?php
namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\EmbeddingProvider;

/**
 * Deterministic embedding provider: hashes tokens into a fixed-dimension
 * float vector, then L2-normalizes. No randomness, no time-dependence —
 * identical text always yields the identical vector.
 */
class FakeEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(private int $dimensions = 64)
    {
        $this->dimensions = max(1, $dimensions);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /** @return array<int, float> */
    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            // Two independent hashes → bucket index + signed magnitude.
            $h1  = crc32($token);
            $h2  = crc32('salt:' . $token);
            $idx = $h1 % $this->dimensions;
            $sign = ($h2 % 2 === 0) ? 1.0 : -1.0;
            $vector[$idx] += $sign;
        }

        return $this->normalize($vector);
    }

    /**
     * @param array<int, float> $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $sumSq = 0.0;
        foreach ($vector as $v) {
            $sumSq += $v * $v;
        }
        $norm = sqrt($sumSq);
        if ($norm <= 0.0) {
            return $vector; // all-zero vector (e.g. empty text)
        }

        return array_map(static fn (float $v): float => $v / $norm, $vector);
    }
}
