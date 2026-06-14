<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationRunStatus;
use App\Enums\NotificationType;
use App\Models\IntegrationRun;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\User;
use App\Services\Integrations\IntegrationManager;
use App\Services\IntegrationSettingsService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $adminA;
    private User $asnUser;
    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        // Belt-and-braces: integrations must stay in stub mode for all tests.
        config()->set('integrations.live', false);

        $this->orgA = $this->makeOrg('Pemda A', 'PA');
        $this->orgB = $this->makeOrg('Pemda B', 'PB');

        $this->adminA  = $this->makeUser('admin@a.id', '199001012010011001', $this->orgA, 'admin_pemda');
        $this->asnUser = $this->makeUser('asn@a.id', '199501012023011001', $this->orgA, 'asn');
        $this->adminB  = $this->makeUser('admin@b.id', '199001012010011002', $this->orgB, 'admin_pemda');
    }

    private function makeOrg(string $name, string $code): Organization
    {
        $org = Organization::create([
            'id' => (string) Str::ulid(), 'type' => 'government',
            'name' => $name, 'code' => $code, 'is_active' => true, 'depth' => 0,
        ]);
        $org->update(['pemda_id' => $org->id]);

        return $org;
    }

    private function makeUser(string $email, string $nip, Organization $org, string $role, ?string $phone = null): User
    {
        $user = User::create([
            'id' => (string) Str::ulid(), 'nip' => $nip,
            'name' => ucfirst(explode('@', $email)[0]), 'email' => $email, 'phone' => $phone,
            'password' => Hash::make('password'), 'user_type' => 'pns',
            'status' => 'active', 'organization_id' => $org->id, 'pemda_id' => $org->id,
            'timezone' => 'Asia/Jakarta', 'locale' => 'id',
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Configure the WhatsApp integration (stub) for an org. */
    private function configureWhatsApp(Organization $org): void
    {
        app(IntegrationSettingsService::class)->save($org, [
            'group'  => 'whatsapp',
            'fields' => [
                'enabled'              => true,
                'phone_number_id'      => '123456789',
                'access_token'         => 'whatsapp-secret-token-XYZ',
                'webhook_verify_token' => 'verify-secret-abc',
            ],
        ], $this->adminA);
    }

    // 1. Manager::run(siasn) stub records a SUCCESS run, tenant-scoped, with finished_at.
    public function test_manager_run_siasn_records_success_run(): void
    {
        $manager = app(IntegrationManager::class);

        $run = $manager->run($this->orgA, IntegrationProvider::SIASN, 'sync_asn', $this->adminA);

        $this->assertSame(IntegrationRunStatus::SUCCESS, $run->status);
        $this->assertSame($this->orgA->id, $run->organization_id);
        $this->assertSame('outbound', $run->direction);
        $this->assertSame('sync_asn', $run->operation);
        $this->assertGreaterThan(0, $run->items_processed); // counts org A's users
        $this->assertNotNull($run->finished_at);
        $this->assertSame($this->adminA->id, $run->triggered_by);

        $this->assertDatabaseHas('integration_runs', [
            'id'              => $run->id,
            'organization_id' => $this->orgA->id,
            'provider'        => 'siasn',
            'status'          => 'success',
        ]);
    }

    // 2. RBAC: asn cannot trigger a run (403); admin_pemda can (302 back).
    public function test_rbac_asn_cannot_run_admin_can(): void
    {
        $this->actingAs($this->asnUser)
            ->post('/admin/integrations/run', ['provider' => 'siasn'])
            ->assertStatus(403);

        $this->actingAs($this->adminA)
            ->post('/admin/integrations/run', ['provider' => 'siasn'])
            ->assertRedirect();

        $this->assertDatabaseHas('integration_runs', [
            'organization_id' => $this->orgA->id,
            'provider'        => 'siasn',
        ]);
    }

    // 3. Monitor page lists only the org's runs (tenant isolation).
    public function test_monitor_lists_only_own_org_runs(): void
    {
        $manager = app(IntegrationManager::class);
        $manager->run($this->orgA, IntegrationProvider::SIASN, 'sync_asn', $this->adminA);
        $manager->run($this->orgB, IntegrationProvider::SRIKANDI, 'sync_surat', $this->adminB);

        $resp = $this->withoutVite()->actingAs($this->adminA)->get('/admin/integrations/monitor');
        $resp->assertStatus(200);

        $runs = collect($resp->original->getData()['page']['props']['runs']);
        $this->assertTrue($runs->isNotEmpty());
        $this->assertTrue($runs->every(fn ($r) => $r['provider'] === 'siasn'));
        $this->assertFalse($runs->contains(fn ($r) => $r['provider'] === 'srikandi'));
    }

    // 4a. WhatsApp channel: pref ON + configured → records a stub IntegrationRun.
    public function test_whatsapp_pref_on_records_integration_run(): void
    {
        $this->configureWhatsApp($this->orgA);

        $recipient = $this->makeUser('wa-on@a.id', '199601012023011010', $this->orgA, 'asn', '628111111111');
        NotificationPreference::create([
            'user_id' => $recipient->id, 'in_app' => true, 'email' => false,
            'push' => false, 'whatsapp' => true,
        ]);

        app(NotificationService::class)->send(
            $recipient, NotificationType::SYSTEM, 'Judul', 'Isi pesan',
        );

        // In-app notification always created.
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $recipient->id]);

        // WhatsApp stub recorded a SUCCESS run.
        $run = IntegrationRun::where('organization_id', $this->orgA->id)
            ->where('provider', 'whatsapp')
            ->where('operation', 'send_message')
            ->first();
        $this->assertNotNull($run);
        $this->assertSame(IntegrationRunStatus::SUCCESS, $run->status);
        $this->assertSame(1, $run->items_processed);
    }

    // 4b. WhatsApp channel: pref OFF → no run; in-app still created.
    public function test_whatsapp_pref_off_records_no_run(): void
    {
        $this->configureWhatsApp($this->orgA);

        $recipient = $this->makeUser('wa-off@a.id', '199601012023011011', $this->orgA, 'asn', '628222222222');
        NotificationPreference::create([
            'user_id' => $recipient->id, 'in_app' => true, 'email' => false,
            'push' => false, 'whatsapp' => false,
        ]);

        app(NotificationService::class)->send(
            $recipient, NotificationType::SYSTEM, 'Judul', 'Isi pesan',
        );

        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $recipient->id]);
        $this->assertDatabaseMissing('integration_runs', [
            'organization_id' => $this->orgA->id,
            'provider'        => 'whatsapp',
        ]);
    }

    // 6. testConnection(siasn) stub returns ok=true once configured.
    public function test_test_connection_siasn_stub_ok(): void
    {
        app(IntegrationSettingsService::class)->save($this->orgA, [
            'group'  => 'siasn',
            'fields' => ['base_url' => 'https://apimws.bkn.go.id', 'api_key' => 'siasn-secret-key'],
        ], $this->adminA);

        $result = app(IntegrationManager::class)
            ->client(IntegrationProvider::SIASN)
            ->testConnection($this->orgA->fresh());

        $this->assertTrue($result['ok']);
    }

    // 6b. testConnection over HTTP via the controller (RBAC admin.integrations.manage).
    public function test_test_connection_endpoint_flashes_result(): void
    {
        app(IntegrationSettingsService::class)->save($this->orgA, [
            'group'  => 'siasn',
            'fields' => ['base_url' => 'https://apimws.bkn.go.id', 'api_key' => 'siasn-secret-key'],
        ], $this->adminA);

        $this->actingAs($this->adminA)
            ->post('/admin/integrations/test', ['provider' => 'siasn'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    // 7. Redaction: the configured access_token never appears in payload_excerpt.
    public function test_secret_redacted_in_payload_excerpt(): void
    {
        $this->configureWhatsApp($this->orgA);

        $recipient = $this->makeUser('wa-redact@a.id', '199601012023011012', $this->orgA, 'asn', '628333333333');
        NotificationPreference::create([
            'user_id' => $recipient->id, 'in_app' => true, 'whatsapp' => true,
        ]);

        app(NotificationService::class)->send(
            $recipient, NotificationType::SYSTEM, 'Judul', 'Isi pesan rahasia',
        );

        $run = IntegrationRun::where('provider', 'whatsapp')->firstOrFail();
        $this->assertStringNotContainsString('whatsapp-secret-token-XYZ', (string) $run->payload_excerpt);
        // The recipient phone (PII) must also not leak into the excerpt.
        $this->assertStringNotContainsString('628333333333', (string) $run->payload_excerpt);
        $this->assertStringNotContainsString('Isi pesan rahasia', (string) $run->payload_excerpt);
    }

    // 8. Every run is recorded — even a failure path records a row.
    public function test_run_records_row_even_when_unconfigured(): void
    {
        // SIASN not configured → stub still succeeds and records a run (observability).
        $run = app(IntegrationManager::class)
            ->run($this->orgA, IntegrationProvider::SIASN, 'sync_asn', $this->adminA);

        $this->assertDatabaseHas('integration_runs', ['id' => $run->id]);
        $this->assertNotNull($run->finished_at);
    }

    // 9. Console command runs in stub mode without network and records runs.
    public function test_console_sync_command_records_runs(): void
    {
        app(IntegrationSettingsService::class)->save($this->orgA, [
            'group'  => 'siasn',
            'fields' => ['enabled' => true, 'base_url' => 'https://apimws.bkn.go.id', 'api_key' => 'k'],
        ], $this->adminA);

        $this->artisan('integrations:sync', ['provider' => 'siasn', '--org' => $this->orgA->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('integration_runs', [
            'organization_id' => $this->orgA->id,
            'provider'        => 'siasn',
        ]);
    }
}
