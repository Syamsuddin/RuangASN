<?php
namespace App\Services\Integrations\Clients;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationRunStatus;
use App\Models\Organization;
use App\Services\Integrations\IntegrationSyncResult;
use App\Services\Integrations\Support\Redactor;

/**
 * SIPD — sinkronisasi program & kegiatan (Sistem Informasi Pemerintahan Daerah).
 * SIPD reuses the generic siasn-style credential fields (base_url/api_key) under
 * its own 'sipd' settings group. Stub returns a deterministic summary.
 */
class SipdClient extends AbstractIntegrationClient
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::SIPD;
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
            // TODO real SIPD API call (program kegiatan) — gated, no-op in tests.
            $summary = Redactor::text("Sinkronisasi program SIPD ({$baseUrl}).", [$apiKey]);

            return IntegrationSyncResult::fromCounts(0, 0, $summary);
        }

        // ── STUB ──────────────────────────────────────────────────────────
        $programs = 4;

        return new IntegrationSyncResult(
            status: IntegrationRunStatus::SUCCESS,
            itemsProcessed: $programs,
            itemsFailed: 0,
            summary: "Sinkronisasi {$programs} program/kegiatan SIPD (stub).",
        );
    }
}
