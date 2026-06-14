<?php
namespace App\Services\Integrations\Clients;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationRunStatus;
use App\Models\Organization;
use App\Services\Integrations\IntegrationSyncResult;
use App\Services\Integrations\Support\Redactor;

/**
 * SRIKANDI — sinkronisasi surat masuk/keluar (persuratan & kearsipan dinamis).
 * Stub returns a deterministic summary with no network call.
 */
class SrikandiClient extends AbstractIntegrationClient
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::SRIKANDI;
    }

    protected function requiredKeys(): array
    {
        return ['base_url', 'api_key'];
    }

    public function sync(Organization $org, array $options = []): IntegrationSyncResult
    {
        if ($this->isLive($org)) {
            $apiKey  = (string) $this->cred($org, 'api_key');
            $baseUrl = (string) $this->cred($org, 'base_url');
            // TODO real SRIKANDI API call (surat masuk/keluar) — gated, no-op in tests.
            $summary = Redactor::text("Sinkronisasi surat SRIKANDI ({$baseUrl}).", [$apiKey]);

            return IntegrationSyncResult::fromCounts(0, 0, $summary);
        }

        // ── STUB ──────────────────────────────────────────────────────────
        // Deterministic surat masuk/keluar figures.
        $masuk  = 2;
        $keluar = 1;

        return new IntegrationSyncResult(
            status: IntegrationRunStatus::SUCCESS,
            itemsProcessed: $masuk + $keluar,
            itemsFailed: 0,
            summary: "Sinkronisasi {$masuk} surat masuk & {$keluar} surat keluar (stub).",
        );
    }
}
