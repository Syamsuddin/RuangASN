<?php
namespace App\Services\Integrations\Clients;

use App\Enums\IntegrationProvider;
use App\Models\Organization;
use App\Models\User;
use App\Services\Integrations\IntegrationSyncResult;
use App\Services\Integrations\Support\Redactor;

/**
 * SIASN BKN — sinkronisasi data kepegawaian ASN. The stub deterministically
 * reports how many of the org's ASN users would be synced (no network). The
 * real path (live + configured) is left clearly marked and is never reached in
 * tests because config('integrations.live') defaults to false.
 */
class SiasnClient extends AbstractIntegrationClient
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::SIASN;
    }

    protected function requiredKeys(): array
    {
        return ['base_url', 'api_key'];
    }

    public function sync(Organization $org, array $options = []): IntegrationSyncResult
    {
        if ($this->isLive($org)) {
            return $this->syncLive($org, $options);
        }

        // ── STUB (deterministic, no network) ──────────────────────────────
        // Count the org's users without auth-scoped global scope so the figure
        // is stable regardless of who triggered the run.
        $count = User::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->count();

        return new IntegrationSyncResult(
            status: \App\Enums\IntegrationRunStatus::SUCCESS,
            itemsProcessed: $count,
            itemsFailed: 0,
            summary: "Sinkronisasi {$count} data ASN (stub).",
            errors: [],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function syncLive(Organization $org, array $options): IntegrationSyncResult
    {
        $baseUrl = (string) $this->cred($org, 'base_url');
        $apiKey  = (string) $this->cred($org, 'api_key');

        // TODO real SIASN API call — NOT executed in tests (gated by isLive()):
        // $resp = Http::timeout(config('integrations.timeout'))
        //     ->withToken($apiKey)
        //     ->baseUrl($baseUrl)
        //     ->get('/apisiasn/1.0/pns/data', ['orgId' => $org->id]);
        // …map $resp->json() to processed/failed counts…
        // Any error text MUST be redacted before it lands in the result:
        $summary = Redactor::text("Sinkronisasi ASN SIASN selesai untuk {$baseUrl}.", [$apiKey]);

        return IntegrationSyncResult::fromCounts(0, 0, $summary);
    }
}
