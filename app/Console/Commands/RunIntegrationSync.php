<?php

namespace App\Console\Commands;

use App\Enums\IntegrationProvider;
use App\Models\Organization;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Console\Command;

/**
 * Triggers an integration sync for one provider across every (or one) org that
 * has it configured + enabled. Harmless when INTEGRATIONS_LIVE is false (the
 * stub path runs and records observability rows without touching the network).
 */
class RunIntegrationSync extends Command
{
    protected $signature = 'integrations:sync {provider? : siasn|srikandi|sipd|google_calendar} {--org= : limit to one organization id}';
    protected $description = 'Run external integration syncs (stub unless INTEGRATIONS_LIVE) and record IntegrationRun rows.';

    public function handle(IntegrationManager $manager): int
    {
        $providerArg = $this->argument('provider');

        $providers = $providerArg !== null
            ? array_filter([IntegrationProvider::tryFrom((string) $providerArg)])
            : [
                IntegrationProvider::SIASN,
                IntegrationProvider::SRIKANDI,
                IntegrationProvider::SIPD,
            ];

        if ($providers === []) {
            $this->error("Unknown provider [{$providerArg}].");
            return self::FAILURE;
        }

        $orgs = $this->resolveOrgs();
        if ($orgs->isEmpty()) {
            $this->warn('No organizations to sync.');
            return self::SUCCESS;
        }

        $total = 0;
        foreach ($orgs as $org) {
            foreach ($providers as $provider) {
                $client = $manager->client($provider);

                // Only meaningful when configured + the provider is enabled in
                // settings. Stub still runs, but we skip wholly-unconfigured orgs
                // to avoid noise.
                $enabled = (string) app(\App\Services\IntegrationSettingsService::class)
                    ->get($org, $provider->settingsGroup(), 'enabled', '0');

                if (! $client->isConfigured($org) && $enabled !== '1') {
                    continue;
                }

                $operation = (string) config(
                    "integrations.providers.{$provider->value}.sync_operation",
                    'sync',
                );

                $run = $manager->run($org, $provider, $operation);
                $total++;

                $this->line(sprintf(
                    '[%s] %s/%s → %s (%d ok, %d gagal)',
                    $org->code ?? $org->id,
                    $provider->value,
                    $operation,
                    $run->status->value,
                    $run->items_processed,
                    $run->items_failed,
                ));
            }
        }

        $this->info("Selesai. {$total} run integrasi tercatat.");
        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    private function resolveOrgs(): \Illuminate\Support\Collection
    {
        $orgId = $this->option('org');

        $query = Organization::withoutGlobalScopes();
        if ($orgId !== null) {
            $query->whereKey($orgId);
        }

        return $query->get();
    }
}
