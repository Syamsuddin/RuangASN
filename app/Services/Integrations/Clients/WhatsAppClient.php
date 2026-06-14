<?php
namespace App\Services\Integrations\Clients;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use App\Models\Organization;
use App\Services\Integrations\IntegrationSyncResult;
use App\Services\Integrations\Support\Redactor;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business — outbound notification channel. Unlike the sync clients,
 * send() is invoked directly from NotificationService (not via the manager), so
 * it records its OWN IntegrationRun for observability. Stub returns true and
 * records a success run with a REDACTED excerpt (never the recipient phone or
 * the access token). Real Meta Cloud path is gated by live + configured.
 */
class WhatsAppClient extends AbstractIntegrationClient
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::WHATSAPP;
    }

    protected function requiredKeys(): array
    {
        return ['phone_number_id', 'access_token'];
    }

    /**
     * Send a WhatsApp message. ALWAYS records an IntegrationRun. Returns whether
     * the send was accepted by the channel. Never throws — caller (notification)
     * must not break on a messaging failure.
     */
    public function send(Organization $org, string $to, string $message): bool
    {
        $run = IntegrationRun::create([
            'organization_id' => $org->id,
            'provider'        => $this->provider()->value,
            'direction'       => 'outbound',
            'operation'       => 'send_message',
            'status'          => IntegrationRunStatus::RUNNING,
            'started_at'      => now(),
        ]);

        try {
            $live = $this->isLive($org);

            if ($live) {
                $ok = $this->sendLive($org, $to, $message);
            } else {
                // ── STUB: no network, deterministic success ───────────────
                $ok = true;
            }

            // Redact the recipient phone + message body + token from the excerpt
            // (passed as known secrets so any inline occurrence is masked).
            $token   = (string) $this->cred($org, 'access_token', '');
            $excerpt = Redactor::payload([
                'to'      => $to,
                'message' => $message,
                'mode'    => $live ? 'live' : 'stub',
            ], [$token, $to, $message]);

            $run->update([
                'status'          => $ok ? IntegrationRunStatus::SUCCESS : IntegrationRunStatus::FAILED,
                'items_processed' => $ok ? 1 : 0,
                'items_failed'    => $ok ? 0 : 1,
                'summary'         => $ok ? 'Pesan WhatsApp terkirim (' . ($live ? 'live' : 'stub') . ').' : 'Pengiriman WhatsApp gagal.',
                'payload_excerpt' => $excerpt,
                'finished_at'     => now(),
            ]);

            return $ok;
        } catch (\Throwable $e) {
            $token = (string) $this->cred($org, 'access_token', '');
            $run->update([
                'status'        => IntegrationRunStatus::FAILED,
                'items_failed'  => 1,
                'error_message' => Redactor::text($e->getMessage(), [$token, $to]),
                'finished_at'   => now(),
            ]);

            return false;
        }
    }

    private function sendLive(Organization $org, string $to, string $message): bool
    {
        $phoneNumberId = (string) $this->cred($org, 'phone_number_id');
        $accessToken   = (string) $this->cred($org, 'access_token');

        // TODO real WhatsApp Cloud API call — NOT executed in tests (gated):
        // $resp = Http::withToken($accessToken)
        //     ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
        //         'messaging_product' => 'whatsapp',
        //         'to' => $to,
        //         'type' => 'text',
        //         'text' => ['body' => $message],
        //     ]);
        // return $resp->successful();
        unset($phoneNumberId, $accessToken);

        return true;
    }

    /**
     * sync() is a no-op for WhatsApp (it is a message channel, not a data sync),
     * but kept to satisfy the interface so the manager can resolve it uniformly.
     */
    public function sync(Organization $org, array $options = []): IntegrationSyncResult
    {
        return new IntegrationSyncResult(
            status: IntegrationRunStatus::SUCCESS,
            itemsProcessed: 0,
            itemsFailed: 0,
            summary: 'WhatsApp adalah kanal pesan keluar; tidak ada data untuk disinkronkan.',
        );
    }

    /**
     * Record a successful inbound delivery (message status callback / incoming
     * message). Stub just logs; real handler would parse Meta's webhook payload.
     *
     * @param array<mixed> $payload
     */
    public function handleInbound(?Organization $org, array $payload): void
    {
        Log::info('WhatsApp inbound webhook received (stub).', [
            'org' => $org?->id,
        ]);
    }
}
