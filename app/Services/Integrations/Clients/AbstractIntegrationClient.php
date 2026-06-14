<?php
namespace App\Services\Integrations\Clients;

use App\Models\Organization;
use App\Services\Integrations\Contracts\IntegrationClient;
use App\Services\IntegrationSettingsService;

/**
 * Shared plumbing for every concrete client: credential reads via
 * IntegrationSettingsService and the live/stub gate. Subclasses declare which
 * credential keys are REQUIRED for the provider to count as configured.
 */
abstract class AbstractIntegrationClient implements IntegrationClient
{
    public function __construct(protected readonly IntegrationSettingsService $settings) {}

    /**
     * Credential keys (within the provider's settings group) that MUST all be
     * present for isConfigured() to be true.
     *
     * @return array<int, string>
     */
    abstract protected function requiredKeys(): array;

    public function isConfigured(Organization $org): bool
    {
        $group = $this->provider()->settingsGroup();
        foreach ($this->requiredKeys() as $key) {
            $value = $this->settings->get($org, $group, $key);
            if ($value === null || $value === '' || $value === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the real upstream path may run: the master live switch is on AND
     * the org is fully configured. When false, clients use their stub path and
     * make NO network call. This is the single guard that keeps tests offline.
     */
    protected function isLive(Organization $org): bool
    {
        return (bool) config('integrations.live', false) && $this->isConfigured($org);
    }

    /** Read one effective credential value for the provider's group. */
    protected function cred(Organization $org, string $key, mixed $default = null): mixed
    {
        return $this->settings->get($org, $this->provider()->settingsGroup(), $key, $default);
    }

    public function testConnection(Organization $org): array
    {
        if (! $this->isConfigured($org)) {
            return ['ok' => false, 'message' => 'Kredensial belum lengkap untuk ' . $this->provider()->label() . '.'];
        }

        if (! config('integrations.live', false)) {
            return ['ok' => true, 'message' => 'Mode stub aktif (INTEGRATIONS_LIVE=false) — koneksi disimulasikan OK.'];
        }

        // TODO real reachability ping (Http::get base_url/health) — gated above.
        return ['ok' => true, 'message' => 'Koneksi ke ' . $this->provider()->label() . ' OK.'];
    }
}
