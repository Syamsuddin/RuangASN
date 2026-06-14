<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\IntegrationSetting;
use App\Models\Organization;
use App\Models\User;
use App\Services\IntegrationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $adminA;
    private User $adminB;
    private User $asnUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->orgA = $this->makeOrg('Pemda A', 'PA');
        $this->orgB = $this->makeOrg('Pemda B', 'PB');

        $this->adminA  = $this->makeUser('admin@a.id', '199001012010011001', $this->orgA, 'admin_pemda');
        $this->adminB  = $this->makeUser('admin@b.id', '199001012010011002', $this->orgB, 'admin_pemda');
        $this->asnUser = $this->makeUser('asn@a.id', '199501012023011001', $this->orgA, 'asn');
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

    private function makeUser(string $email, string $nip, Organization $org, string $role): User
    {
        $user = User::create([
            'id' => (string) Str::ulid(), 'nip' => $nip,
            'name' => ucfirst(explode('@', $email)[0]), 'email' => $email,
            'password' => Hash::make('password'), 'user_type' => 'pns',
            'status' => 'active', 'organization_id' => $org->id, 'pemda_id' => $org->id,
            'timezone' => 'Asia/Jakarta', 'locale' => 'id',
        ]);
        $user->assignRole($role);

        return $user;
    }

    // 1a. admin_pemda can view
    public function test_admin_pemda_can_view_integrations(): void
    {
        $this->withoutVite()->actingAs($this->adminA)->get('/admin/integrations')->assertStatus(200);
    }

    // 1b. asn (no admin.integrations.view) => 403
    public function test_asn_gets_403_on_integrations_index(): void
    {
        $this->actingAs($this->asnUser)->get('/admin/integrations')->assertStatus(403);
    }

    // 2. non-secret value persists plain and reads back
    public function test_non_secret_value_persists_plain_and_reads_back(): void
    {
        $this->actingAs($this->adminA)->patch('/admin/integrations', [
            'group'  => 'ai',
            'fields' => ['default_provider' => 'gemini', 'fallback_order' => 'gemini,fake'],
        ])->assertRedirect();

        $row = IntegrationSetting::withoutGlobalScope('organization')
            ->where('organization_id', $this->orgA->id)
            ->where('group', 'ai')->where('key', 'default_provider')->first();

        $this->assertNotNull($row);
        $this->assertSame('gemini', $row->value);
        $this->assertFalse($row->is_secret);

        $resp = $this->withoutVite()->actingAs($this->adminA)->get('/admin/integrations');
        $resp->assertStatus(200);
        $values = $resp->original->getData()['page']['props']['values'];
        $this->assertSame('gemini', $values['ai']['default_provider']);
    }

    // 3. secret stored ENCRYPTED + index returns configured:true without plaintext
    public function test_secret_stored_encrypted_and_never_exposed(): void
    {
        $secret = 'sk-super-secret-key-123';

        $this->actingAs($this->adminA)->patch('/admin/integrations', [
            'group'  => 'ai',
            'fields' => ['providers.gemini.api_key' => $secret],
        ])->assertRedirect();

        $row = IntegrationSetting::withoutGlobalScope('organization')
            ->where('organization_id', $this->orgA->id)
            ->where('group', 'ai')->where('key', 'providers.gemini.api_key')->first();

        $this->assertNotNull($row);
        $this->assertTrue($row->is_secret);
        // Ciphertext at rest — not equal to plaintext, but decryptable.
        $this->assertNotSame($secret, $row->value);
        $this->assertSame($secret, Crypt::decryptString($row->value));

        // Index never leaks the plaintext; reports configured:true.
        $resp = $this->withoutVite()->actingAs($this->adminA)->get('/admin/integrations');
        $resp->assertStatus(200);
        $resp->assertDontSee($secret);
        $values = $resp->original->getData()['page']['props']['values'];
        $this->assertSame(['configured' => true], $values['ai']['providers.gemini.api_key']);
    }

    // 4. empty secret submit keeps existing
    public function test_empty_secret_submit_keeps_existing(): void
    {
        $secret = 'sk-keep-me-please';
        $this->actingAs($this->adminA)->patch('/admin/integrations', [
            'group' => 'ai', 'fields' => ['providers.gemini.api_key' => $secret],
        ])->assertRedirect();

        // Submit again with empty secret + another field changed.
        $this->actingAs($this->adminA)->patch('/admin/integrations', [
            'group' => 'ai', 'fields' => ['providers.gemini.api_key' => '', 'providers.gemini.model' => 'gemini-1.5-pro'],
        ])->assertRedirect();

        $row = IntegrationSetting::withoutGlobalScope('organization')
            ->where('organization_id', $this->orgA->id)
            ->where('group', 'ai')->where('key', 'providers.gemini.api_key')->first();

        $this->assertSame($secret, Crypt::decryptString($row->value));
    }

    // 5. asn cannot PATCH
    public function test_asn_cannot_update_integrations(): void
    {
        $this->actingAs($this->asnUser)->patch('/admin/integrations', [
            'group' => 'ai', 'fields' => ['default_provider' => 'gemini'],
        ])->assertStatus(403);
    }

    // 6. tenant isolation: org A value invisible to org B admin
    public function test_tenant_isolation_settings_per_organization(): void
    {
        $this->actingAs($this->adminA)->patch('/admin/integrations', [
            'group' => 'mail', 'fields' => ['from_name' => 'Pemda A Mailer'],
        ])->assertRedirect();

        $resp = $this->withoutVite()->actingAs($this->adminB)->get('/admin/integrations');
        $resp->assertStatus(200);
        $values = $resp->original->getData()['page']['props']['values'];

        // Org B sees the config fallback (env 'RuangASN' default), not org A's value.
        $this->assertNotSame('Pemda A Mailer', $values['mail']['from_name']);
    }

    // 7. aiConfig overlays a DB gemini api_key over config
    public function test_ai_config_overlays_db_gemini_api_key(): void
    {
        $service = app(IntegrationSettingsService::class);

        $this->actingAs($this->adminA)->patch('/admin/integrations', [
            'group' => 'ai', 'fields' => ['providers.gemini.api_key' => 'sk-gemini-db', 'providers.gemini.model' => 'gemini-2.0-flash'],
        ])->assertRedirect();

        $cfg = $service->aiConfig($this->orgA->fresh());

        $this->assertSame('sk-gemini-db', $cfg['providers']['gemini']['api_key']);
        $this->assertSame('gemini-2.0-flash', $cfg['providers']['gemini']['model']);

        // And a provider built from that key would be "available".
        $provider = new \App\Services\Ai\Providers\GeminiProvider($cfg['providers']['gemini']);
        $this->assertTrue($provider->isAvailable());
    }

    // 8. saving writes an audit_logs row
    public function test_saving_writes_audit_log(): void
    {
        $before = AuditLog::count();

        $this->actingAs($this->adminA)->patch('/admin/integrations', [
            'group' => 'ai', 'fields' => ['default_provider' => 'fake'],
        ])->assertRedirect();

        $this->assertGreaterThan($before, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'action'         => AuditAction::UPDATED->value,
            'auditable_type' => 'IntegrationSetting',
        ]);
    }
}
