<?php
namespace App\Services\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use App\Models\Organization;
use App\Models\User;
use App\Services\Integrations\Clients\GoogleCalendarClient;
use App\Services\Integrations\Clients\SiasnClient;
use App\Services\Integrations\Clients\SipdClient;
use App\Services\Integrations\Clients\SrikandiClient;
use App\Services\Integrations\Clients\WhatsAppClient;
use App\Services\Integrations\Contracts\IntegrationClient;
use App\Services\Integrations\Support\Redactor;
use App\Services\IntegrationSettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Resolves an IntegrationClient per provider and executes a sync, recording an
 * IntegrationRun for EVERY attempt (success, partial, or failure) so the monitor
 * always has full observability. The run is created RUNNING, then finalized in
 * place. Any thrown error is caught, redacted, and recorded as FAILED — the
 * manager never propagates an upstream exception to the caller.
 */
class IntegrationManager
{
    public function __construct(private readonly IntegrationSettingsService $settings) {}

    /** Resolve the concrete client for a provider. */
    public function client(IntegrationProvider $provider): IntegrationClient
    {
        return match ($provider) {
            IntegrationProvider::SIASN           => new SiasnClient($this->settings),
            IntegrationProvider::SRIKANDI        => new SrikandiClient($this->settings),
            IntegrationProvider::SIPD            => new SipdClient($this->settings),
            IntegrationProvider::GOOGLE_CALENDAR => new GoogleCalendarClient($this->settings),
            IntegrationProvider::WHATSAPP        => new WhatsAppClient($this->settings),
            IntegrationProvider::SSO             => new SiasnClient($this->settings), // SSO has no sync op; placeholder
        };
    }

    /**
     * Run a provider sync and record the IntegrationRun.
     *
     * @param array<string, mixed> $options
     */
    public function run(
        Organization $org,
        IntegrationProvider $provider,
        string $operation,
        ?User $actor = null,
        array $options = [],
    ): IntegrationRun {
        $run = IntegrationRun::create([
            'organization_id' => $org->id,
            'provider'        => $provider->value,
            'direction'       => 'outbound',
            'operation'       => $operation,
            'status'          => IntegrationRunStatus::RUNNING,
            'started_at'      => now(),
            'triggered_by'    => $actor?->id,
        ]);

        try {
            $client = $this->client($provider);
            $result = $client->sync($org, $options);

            $run->update([
                'status'          => $result->status,
                'items_processed' => $result->itemsProcessed,
                'items_failed'    => $result->itemsFailed,
                'summary'         => Redactor::text($result->summary, $this->knownSecrets($org, $provider)),
                'error_message'   => $result->errors !== []
                    ? Redactor::text(implode('; ', $result->errors), $this->knownSecrets($org, $provider))
                    : null,
                'payload_excerpt' => Redactor::payload([
                    'operation' => $operation,
                    'options'   => $options,
                    'processed' => $result->itemsProcessed,
                    'failed'    => $result->itemsFailed,
                ], $this->knownSecrets($org, $provider)),
                'finished_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // Scrub any secret/PII that may have reached the exception message
            // before it touches the run row or the log.
            $message = Redactor::text($e->getMessage(), $this->knownSecrets($org, $provider));

            $run->update([
                'status'        => IntegrationRunStatus::FAILED,
                'items_failed'  => 1,
                'error_message' => $message,
                'finished_at'   => now(),
            ]);

            Log::warning('Integration run failed.', [
                'provider'  => $provider->value,
                'operation' => $operation,
                'org'       => $org->id,
                'error'     => $message,
            ]);
        }

        return $run->refresh();
    }

    /**
     * The decrypted secret strings for a provider — passed to the Redactor so
     * any inline occurrence is masked before being persisted.
     *
     * @return array<int, string|null>
     */
    private function knownSecrets(Organization $org, IntegrationProvider $provider): array
    {
        $group = $provider->settingsGroup();

        return [
            $this->settings->get($org, $group, 'api_key'),
            $this->settings->get($org, $group, 'client_secret'),
            $this->settings->get($org, $group, 'access_token'),
            $this->settings->get($org, $group, 'webhook_verify_token'),
        ];
    }
}
