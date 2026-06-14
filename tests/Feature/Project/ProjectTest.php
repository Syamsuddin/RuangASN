<?php

namespace Tests\Feature\Project;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $owner;       // kepala_opd — owns projects, can close
    private User $member;      // asn — added as project member
    private User $nonMember;   // asn — same org, not a member, no view.all
    private Organization $otherOrg;
    private User $otherUser;   // cross-org

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = $this->makeOrg('Pemda Proyek', 'TPRO');

        $this->owner     = $this->makeUser($this->org, 'owner@proj.id', 'kepala_opd');
        $this->member    = $this->makeUser($this->org, 'member@proj.id', 'asn');
        $this->nonMember = $this->makeUser($this->org, 'nonmember@proj.id', 'asn');

        $this->otherOrg  = $this->makeOrg('Pemda Lain', 'OPRO');
        $this->otherUser = $this->makeUser($this->otherOrg, 'other@proj.id', 'kepala_opd');
    }

    private function makeOrg(string $name, string $code): Organization
    {
        $org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => $name,
            'code'      => $code,
            'is_active' => true,
            'depth'     => 0,
        ]);
        $org->update(['pemda_id' => $org->id]);

        return $org;
    }

    private function makeUser(Organization $org, string $email, string $role): User
    {
        $user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => (string) rand(100000000000000000, 999999999999999999),
            'name'            => ucfirst(explode('@', $email)[0]),
            'email'           => $email,
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $org->id,
            'pemda_id'        => $org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name'               => 'Digitalisasi Layanan Perizinan',
            'description'         => 'Proyek transformasi digital perizinan.',
            'objectives'          => 'Mempercepat layanan perizinan.',
            'planned_start_date'  => '2026-06-01',
            'planned_end_date'    => '2026-12-31',
            'budget'              => 500000000,
            'data_classification' => 2,
        ], $override);
    }

    private function createProjectAs(User $user): Project
    {
        $this->actingAs($user)->post('/projects', $this->payload());
        $project = Project::where('owner_id', $user->id)->latest('created_at')->first();
        $this->assertNotNull($project);

        return $project;
    }

    // 1a. Owner creates project → status draft, owner auto-added as member.
    public function test_owner_can_create_project_and_is_auto_member(): void
    {
        $response = $this->actingAs($this->owner)->post('/projects', $this->payload());
        $response->assertRedirect();

        $project = Project::where('owner_id', $this->owner->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals(ProjectStatus::DRAFT->value, $project->status->value);
        $this->assertEquals($this->org->id, $project->organization_id);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id'    => $this->owner->id,
            'role'       => 'owner',
        ]);
    }

    // 1b. RBAC deny: a role lacking project.create cannot create.
    public function test_role_without_create_permission_is_denied(): void
    {
        // A user with no roles/permissions has no project.create → create denied.
        $stripped = $this->makeUser($this->org, 'noperm@proj.id', 'asn');
        $stripped->roles()->detach();
        $stripped->forgetCachedPermissions();
        $this->assertFalse($stripped->fresh()->hasPermissionTo('project.create'));

        $response = $this->actingAs($stripped->fresh())->post('/projects', $this->payload());
        $response->assertStatus(403);
    }

    // 2. Tenant isolation: cross-org project view → 404 (global scope).
    public function test_tenant_isolation_blocks_cross_org_view(): void
    {
        $project = $this->createProjectAs($this->owner);

        $response = $this->actingAs($this->otherUser)->get("/projects/{$project->id}");
        $response->assertStatus(404);
    }

    // 3a. Owner and member can view; 3b. non-member without view.all → 403.
    public function test_member_can_view_but_non_member_cannot(): void
    {
        $project = $this->createProjectAs($this->owner);

        // Add member.
        $this->actingAs($this->owner)->post("/projects/{$project->id}/members", [
            'user_id' => $this->member->id,
            'role'    => 'member',
        ]);

        $this->withoutVite()->actingAs($this->owner)->get("/projects/{$project->id}")->assertOk();
        $this->withoutVite()->actingAs($this->member)->get("/projects/{$project->id}")->assertOk();

        // nonMember (asn, no view.all, not a member) → 403.
        $this->withoutVite()->actingAs($this->nonMember)->get("/projects/{$project->id}")->assertStatus(403);
    }

    // 4a. Valid state path draft→planning→active.
    public function test_state_machine_allows_valid_transitions(): void
    {
        $project = $this->createProjectAs($this->owner);

        $this->actingAs($this->owner)->post("/projects/{$project->id}/transition", ['status' => 'planning'])
            ->assertRedirect();
        $project->refresh();
        $this->assertEquals(ProjectStatus::PLANNING->value, $project->status->value);

        $this->actingAs($this->owner)->post("/projects/{$project->id}/transition", ['status' => 'active'])
            ->assertRedirect();
        $project->refresh();
        $this->assertEquals(ProjectStatus::ACTIVE->value, $project->status->value);
        $this->assertNotNull($project->actual_start_date);

        $this->assertDatabaseHas('project_status_histories', [
            'project_id'  => $project->id,
            'from_status' => 'draft',
            'to_status'   => 'planning',
            'changed_by'  => $this->owner->id,
        ]);
    }

    // 4b. Invalid transition draft→completed rejected (session errors, no change).
    public function test_invalid_transition_is_rejected(): void
    {
        $project = $this->createProjectAs($this->owner);

        $response = $this->actingAs($this->owner)
            ->from("/projects/{$project->id}")
            ->post("/projects/{$project->id}/transition", ['status' => 'completed']);

        $response->assertSessionHasErrors('status');

        $this->assertDatabaseHas('projects', [
            'id'     => $project->id,
            'status' => ProjectStatus::DRAFT->value,
        ]);
    }

    // 5. Milestone: add + complete recomputes progress_percent; perm enforced.
    public function test_milestone_complete_recomputes_progress(): void
    {
        $project = $this->createProjectAs($this->owner);

        // Add two milestones.
        $this->actingAs($this->owner)->post("/projects/{$project->id}/milestones", [
            'name' => 'Perencanaan', 'due_date' => '2026-07-01',
        ])->assertRedirect();
        $this->actingAs($this->owner)->post("/projects/{$project->id}/milestones", [
            'name' => 'Eksekusi', 'due_date' => '2026-09-01',
        ])->assertRedirect();

        $project->refresh();
        $this->assertEquals(0, $project->progress_percent);

        $first = ProjectMilestone::where('project_id', $project->id)->first();
        $this->actingAs($this->owner)->post("/projects/milestones/{$first->id}/complete")
            ->assertRedirect();

        $project->refresh();
        $this->assertEquals(50, $project->progress_percent); // 1 of 2 done
        $first->refresh();
        $this->assertEquals(MilestoneStatus::COMPLETED->value, $first->status->value);
        $this->assertNotNull($first->completed_at);
    }

    // 5b. Member with milestone perm can add; non-member cannot manage milestone.
    public function test_milestone_management_requires_membership(): void
    {
        $project = $this->createProjectAs($this->owner);

        // nonMember (asn) has project.milestone.manage permission but is NOT a
        // member of this project → manageMilestone policy denies.
        $response = $this->actingAs($this->nonMember)->post("/projects/{$project->id}/milestones", [
            'name' => 'Selundupan', 'due_date' => '2026-08-01',
        ]);
        $response->assertStatus(403);
    }

    // 6. Risk: add risk with level + close.
    public function test_risk_add_and_close(): void
    {
        $project = $this->createProjectAs($this->owner);

        $this->actingAs($this->owner)->post("/projects/{$project->id}/risks", [
            'title'       => 'Keterlambatan pengadaan',
            'risk_level'  => 'high',
            'probability' => 4,
            'impact'      => 5,
        ])->assertRedirect();

        $risk = ProjectRisk::where('project_id', $project->id)->first();
        $this->assertNotNull($risk);
        $this->assertEquals('high', $risk->risk_level->value);
        $this->assertEquals('open', $risk->status);

        $this->actingAs($this->owner)->post("/projects/risks/{$risk->id}/close")
            ->assertRedirect();
        $risk->refresh();
        $this->assertEquals('closed', $risk->status);
    }

    // 7. Member add/remove; manageMembers requires owner/manager perm.
    public function test_member_add_remove_and_permission_enforced(): void
    {
        $project = $this->createProjectAs($this->owner);

        // Owner adds member.
        $this->actingAs($this->owner)->post("/projects/{$project->id}/members", [
            'user_id' => $this->member->id, 'role' => 'member',
        ])->assertRedirect();

        $membership = ProjectMember::where('project_id', $project->id)
            ->where('user_id', $this->member->id)->first();
        $this->assertNotNull($membership);
        $this->assertNull($membership->left_at);

        // A plain member (asn, not owner/manager) cannot manage members → 403.
        $response = $this->actingAs($this->member)->post("/projects/{$project->id}/members", [
            'user_id' => $this->nonMember->id, 'role' => 'member',
        ]);
        $response->assertStatus(403);

        // Owner removes member (soft remove via left_at).
        $this->actingAs($this->owner)
            ->delete("/projects/{$project->id}/members/{$membership->id}")
            ->assertRedirect();
        $membership->refresh();
        $this->assertNotNull($membership->left_at);
    }

    // ── Cross-tenant authorization hardening ─────────────────────────────────

    // SEC-1. Adding an org-B user as a member of an org-A project → 422, not added.
    public function test_cannot_add_cross_org_user_as_member(): void
    {
        $project = $this->createProjectAs($this->owner);

        $response = $this->actingAs($this->owner)
            ->from("/projects/{$project->id}")
            ->post("/projects/{$project->id}/members", [
                'user_id' => $this->otherUser->id, // org B
                'role'    => 'member',
            ]);

        $response->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $project->id,
            'user_id'    => $this->otherUser->id,
        ]);
    }

    // SEC-2. Setting manager_id to an org-B user → 422 (privilege escalation block).
    public function test_cannot_set_cross_org_manager_on_create(): void
    {
        $response = $this->actingAs($this->owner)
            ->from('/projects')
            ->post('/projects', $this->payload(['manager_id' => $this->otherUser->id]));

        $response->assertSessionHasErrors('manager_id');

        $this->assertDatabaseMissing('projects', ['manager_id' => $this->otherUser->id]);
    }

    public function test_cannot_set_cross_org_manager_on_update(): void
    {
        $project = $this->createProjectAs($this->owner);

        $response = $this->actingAs($this->owner)
            ->from("/projects/{$project->id}")
            ->patch("/projects/{$project->id}", ['manager_id' => $this->otherUser->id]);

        $response->assertSessionHasErrors('manager_id');
        $this->assertNull($project->fresh()->manager_id);
    }

    // SEC-3. team_id must belong to the acting pemda → cross-org team rejected.
    public function test_cannot_set_cross_org_team_on_create(): void
    {
        $foreignTeam = \App\Models\Team::withoutGlobalScopes()->create([
            'id'              => (string) Str::ulid(),
            'pemda_id'        => $this->otherOrg->id,
            'organization_id' => $this->otherOrg->id,
            'type'            => 'functional',
            'name'            => 'Tim Lain',
            'is_active'       => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->from('/projects')
            ->post('/projects', $this->payload(['team_id' => $foreignTeam->id]));

        $response->assertSessionHasErrors('team_id');
    }

    // SEC-4. risk owner_id must belong to the project's org → org-B owner rejected.
    public function test_cannot_set_cross_org_risk_owner(): void
    {
        $project = $this->createProjectAs($this->owner);

        $response = $this->actingAs($this->owner)
            ->from("/projects/{$project->id}")
            ->post("/projects/{$project->id}/risks", [
                'title'    => 'Risiko bocor',
                'owner_id' => $this->otherUser->id, // org B
            ]);

        $response->assertSessionHasErrors('owner_id');

        $this->assertDatabaseMissing('project_risks', [
            'project_id' => $project->id,
            'owner_id'   => $this->otherUser->id,
        ]);
    }

    // 8. Close: owner with project.close transitions to completed; non-owner → 403.
    public function test_close_requires_owner_and_close_permission(): void
    {
        $project = $this->createProjectAs($this->owner);

        // Move to a closeable state: draft→planning→active→closing.
        foreach (['planning', 'active', 'closing'] as $status) {
            $this->actingAs($this->owner)->post("/projects/{$project->id}/transition", ['status' => $status]);
        }
        $project->refresh();
        $this->assertEquals(ProjectStatus::CLOSING->value, $project->status->value);

        // Non-owner (member without project.close) → 403.
        $this->actingAs($this->owner)->post("/projects/{$project->id}/members", [
            'user_id' => $this->member->id, 'role' => 'member',
        ]);
        $this->actingAs($this->member)->post("/projects/{$project->id}/close")
            ->assertStatus(403);

        // Owner (kepala_opd has project.close) closes → completed.
        $this->actingAs($this->owner)->post("/projects/{$project->id}/close")
            ->assertRedirect();
        $project->refresh();
        $this->assertEquals(ProjectStatus::COMPLETED->value, $project->status->value);
        $this->assertNotNull($project->actual_end_date);
    }
}
