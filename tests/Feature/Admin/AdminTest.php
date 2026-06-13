<?php

namespace Tests\Feature\Admin;

use App\Enums\DelegationType;
use App\Enums\UserStatus;
use App\Models\Delegation;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private Organization $pemda;
    private User $adminPemda;
    private User $asnUser;
    private Organization $secondOrg;
    private User $secondOrgAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        // Create primary pemda (root org)
        $this->pemda = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Pemda HSS',
            'code'      => 'HSS',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->pemda->update(['pemda_id' => $this->pemda->id]);

        // Admin pemda user
        $this->adminPemda = $this->makeUser('admin@hss.id', '199001012010011001', $this->pemda, $this->pemda, 'admin_pemda');

        // ASN user (no admin perms)
        $this->asnUser = $this->makeUser('asn@hss.id', '199501012023011001', $this->pemda, $this->pemda, 'asn');

        // Second pemda for tenant isolation
        $this->secondOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Pemda Lain',
            'code'      => 'OTHER',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->secondOrg->update(['pemda_id' => $this->secondOrg->id]);
        $this->secondOrgAdmin = $this->makeUser('admin@other.id', '198001012010011099', $this->secondOrg, $this->secondOrg, 'admin_pemda');
    }

    private function makeUser(string $email, string $nip, Organization $org, Organization $pemda, string $role): User
    {
        $user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => $nip,
            'name'            => ucfirst(explode('@', $email)[0]),
            'email'           => $email,
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => UserStatus::ACTIVE->value,
            'organization_id' => $org->id,
            'pemda_id'        => $pemda->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $user->assignRole($role);
        return $user;
    }

    // 1. admin_pemda can list users
    public function test_admin_pemda_can_list_users(): void
    {
        $response = $this->withoutVite()->actingAs($this->adminPemda)->get('/admin/users');
        $response->assertStatus(200);
    }

    // 1b. asn without admin.users.view gets 403
    public function test_asn_gets_403_on_users_index(): void
    {
        $response = $this->actingAs($this->asnUser)->get('/admin/users');
        $response->assertStatus(403);
    }

    // 2. admin creates a user with a role
    public function test_admin_can_create_user_with_role(): void
    {
        $response = $this->actingAs($this->adminPemda)->post('/admin/users', [
            'nip'             => '200001012025011001',
            'name'            => 'Pengguna Baru',
            'email'           => 'baru@hss.id',
            'user_type'       => 'pns',
            'organization_id' => $this->pemda->id,
            'role'            => 'asn',
            'password'        => 'password123',
        ]);

        $response->assertRedirect();

        $user = User::where('email', 'baru@hss.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Pengguna Baru', $user->name);
        $this->assertTrue($user->hasRole('asn'));
    }

    // 3. deactivate sets status inactive
    public function test_admin_can_deactivate_user(): void
    {
        $target = $this->makeUser('target@hss.id', '200001012025011002', $this->pemda, $this->pemda, 'asn');

        $response = $this->actingAs($this->adminPemda)->patch("/admin/users/{$target->id}/deactivate");
        $response->assertRedirect();

        $target->refresh();
        $this->assertEquals(UserStatus::INACTIVE->value, $target->status->value);
    }

    // 4. tenant isolation: admin sees only own-pemda users
    public function test_tenant_isolation_user_list(): void
    {
        $response = $this->withoutVite()->actingAs($this->adminPemda)->get('/admin/users');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'] ?? null;
        if ($props) {
            $userIds = collect($props['users']['data'] ?? [])->pluck('id')->all();
            $this->assertNotContains($this->secondOrgAdmin->id, $userIds);
        }
        // At minimum confirm response OK and not containing second org user
        $response->assertDontSee($this->secondOrgAdmin->email);
    }

    // 5a. organization tree index accessible to admin
    public function test_admin_can_view_organization_tree(): void
    {
        $response = $this->withoutVite()->actingAs($this->adminPemda)->get('/admin/organizations');
        $response->assertStatus(200);
    }

    // 5b. create child org sets depth correctly
    public function test_create_child_org_sets_depth(): void
    {
        $response = $this->actingAs($this->adminPemda)->post('/admin/organizations', [
            'name'      => 'BKPSDM',
            'type'      => 'department',
            'parent_id' => $this->pemda->id,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $child = Organization::where('name', 'BKPSDM')->first();
        $this->assertNotNull($child);
        $this->assertEquals(1, $child->depth);
        $this->assertEquals($this->pemda->id, $child->pemda_id);
    }

    // 5c. asn gets 403 on organization
    public function test_asn_gets_403_on_organizations(): void
    {
        // ASN has organization.view.tree so they can view, but not create
        $response = $this->actingAs($this->asnUser)->post('/admin/organizations', [
            'name' => 'Unauthorized Org', 'type' => 'department',
        ]);
        $response->assertStatus(403);
    }

    // 6. team create + add member + remove member
    public function test_team_create_and_member_management(): void
    {
        // Create team
        $response = $this->actingAs($this->adminPemda)->post('/admin/teams', [
            'name'            => 'Tim Digitalisasi',
            'type'            => 'task_force',
            'organization_id' => $this->pemda->id,
        ]);
        $response->assertRedirect();

        $team = Team::where('name', 'Tim Digitalisasi')->first();
        $this->assertNotNull($team);
        $this->assertEquals($this->pemda->id, $team->pemda_id);

        // Add member
        $response = $this->actingAs($this->adminPemda)->post("/admin/teams/{$team->id}/members", [
            'user_id' => $this->asnUser->id,
            'role'    => 'member',
        ]);
        $response->assertRedirect();

        $member = TeamMember::where('team_id', $team->id)->where('user_id', $this->asnUser->id)->first();
        $this->assertNotNull($member);
        $this->assertTrue($member->is_active);

        // Remove member
        $response = $this->actingAs($this->adminPemda)->delete("/admin/teams/{$team->id}/members/{$member->id}");
        $response->assertRedirect();

        $member->refresh();
        $this->assertFalse($member->is_active);
    }

    // 6b. RBAC deny for asn on team create
    public function test_asn_cannot_manage_teams(): void
    {
        $response = $this->actingAs($this->asnUser)->post('/admin/teams', [
            'name' => 'Unauthorized Tim', 'type' => 'task_force',
        ]);
        $response->assertStatus(403);
    }

    // 7. delegation create + revoke
    public function test_delegation_create_and_revoke(): void
    {
        // Create delegation
        $response = $this->actingAs($this->adminPemda)->post('/admin/delegations', [
            'delegator_id' => $this->adminPemda->id,
            'delegate_id'  => $this->asnUser->id,
            'type'         => DelegationType::PLT->value,
            'reason'       => 'Kepala OPD menghadiri Rakornas',
            'start_date'   => now()->toDateString(),
            'end_date'     => now()->addDays(7)->toDateString(),
            'sk_number'    => '001/PLT/2026',
        ]);
        $response->assertRedirect();

        $delegation = Delegation::where('delegate_id', $this->asnUser->id)->first();
        $this->assertNotNull($delegation);
        $this->assertTrue($delegation->is_active);

        // Revoke
        $response = $this->actingAs($this->adminPemda)->patch("/admin/delegations/{$delegation->id}/revoke");
        $response->assertRedirect();

        $delegation->refresh();
        $this->assertFalse($delegation->is_active);
    }

    // 7b. asn without delegation.manage gets 403
    public function test_asn_cannot_manage_delegations(): void
    {
        $response = $this->actingAs($this->asnUser)->post('/admin/delegations', [
            'delegator_id' => $this->adminPemda->id,
            'delegate_id'  => $this->asnUser->id,
            'type'         => 'plt',
            'reason'       => 'test',
            'start_date'   => now()->toDateString(),
            'end_date'     => now()->addDays(3)->toDateString(),
        ]);
        $response->assertStatus(403);
    }

    // 8. audit index requires permission; asn without audit.view gets 403
    public function test_audit_index_rbac(): void
    {
        // ASN has no audit.view.own or audit.view.all
        $asnNoAudit = $this->makeUser('noaudit@hss.id', '199901012022011001', $this->pemda, $this->pemda, 'asn');
        $response = $this->actingAs($asnNoAudit)->get('/admin/audit');
        $response->assertStatus(403);
    }

    public function test_admin_pemda_can_view_audit_log(): void
    {
        $response = $this->withoutVite()->actingAs($this->adminPemda)->get('/admin/audit');
        $response->assertStatus(200);
    }
}
