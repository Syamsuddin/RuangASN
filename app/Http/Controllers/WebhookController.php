<?php
namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use App\Models\Organization;
use App\Models\WebhookEvent;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\Support\Redactor;
use App\Services\IntegrationSettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Unauthenticated, machine-to-machine webhook receiver. Security comes from a
 * provider signature/secret verified with hash_equals (constant time), NOT from
 * a session. Every delivery is recorded in webhook_events (dedupe + audit); a
 * valid one also creates an inbound IntegrationRun. Stored headers/body are
 * REDACTED — no secrets or full PII ever land in the audit rows.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly IntegrationSettingsService $settings,
        private readonly IntegrationManager $manager,
    ) {}

    /**
     * Meta WhatsApp verification handshake (GET): echo hub.challenge when the
     * presented hub.verify_token matches the configured verify token.
     */
    public function whatsappVerify(Request $request): Response
    {
        $provider = IntegrationProvider::WHATSAPP;
        $org      = $this->resolveOrg($request, $provider);

        $expected  = (string) $this->settings->get($org, 'whatsapp', 'webhook_verify_token', '');
        $presented = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($expected !== '' && hash_equals($expected, $presented)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle an inbound webhook POST for any provider.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerEnum = IntegrationProvider::tryFrom($provider);
        if ($providerEnum === null) {
            return response()->json(['message' => 'Unknown provider'], 404);
        }

        $org      = $this->resolveOrg($request, $providerEnum);
        $eventId  = $this->extractEventId($request, $providerEnum);
        $bodyHash = hash('sha256', $request->getContent());
        $payload  = (array) $request->json()->all();

        // ── Signature verification (constant-time) ─────────────────────────
        $valid = $this->verifySignature($request, $providerEnum, $org);

        // A valid signature can only be produced from a configured secret, which
        // can only be read for a resolvable org → $org is non-null past here.
        if (! $valid || $org === null) {
            // Record the rejected delivery for audit only. body_hash is left NULL
            // so an invalid (unsigned) delivery can never occupy the dedupe slot
            // and starve a later legitimate, correctly-signed delivery of the same
            // body. NULLs are distinct, so invalid rows also never collide here.
            $this->recordEvent($org, $providerEnum, $eventId, null, false, false, $request);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // ── Atomic idempotency: INSERT first, let the DB unique guard on
        // (provider, event_id, body_hash) reject replays. Binding to body_hash
        // (sha256 of the SIGNED body) — not just the attacker-supplied event_id —
        // means a reused event_id with a DIFFERENT body is treated as a new
        // event, while a verbatim replay collides and is acknowledged idempotently
        // WITHOUT re-running the side effect. This closes the read-then-act race.
        try {
            $event = $this->recordEvent($org, $providerEnum, $eventId, $bodyHash, true, true, $request);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return response()->json(['message' => 'Already processed', 'idempotent' => true], 200);
            }
            throw $e;
        }

        $run = IntegrationRun::create([
            'organization_id' => $org->id,
            'provider'        => $providerEnum->value,
            'direction'       => 'inbound',
            'operation'       => 'webhook_received',
            'status'          => IntegrationRunStatus::RUNNING,
            'started_at'      => now(),
        ]);

        try {
            $this->dispatchInbound($providerEnum, $org, $payload);

            $run->update([
                'status'          => IntegrationRunStatus::SUCCESS,
                'items_processed' => 1,
                'summary'         => "Webhook {$providerEnum->value} diproses.",
                'payload_excerpt' => Redactor::payload($payload, $this->knownSecrets($org, $providerEnum)),
                'finished_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status'        => IntegrationRunStatus::FAILED,
                'items_failed'  => 1,
                'error_message' => Redactor::text($e->getMessage(), $this->knownSecrets($org, $providerEnum)),
                'finished_at'   => now(),
            ]);
        }

        return response()->json(['message' => 'OK', 'event' => $event->id], 200);
    }

    /**
     * Verify the provider signature/secret in constant time. WhatsApp uses the
     * configured webhook_verify_token (header or X-Hub-Signature HMAC); other
     * providers use a per-group `webhook_secret`. Absent configured secret →
     * invalid (fail closed).
     */
    private function verifySignature(Request $request, IntegrationProvider $provider, ?Organization $org): bool
    {
        $group = $provider->settingsGroup();

        if ($provider === IntegrationProvider::WHATSAPP) {
            $expected = (string) $this->settings->get($org, 'whatsapp', 'webhook_verify_token', '');
            if ($expected === '') {
                return false;
            }

            // Accept either an X-Hub-Signature-256 HMAC of the raw body, or a
            // simple shared-token header. The shared token is header-only — a
            // token in the URL query string lands in access logs / Referer and is
            // NOT accepted here. Both compared constant-time.
            $hubSig = (string) $request->header('X-Hub-Signature-256', '');
            if ($hubSig !== '') {
                $computed = 'sha256=' . hash_hmac('sha256', $request->getContent(), $expected);
                return hash_equals($computed, $hubSig);
            }

            $token = (string) $request->header('X-Webhook-Token', '');
            return hash_equals($expected, $token);
        }

        $secret = (string) $this->settings->get($org, $group, 'webhook_secret', '');
        if ($secret === '') {
            // Fall back to client_secret if no dedicated webhook_secret field.
            $secret = (string) $this->settings->get($org, $group, 'client_secret', '');
        }
        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('X-Signature', '');
        if ($signature !== '') {
            $computed = hash_hmac('sha256', $request->getContent(), $secret);
            return hash_equals($computed, $signature);
        }

        // Shared token is header-only (never the URL query string — it would leak
        // into access logs / Referer). Prefer the HMAC path above.
        $token = (string) $request->header('X-Webhook-Token', '');
        return hash_equals($secret, $token);
    }

    private function dispatchInbound(IntegrationProvider $provider, ?Organization $org, array $payload): void
    {
        if ($provider === IntegrationProvider::WHATSAPP) {
            /** @var \App\Services\Integrations\Clients\WhatsAppClient $client */
            $client = $this->manager->client($provider);
            $client->handleInbound($org, $payload);
            return;
        }

        // Other providers: stub — recording the run is the inbound effect.
    }

    private function recordEvent(
        ?Organization $org,
        IntegrationProvider $provider,
        ?string $eventId,
        ?string $bodyHash,
        bool $signatureValid,
        bool $processed,
        Request $request,
    ): WebhookEvent {
        $secrets = $this->knownSecrets($org, $provider);

        return WebhookEvent::create([
            'organization_id' => $org?->id,
            'provider'        => $provider->value,
            'event_id'        => $eventId,
            'body_hash'       => $bodyHash,
            'signature_valid' => $signatureValid,
            'processed'       => $signatureValid && $processed,
            'headers'         => Redactor::maskArray($this->safeHeaders($request), $secrets),
            'body_excerpt'    => Redactor::text($request->getContent(), $secrets),
        ]);
    }

    /**
     * Detect a UNIQUE constraint violation across both Postgres (SQLSTATE 23505)
     * and SQLite ("UNIQUE constraint failed") so the idempotency guard behaves
     * identically in tests (SQLite) and production (Postgres).
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        if (($e->getCode() === '23505')) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique constraint')
            || str_contains($message, 'unique violation')
            || str_contains($message, 'duplicate');
    }

    /**
     * @return array<string, mixed>
     */
    private function safeHeaders(Request $request): array
    {
        $out = [];
        foreach ($request->headers->all() as $name => $values) {
            // Symfony returns each header as a list of string|null; flatten it.
            $out[$name] = implode(', ', array_map(static fn ($v) => (string) $v, $values));
        }

        return $out;
    }

    private function extractEventId(Request $request, IntegrationProvider $provider): ?string
    {
        // Common idempotency-key headers first, then provider-specific body keys.
        $header = $request->header('X-Idempotency-Key')
            ?? $request->header('X-Event-Id')
            ?? $request->header('X-Request-Id');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        $id = $request->input('event_id') ?? $request->input('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function resolveOrg(Request $request, IntegrationProvider $provider): ?Organization
    {
        $orgId = $request->query('org') ?? $request->header('X-Organization-Id') ?? $request->input('organization_id');

        if (is_string($orgId) && $orgId !== '') {
            return Organization::withoutGlobalScopes()->whereKey($orgId)->first();
        }

        return null;
    }

    /**
     * @return array<int, string|null>
     */
    private function knownSecrets(?Organization $org, IntegrationProvider $provider): array
    {
        $group = $provider->settingsGroup();

        return [
            $this->settings->get($org, $group, 'webhook_verify_token'),
            $this->settings->get($org, $group, 'webhook_secret'),
            $this->settings->get($org, $group, 'client_secret'),
            $this->settings->get($org, $group, 'access_token'),
            $this->settings->get($org, $group, 'api_key'),
        ];
    }

}
