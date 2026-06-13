<?php

namespace Tests\Feature\Performance;

use App\Enums\PerformanceStatus;
use App\Enums\SkpPerspective;
use App\Models\Organization;
use App\Models\SkpEvaluation;
use App\Models\SkpIndicator;
use App\Models\SkpPeriod;
use App\Models\SkpPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkpTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $asn;
    private User $superior;
    private User $kepalaOpd;
    private SkpPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Dinas Kinerja Test',
            'code'      => 'DKT',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->asn = $this->makeUser('199001012020011001', 'Pegawai ASN', 'asn@skp.id', 'asn');
        $this->superior = $this->makeUser('198001012010011001', 'Kepala Bidang', 'bidang@skp.id', 'kepala_bidang');
        $this->kepalaOpd = $this->makeUser('197001012000011001', 'Kepala OPD', 'opd@skp.id', 'kepala_opd');

        $this->period = SkpPeriod::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $this->org->id,
            'year'            => 2026,
            'semester'        => null,
            'name'            => 'Tahun 2026',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'is_active'       => true,
        ]);
    }

    private function makeUser(string $nip, string $name, string $email, string $role): User
    {
        $user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => $nip,
            'name'            => $name,
            'email'           => $email,
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function createPlanFor(User $user, ?User $superior = null): SkpPlan
    {
        return SkpPlan::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $user->organization_id,
            'user_id'         => $user->id,
            'period_id'       => $this->period->id,
            'superior_id'     => $superior?->id,
            'status'          => PerformanceStatus::PLANNING->value,
            'created_by'      => $user->id,
            'version'         => 1,
        ]);
    }

    private function addIndicator(SkpPlan $plan, float $target = 10, float $realization = 0): SkpIndicator
    {
        return SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PENERIMA_LAYANAN->value,
            'name'              => 'Indikator Test',
            'target_value'      => $target,
            'target_unit'       => 'dokumen',
            'weight'            => 100,
            'realization_value' => $realization,
            'achievement_pct'   => $target > 0 ? round($realization / $target * 100, 2) : 0.0,
            'sort_order'        => 0,
        ]);
    }

    // ── 6. Create plan ─────────────────────────────────────────────────────

    public function test_asn_can_create_skp_plan(): void
    {
        $response = $this->actingAs($this->asn)->post('/performance/plans', [
            'period_id'   => $this->period->id,
            'superior_id' => $this->superior->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('skp_plans', [
            'user_id'   => $this->asn->id,
            'period_id' => $this->period->id,
            'status'    => PerformanceStatus::PLANNING->value,
        ]);
    }

    public function test_unique_user_period_constraint_enforced(): void
    {
        // First plan succeeds
        $this->actingAs($this->asn)->post('/performance/plans', [
            'period_id'   => $this->period->id,
            'superior_id' => $this->superior->id,
        ]);

        $this->assertDatabaseCount('skp_plans', 1);

        // Second plan for same user+period must fail with validation error
        $response = $this->actingAs($this->asn)
            ->from('/performance')
            ->post('/performance/plans', [
                'period_id'   => $this->period->id,
                'superior_id' => $this->superior->id,
            ]);

        $response->assertSessionHasErrors('period_id');
        $this->assertDatabaseCount('skp_plans', 1);
    }

    // ── 7. RBAC: ASN cannot evaluate ────────────────────────────────────────

    public function test_asn_cannot_evaluate_any_plan(): void
    {
        $plan = $this->createPlanFor($this->superior, $this->kepalaOpd);

        $response = $this->actingAs($this->asn)->post("/performance/plans/{$plan->id}/evaluate", [
            'behavior_service'    => 80,
            'behavior_commit'     => 80,
            'behavior_initiative' => 80,
            'behavior_teamwork'   => 80,
        ]);

        $response->assertStatus(403);
    }

    // ── 8. Tenant isolation ─────────────────────────────────────────────────

    public function test_cross_org_plan_view_denied(): void
    {
        $otherOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Dinas Lain',
            'code'      => 'DLN',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $otherOrg->update(['pemda_id' => $otherOrg->id]);

        $otherUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199901012020011099',
            'name'            => 'ASN Lain',
            'email'           => 'other@other.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $otherOrg->id,
            'pemda_id'        => $otherOrg->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $otherUser->assignRole('asn');

        // Create period in otherOrg
        $otherPeriod = SkpPeriod::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $otherOrg->id,
            'year'            => 2026,
            'name'            => 'Tahun 2026 Lain',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'is_active'       => true,
        ]);

        $plan = SkpPlan::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $otherOrg->id,
            'user_id'         => $otherUser->id,
            'period_id'       => $otherPeriod->id,
            'status'          => PerformanceStatus::PLANNING->value,
            'created_by'      => $otherUser->id,
            'version'         => 1,
        ]);

        // asn from this org tries to view plan from other org → 404 via BelongsToOrganization
        $response = $this->actingAs($this->asn)->get("/performance/plans/{$plan->id}");
        $response->assertStatus(404);
    }

    // ── 9. Workflow: submit → approve → active; realization updates achievement ──

    public function test_submit_and_approve_transitions_to_active(): void
    {
        // kepala_opd has performance.skp.approve; use them as superior/approver
        $plan = $this->createPlanFor($this->asn, $this->kepalaOpd);

        // Submit
        $this->actingAs($this->asn)
            ->post("/performance/plans/{$plan->id}/submit")
            ->assertRedirect();

        $plan->refresh();
        $this->assertNotNull($plan->submitted_at);

        // Approve by kepala_opd (who is the superior)
        $this->actingAs($this->kepalaOpd)
            ->post("/performance/plans/{$plan->id}/approve")
            ->assertRedirect();

        $plan->refresh();
        $this->assertEquals(PerformanceStatus::ACTIVE, $plan->status);
        $this->assertEquals($this->kepalaOpd->id, $plan->approved_by);
        $this->assertNotNull($plan->approved_at);
    }

    public function test_add_realization_updates_indicator_achievement(): void
    {
        $plan = $this->createPlanFor($this->asn, $this->superior);

        // Force to active
        $plan->update(['status' => PerformanceStatus::ACTIVE->value]);

        $indicator = $this->addIndicator($plan, target: 10, realization: 0);

        // Add realization
        $this->actingAs($this->asn)
            ->post("/performance/indicators/{$indicator->id}/realizations", [
                'realization_value' => 7,
                'realization_date'  => '2026-06-01',
                'description'       => 'Laporan kegiatan',
            ])
            ->assertRedirect();

        $indicator->refresh();
        $this->assertEqualsWithDelta(7.0, (float) $indicator->realization_value, 0.001);
        $this->assertEqualsWithDelta(70.0, (float) $indicator->achievement_pct, 0.01);
    }

    // ── 10. Evaluate produces correct scores; owner cannot self-evaluate ────

    public function test_superior_can_evaluate_and_produces_correct_scores(): void
    {
        // kepala_opd has performance.skp.review; use them as evaluator
        $plan = $this->createPlanFor($this->asn, $this->kepalaOpd);
        $plan->update(['status' => PerformanceStatus::ACTIVE->value]);

        // Add indicator with known achievement
        $this->addIndicator($plan, target: 10, realization: 9);
        // Reload to set achievement_pct = 90
        SkpIndicator::where('skp_plan_id', $plan->id)->update(['achievement_pct' => 90.0]);

        $this->actingAs($this->kepalaOpd)
            ->post("/performance/plans/{$plan->id}/evaluate", [
                'behavior_service'    => 90,
                'behavior_commit'     => 80,
                'behavior_initiative' => 85,
                'behavior_teamwork'   => 75,
                // leadership null → 4-dim average
            ])
            ->assertRedirect();

        $eval = SkpEvaluation::where('skp_plan_id', $plan->id)->first();
        $this->assertNotNull($eval);

        // performance_score = 90.0 (one indicator weight=100)
        $this->assertEqualsWithDelta(90.0, (float) $eval->performance_score, 0.01);

        // behavior_score = (90+80+85+75)/4 = 82.5
        $this->assertEqualsWithDelta(82.5, (float) $eval->behavior_score, 0.01);

        // final_score = 0.7*90 + 0.3*82.5 = 63 + 24.75 = 87.75
        $this->assertEqualsWithDelta(87.75, (float) $eval->final_score, 0.01);

        // predicate: 87.75 → BAIK (76 ≤ x < 90)
        $this->assertEquals('baik', $eval->predicate?->value);
    }

    public function test_owner_cannot_self_evaluate(): void
    {
        $plan = $this->createPlanFor($this->asn, $this->superior);
        $plan->update(['status' => PerformanceStatus::ACTIVE->value]);

        $response = $this->actingAs($this->asn)->post("/performance/plans/{$plan->id}/evaluate", [
            'behavior_service'    => 80,
            'behavior_commit'     => 80,
            'behavior_initiative' => 80,
            'behavior_teamwork'   => 80,
        ]);

        $response->assertStatus(403);
    }

    // ── 11. State machine: invalid transition rejected ────────────────────

    public function test_invalid_transition_planning_to_finalized_rejected(): void
    {
        $plan = $this->createPlanFor($this->asn, $this->superior);
        // Plan is in planning; finalized is not allowed directly

        $response = $this->actingAs($this->kepalaOpd)
            ->from('/performance')
            ->post("/performance/plans/{$plan->id}/transition", [
                'status' => 'finalized',
            ]);

        $response->assertSessionHasErrors('status');

        $plan->refresh();
        $this->assertEquals(PerformanceStatus::PLANNING, $plan->status);
    }

    // ── FIX 1: behavior dimension = 120 persists; final_score correct ────────

    public function test_evaluate_with_behavior_120_persists_and_computes_final_score(): void
    {
        // Regression for the NUMERIC(4,2) overflow: a behavior score of 120 must
        // persist and feed the formula. (SQLite won't enforce the precision, so
        // this asserts evaluate() succeeds + the computed final_score matches.)
        $plan = $this->createPlanFor($this->asn, $this->kepalaOpd);
        $plan->update(['status' => PerformanceStatus::ACTIVE->value]);

        // One indicator at 100% achievement → performance_score = 100
        $this->addIndicator($plan, target: 10, realization: 10);
        SkpIndicator::where('skp_plan_id', $plan->id)->update(['achievement_pct' => 100.0]);

        $this->actingAs($this->kepalaOpd)
            ->post("/performance/plans/{$plan->id}/evaluate", [
                'behavior_service'    => 120,
                'behavior_commit'     => 120,
                'behavior_initiative' => 120,
                'behavior_teamwork'   => 120,
                // leadership null → 4-dim average = 120
            ])
            ->assertRedirect();

        $eval = SkpEvaluation::where('skp_plan_id', $plan->id)->first();
        $this->assertNotNull($eval);

        // behavior_score = (120+120+120+120)/4 = 120 (must persist, not overflow)
        $this->assertEqualsWithDelta(120.0, (float) $eval->behavior_score, 0.01);
        // performance_score = 100
        $this->assertEqualsWithDelta(100.0, (float) $eval->performance_score, 0.01);
        // final_score = 0.7*100 + 0.3*120 = 70 + 36 = 106
        $this->assertEqualsWithDelta(106.0, (float) $eval->final_score, 0.01);
        // predicate: 106 → SANGAT_BAIK
        $this->assertEquals('sangat_baik', $eval->predicate?->value);
    }

    // ── FIX 5: cross-org child resource mutation isolation ───────────────────

    private function makeOtherOrgPlanWithIndicator(): array
    {
        $otherOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Dinas Seberang',
            'code'      => 'DSB',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $otherOrg->update(['pemda_id' => $otherOrg->id]);

        $otherUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199801012020011055',
            'name'            => 'ASN Seberang',
            'email'           => 'seberang@other.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $otherOrg->id,
            'pemda_id'        => $otherOrg->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $otherUser->assignRole('asn');

        $otherPeriod = SkpPeriod::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $otherOrg->id,
            'year'            => 2026,
            'name'            => 'Periode Seberang',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'is_active'       => true,
        ]);

        $plan = SkpPlan::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $otherOrg->id,
            'user_id'         => $otherUser->id,
            'period_id'       => $otherPeriod->id,
            'status'          => PerformanceStatus::ACTIVE->value,
            'created_by'      => $otherUser->id,
            'version'         => 1,
        ]);

        $indicator = SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PENERIMA_LAYANAN->value,
            'name'              => 'Indikator Seberang',
            'target_value'      => 10,
            'target_unit'       => 'dokumen',
            'weight'            => 100,
            'realization_value' => 0,
            'achievement_pct'   => 0.0,
            'sort_order'        => 0,
        ]);

        return [$plan, $indicator];
    }

    public function test_cross_org_user_cannot_patch_indicator(): void
    {
        [, $indicator] = $this->makeOtherOrgPlanWithIndicator();

        $response = $this->actingAs($this->asn)
            ->patch("/performance/indicators/{$indicator->id}", [
                'name' => 'Hacked Name',
            ]);

        // Parent plan resolved via org-scoped SkpPlan → 404 (never 200/500)
        $this->assertContains($response->status(), [403, 404]);
        $indicator->refresh();
        $this->assertEquals('Indikator Seberang', $indicator->name);
    }

    public function test_cross_org_user_cannot_delete_indicator(): void
    {
        [, $indicator] = $this->makeOtherOrgPlanWithIndicator();

        $response = $this->actingAs($this->asn)
            ->delete("/performance/indicators/{$indicator->id}");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('skp_indicators', [
            'id'         => $indicator->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cross_org_user_cannot_add_realization_to_foreign_indicator(): void
    {
        [, $indicator] = $this->makeOtherOrgPlanWithIndicator();

        $response = $this->actingAs($this->asn)
            ->post("/performance/indicators/{$indicator->id}/realizations", [
                'realization_value' => 5,
                'realization_date'  => '2026-06-01',
            ]);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseMissing('skp_realizations', [
            'indicator_id' => $indicator->id,
        ]);
    }

    // ── FIX 4: view.all is READ only; cannot approve/transition foreign plan ──

    public function test_viewer_with_view_all_but_not_superior_cannot_approve(): void
    {
        // kepala_bidang has performance.view.all + skp.review but NOT skp.approve,
        // and is NOT the assigned superior of this plan. Must be forbidden.
        $plan = $this->createPlanFor($this->asn, $this->kepalaOpd);
        $plan->update(['status' => PerformanceStatus::ACTIVE->value]);

        $this->assertTrue($this->superior->hasPermissionTo('performance.view.all'));
        $this->assertFalse($this->superior->hasPermissionTo('performance.skp.approve'));

        $response = $this->actingAs($this->superior)
            ->post("/performance/plans/{$plan->id}/approve");

        $response->assertStatus(403);
    }

    public function test_viewer_with_view_all_but_not_superior_cannot_transition(): void
    {
        $plan = $this->createPlanFor($this->asn, $this->kepalaOpd);
        $plan->update(['status' => PerformanceStatus::ACTIVE->value]);

        $response = $this->actingAs($this->superior)
            ->from('/performance')
            ->post("/performance/plans/{$plan->id}/transition", [
                'status' => 'evaluating',
            ]);

        $response->assertStatus(403);

        $plan->refresh();
        $this->assertEquals(PerformanceStatus::ACTIVE, $plan->status);
    }

    // ── FIX 3: destroy() emits outbox skp.deleted + audit row ────────────────

    public function test_destroy_plan_emits_outbox_and_audit(): void
    {
        $plan = $this->createPlanFor($this->asn, $this->superior);
        // delete() policy requires owner + PLANNING status — both satisfied.

        $this->actingAs($this->asn)
            ->delete("/performance/plans/{$plan->id}")
            ->assertRedirect();

        // Soft-deleted with deleted_by recorded
        $this->assertSoftDeleted('skp_plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('skp_plans', [
            'id'         => $plan->id,
            'deleted_by' => $this->asn->id,
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'event_type'  => 'skp.deleted',
            'aggregate_id'=> $plan->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'deleted',
            'auditable_type'=> 'SkpPlan',
            'auditable_id' => $plan->id,
        ]);
    }

    public function test_approve_writes_audit_log(): void
    {
        $plan = $this->createPlanFor($this->asn, $this->kepalaOpd);

        $this->actingAs($this->kepalaOpd)
            ->post("/performance/plans/{$plan->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action'        => 'approved',
            'auditable_type'=> 'SkpPlan',
            'auditable_id'  => $plan->id,
        ]);
    }

    // ── FIX 5b / status guard: realization rejected when plan not ACTIVE ─────

    public function test_add_realization_rejected_when_plan_not_active(): void
    {
        // Plan is in PLANNING (not ACTIVE) → addRealization policy denies.
        $plan = $this->createPlanFor($this->asn, $this->superior);
        $indicator = $this->addIndicator($plan, target: 10, realization: 0);

        $response = $this->actingAs($this->asn)
            ->post("/performance/indicators/{$indicator->id}/realizations", [
                'realization_value' => 5,
                'realization_date'  => '2026-06-01',
            ]);

        $this->assertContains($response->status(), [403, 422]);
        $this->assertDatabaseMissing('skp_realizations', [
            'indicator_id' => $indicator->id,
        ]);
    }

    // ── FIX 6: target_value must be > 0 (validation) ─────────────────────────

    public function test_add_indicator_rejects_zero_target_value(): void
    {
        $plan = $this->createPlanFor($this->asn, $this->superior);

        $response = $this->actingAs($this->asn)
            ->from("/performance/plans/{$plan->id}")
            ->post("/performance/plans/{$plan->id}/indicators", [
                'perspective'  => SkpPerspective::PENERIMA_LAYANAN->value,
                'name'         => 'Indikator Nol',
                'target_value' => 0,
                'target_unit'  => 'dokumen',
                'weight'       => 100,
            ]);

        $response->assertSessionHasErrors('target_value');
        $this->assertDatabaseMissing('skp_indicators', [
            'skp_plan_id' => $plan->id,
            'name'        => 'Indikator Nol',
        ]);
    }

    // ── RBAC deny create + full state-machine happy path ─────────────────────

    public function test_role_lacking_skp_create_cannot_create_plan(): void
    {
        // Build a role with NO performance.skp.create permission.
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'no_skp_create', 'guard_name' => 'web']
        );
        $role->syncPermissions(['performance.view.own']);

        $user = $this->makeUser('199501012020011077', 'Tanpa SKP', 'noskp@skp.id', 'no_skp_create');

        $response = $this->actingAs($user)->post('/performance/plans', [
            'period_id'   => $this->period->id,
            'superior_id' => $this->superior->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_full_state_machine_happy_path_via_transition(): void
    {
        // kepala_opd is superior + holds skp.approve → mutation authority.
        $plan = $this->createPlanFor($this->asn, $this->kepalaOpd);
        $plan->update(['status' => PerformanceStatus::ACTIVE->value]);

        // active → evaluating
        $this->actingAs($this->kepalaOpd)
            ->post("/performance/plans/{$plan->id}/transition", ['status' => 'evaluating'])
            ->assertRedirect();
        $this->assertEquals(PerformanceStatus::EVALUATING, $plan->fresh()->status);

        // evaluating → finalized
        $this->actingAs($this->kepalaOpd)
            ->post("/performance/plans/{$plan->id}/transition", ['status' => 'finalized'])
            ->assertRedirect();
        $this->assertEquals(PerformanceStatus::FINALIZED, $plan->fresh()->status);

        // finalized → archived
        $this->actingAs($this->kepalaOpd)
            ->post("/performance/plans/{$plan->id}/transition", ['status' => 'archived'])
            ->assertRedirect();
        $this->assertEquals(PerformanceStatus::ARCHIVED, $plan->fresh()->status);
    }
}
