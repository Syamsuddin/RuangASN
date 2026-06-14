<?php

namespace Tests\Feature\Integrations;

use App\Models\IntegrationRun;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\IntegrationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $adminA;

    private const VERIFY_TOKEN = 'wa-verify-secret-token-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        config()->set('integrations.live', false);

        $this->orgA = Organization::create([
            'id' => (string) Str::ulid(), 'type' => 'government',
            'name' => 'Pemda A', 'code' => 'PA', 'is_active' => true, 'depth' => 0,
        ]);
        $this->orgA->update(['pemda_id' => $this->orgA->id]);

        $this->adminA = User::create([
            'id' => (string) Str::ulid(), 'nip' => '199001012010011001',
            'name' => 'Admin A', 'email' => 'admin@a.id',
            'password' => Hash::make('password'), 'user_type' => 'pns',
            'status' => 'active', 'organization_id' => $this->orgA->id, 'pemda_id' => $this->orgA->id,
            'timezone' => 'Asia/Jakarta', 'locale' => 'id',
        ]);
        $this->adminA->assignRole('admin_pemda');

        // Configure the WhatsApp webhook verify token for org A.
        app(IntegrationSettingsService::class)->save($this->orgA, [
            'group'  => 'whatsapp',
            'fields' => [
                'enabled'              => true,
                'phone_number_id'      => '123',
                'access_token'         => 'wa-access-token',
                'webhook_verify_token' => self::VERIFY_TOKEN,
            ],
        ], $this->adminA);
    }

    // 5a. Invalid token → 401 + WebhookEvent(signature_valid=false), not processed.
    public function test_invalid_signature_returns_401_and_records_invalid_event(): void
    {
        $resp = $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, [
            'event_id' => 'evt-001',
            'entry'    => [['changes' => []]],
        ], ['X-Webhook-Token' => 'WRONG-TOKEN']);

        $resp->assertStatus(401);

        $event = WebhookEvent::where('provider', 'whatsapp')->first();
        $this->assertNotNull($event);
        $this->assertFalse($event->signature_valid);
        $this->assertFalse($event->processed);

        // No inbound run created for an invalid delivery.
        $this->assertDatabaseMissing('integration_runs', [
            'provider'  => 'whatsapp',
            'direction' => 'inbound',
        ]);
    }

    // 5b. Valid token → 200 + WebhookEvent(valid) + inbound IntegrationRun.
    public function test_valid_signature_returns_200_and_records_event_and_run(): void
    {
        $resp = $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, [
            'event_id' => 'evt-002',
            'entry'    => [['changes' => [['value' => ['messages' => []]]]]],
        ], ['X-Webhook-Token' => self::VERIFY_TOKEN]);

        $resp->assertStatus(200);

        $event = WebhookEvent::where('event_id', 'evt-002')->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->signature_valid);
        $this->assertTrue($event->processed);

        $this->assertDatabaseHas('integration_runs', [
            'organization_id' => $this->orgA->id,
            'provider'        => 'whatsapp',
            'direction'       => 'inbound',
            'operation'       => 'webhook_received',
            'status'          => 'success',
        ]);
    }

    // 5c. Replay with same event_id → 200 idempotent, not double-processed.
    public function test_replay_same_event_id_is_idempotent(): void
    {
        $payload = ['event_id' => 'evt-003', 'entry' => []];
        $headers = ['X-Webhook-Token' => self::VERIFY_TOKEN];

        $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, $payload, $headers)->assertStatus(200);
        $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, $payload, $headers)
            ->assertStatus(200)
            ->assertJson(['idempotent' => true]);

        // Only ONE processed webhook_event + ONE inbound run for this event_id.
        $this->assertSame(1, WebhookEvent::where('event_id', 'evt-003')->where('processed', true)->count());
        $this->assertSame(1, IntegrationRun::where('provider', 'whatsapp')->where('direction', 'inbound')->count());
    }

    // GET handshake: echoes hub.challenge when verify token matches.
    public function test_whatsapp_get_handshake_echoes_challenge(): void
    {
        $this->get('/api/webhooks/whatsapp?org=' . $this->orgA->id
                . '&hub_verify_token=' . self::VERIFY_TOKEN . '&hub_challenge=CHALLENGE-42')
            ->assertStatus(200)
            ->assertSee('CHALLENGE-42');
    }

    // GET handshake: wrong verify token → 403.
    public function test_whatsapp_get_handshake_rejects_wrong_token(): void
    {
        $this->get('/api/webhooks/whatsapp?org=' . $this->orgA->id
                . '&hub_verify_token=WRONG&hub_challenge=CHALLENGE-42')
            ->assertStatus(403);
    }

    // 7. Redaction: stored body_excerpt / headers never contain the verify token.
    public function test_secret_redacted_in_stored_webhook(): void
    {
        $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, [
            'event_id'             => 'evt-redact',
            'webhook_verify_token' => self::VERIFY_TOKEN, // attacker echoes it back in body
            'note'                 => 'contains ' . self::VERIFY_TOKEN . ' inline',
        ], ['X-Webhook-Token' => self::VERIFY_TOKEN]);

        $event = WebhookEvent::where('event_id', 'evt-redact')->firstOrFail();

        $this->assertStringNotContainsString(self::VERIFY_TOKEN, (string) $event->body_excerpt);
        $this->assertStringNotContainsString(self::VERIFY_TOKEN, json_encode($event->headers));

        $run = IntegrationRun::where('provider', 'whatsapp')->where('direction', 'inbound')->firstOrFail();
        $this->assertStringNotContainsString(self::VERIFY_TOKEN, (string) $run->payload_excerpt);
    }

    // Unknown provider → 404.
    public function test_unknown_provider_returns_404(): void
    {
        $this->postJson('/api/webhooks/bogus', ['event_id' => 'x'])->assertStatus(404);
    }

    // 6. event_id poisoning: same event_id but a DIFFERENT signed body is a NEW
    // event (dedupe is bound to body_hash), so it is processed, not dropped.
    public function test_same_event_id_with_different_body_is_processed_as_new(): void
    {
        $headers = ['X-Webhook-Token' => self::VERIFY_TOKEN];

        $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, [
            'event_id' => 'evt-poison', 'entry' => [['v' => 'first']],
        ], $headers)->assertStatus(200);

        $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, [
            'event_id' => 'evt-poison', 'entry' => [['v' => 'SECOND-different-body']],
        ], $headers)->assertStatus(200);

        // Two distinct processed events for the same event_id (different bodies),
        // and two inbound runs — the second was NOT silently dropped.
        $this->assertSame(2, WebhookEvent::where('event_id', 'evt-poison')->where('processed', true)->count());
        $this->assertSame(2, IntegrationRun::where('provider', 'whatsapp')->where('direction', 'inbound')->count());
    }

    // 7. Shared token in the URL query string is NOT accepted (header-only).
    public function test_query_string_token_is_rejected(): void
    {
        $resp = $this->postJson(
            '/api/webhooks/whatsapp?org=' . $this->orgA->id . '&token=' . self::VERIFY_TOKEN,
            ['event_id' => 'evt-qs', 'entry' => []],
            // No X-Webhook-Token header — only the (now rejected) query param.
        );

        $resp->assertStatus(401);

        $this->assertSame(0, IntegrationRun::where('provider', 'whatsapp')->where('direction', 'inbound')->count());
    }

    // 9. Redaction floor: a 16-digit NIK-like run in a free-text body excerpt is masked.
    public function test_nik_digit_run_is_masked_in_body_excerpt(): void
    {
        $nik = '3201234567890123'; // 16 digits

        $this->postJson('/api/webhooks/whatsapp?org=' . $this->orgA->id, [
            'event_id' => 'evt-nik',
            'note'     => "Warga dengan NIK {$nik} mengajukan permohonan.",
        ], ['X-Webhook-Token' => self::VERIFY_TOKEN])->assertStatus(200);

        $event = WebhookEvent::where('event_id', 'evt-nik')->firstOrFail();
        $this->assertStringNotContainsString($nik, (string) $event->body_excerpt);

        $run = IntegrationRun::where('provider', 'whatsapp')->where('direction', 'inbound')->firstOrFail();
        $this->assertStringNotContainsString($nik, (string) $run->payload_excerpt);
    }
}
