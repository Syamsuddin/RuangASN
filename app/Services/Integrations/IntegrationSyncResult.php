<?php
namespace App\Services\Integrations;

use App\Enums\IntegrationRunStatus;

/**
 * Immutable outcome of a single client sync() / inbound handle. The manager
 * copies these fields onto the IntegrationRun row. `summary` and `errors` MUST
 * already be redacted by the client (no secrets / no full PII).
 */
final class IntegrationSyncResult
{
    /**
     * @param array<int, string> $errors Redacted, human-readable error lines.
     */
    public function __construct(
        public readonly IntegrationRunStatus $status,
        public readonly int $itemsProcessed = 0,
        public readonly int $itemsFailed = 0,
        public readonly string $summary = '',
        public readonly array $errors = [],
    ) {}

    /**
     * Derive a status from processed/failed counts: nothing failed → SUCCESS,
     * some processed + some failed → PARTIAL, everything failed → FAILED.
     */
    public static function fromCounts(int $processed, int $failed, string $summary, array $errors = []): self
    {
        $status = match (true) {
            $failed === 0                  => IntegrationRunStatus::SUCCESS,
            $processed > 0 && $failed > 0  => IntegrationRunStatus::PARTIAL,
            default                        => IntegrationRunStatus::FAILED,
        };

        return new self($status, $processed, $failed, $summary, $errors);
    }
}
