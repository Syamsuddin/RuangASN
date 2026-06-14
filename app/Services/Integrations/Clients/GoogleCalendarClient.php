<?php
namespace App\Services\Integrations\Clients;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationRunStatus;
use App\Models\Organization;
use App\Services\Integrations\IntegrationSyncResult;
use App\Services\Integrations\Support\Redactor;

/**
 * Google Calendar — per-user, opt-in two-way sync of calendar events. Credentials
 * live under the 'video' settings group (shared with the meet/zoom config). Stub
 * returns a deterministic summary and never touches the network.
 */
class GoogleCalendarClient extends AbstractIntegrationClient
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::GOOGLE_CALENDAR;
    }

    protected function requiredKeys(): array
    {
        return ['api_key'];
    }

    public function sync(Organization $org, array $options = []): IntegrationSyncResult
    {
        if ($this->isLive($org)) {
            $apiKey = (string) $this->cred($org, 'api_key');
            // TODO real Google Calendar API call (per-user OAuth) — gated, no-op in tests.
            $summary = Redactor::text('Sinkronisasi Google Calendar selesai.', [$apiKey]);

            return IntegrationSyncResult::fromCounts(0, 0, $summary);
        }

        // ── STUB ──────────────────────────────────────────────────────────
        // Per-user opt-in: options['user_id'] identifies the opted-in user.
        $events = 3;

        return new IntegrationSyncResult(
            status: IntegrationRunStatus::SUCCESS,
            itemsProcessed: $events,
            itemsFailed: 0,
            summary: "Sinkronisasi {$events} acara kalender (stub).",
        );
    }
}
