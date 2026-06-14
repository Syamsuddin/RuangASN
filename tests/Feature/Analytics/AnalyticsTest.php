<?php

namespace Tests\Feature\Analytics;

use App\Enums\MeetingStatus;
use App\Enums\PerformancePredicate;
use App\Enums\PerformanceStatus;
use App\Enums\TaskStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\SkpEvaluation;
use App\Models\SkpPeriod;
use App\Models\SkpPlan;
use App\Models\Task;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $orgB;
    private User $adminPemda;
    private AnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org  = $this->makeOrg('Dinas Analytics A', 'DAA');
        $this->orgB = $this->makeOrg('Dinas Analytics B', 'DAB');

        $this->adminPemda = $this->makeUser($this->org, '199001012020011001', 'Admin Pemda', 'admin@an.id', 'admin_pemda');
        $this->analytics  = app(AnalyticsService::class);
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

    private function makeUser(Organization $org, string $nip, string $name, string $email, string $role): User
    {
        $user = User::create([
            'id' => (string) Str::ulid(), 'nip' => $nip, 'name' => $name, 'email' => $email,
            'password' => Hash::make('password'), 'user_type' => 'pns', 'status' => 'active',
            'organization_id' => $org->id, 'pemda_id' => $org->pemda_id,
            'timezone' => 'Asia/Jakarta', 'locale' => 'id',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeTask(Organization $org, User $creator, string $status, ?string $due = null): Task
    {
        return Task::withoutEvents(fn () => Task::create([
            'id' => (string) Str::ulid(), 'organization_id' => $org->id, 'pemda_id' => $org->pemda_id,
            'title' => 'Tugas ' . Str::random(5), 'task_type' => 'personal',
            'status' => $status, 'priority' => 'medium',
            'creator_id' => $creator->id, 'assignee_id' => $creator->id,
            'due_date' => $due, 'data_classification' => 2, 'version' => 1,
            'created_by' => $creator->id,
        ]));
    }

    private function makeEvaluatedPlan(Organization $org, User $user, float $finalScore, PerformancePredicate $pred): SkpPlan
    {
        $period = SkpPeriod::create([
            'id' => (string) Str::ulid(), 'organization_id' => $org->id, 'year' => 2026,
            'name' => 'Periode ' . Str::random(4), 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $plan = SkpPlan::create([
            'id' => (string) Str::ulid(), 'organization_id' => $org->id, 'user_id' => $user->id,
            'period_id' => $period->id, 'status' => PerformanceStatus::EVALUATING->value,
            'created_by' => $user->id, 'version' => 1,
        ]);

        SkpEvaluation::create([
            'id' => (string) Str::ulid(), 'skp_plan_id' => $plan->id,
            'performance_score' => $finalScore, 'behavior_score' => $finalScore,
            'final_score' => $finalScore, 'predicate' => $pred->value,
            'behavior_service' => 90, 'evaluated_by' => $user->id, 'evaluated_at' => now(),
        ]);

        return $plan;
    }

    // ── 1. computeSnapshot returns correct aggregates ───────────────────────

    public function test_compute_snapshot_returns_correct_aggregates(): void
    {
        // 2 completed, 1 overdue, 1 in_progress for org A.
        $this->makeTask($this->org, $this->adminPemda, TaskStatus::COMPLETED->value);
        $this->makeTask($this->org, $this->adminPemda, TaskStatus::COMPLETED->value);
        $this->makeTask($this->org, $this->adminPemda, TaskStatus::OPEN->value, Carbon::yesterday()->toDateString());
        $this->makeTask($this->org, $this->adminPemda, TaskStatus::IN_PROGRESS->value);

        Meeting::withoutEvents(fn () => Meeting::create([
            'id' => (string) Str::ulid(), 'organization_id' => $this->org->id, 'pemda_id' => $this->org->pemda_id,
            'title' => 'Rapat A', 'meeting_type' => 'internal', 'meeting_mode' => 'offline',
            'status' => MeetingStatus::COMPLETED->value, 'scheduled_at' => now(), 'duration_minutes' => 60,
            'host_id' => $this->adminPemda->id, 'data_classification' => 2, 'version' => 1,
            'created_by' => $this->adminPemda->id,
        ]));

        $this->makeEvaluatedPlan($this->org, $this->adminPemda, 88.0, PerformancePredicate::BAIK);

        $m = $this->analytics->computeSnapshot($this->org, Carbon::today());

        $this->assertSame(4, $m['tasks']['total']);
        $this->assertSame(2, $m['tasks']['completed']);
        $this->assertSame(1, $m['tasks']['overdue']);
        $this->assertSame(1, $m['tasks']['in_progress']);
        $this->assertEqualsWithDelta(50.0, $m['tasks']['completion_rate'], 0.01);

        $this->assertSame(1, $m['meetings']['completed']);

        $this->assertSame(1, $m['skp']['evaluated']);
        $this->assertEqualsWithDelta(88.0, $m['skp']['avg_final_score'], 0.01);
        $this->assertSame(1, $m['skp']['predicate_distribution']['baik']);
        $this->assertSame(0, $m['skp']['predicate_distribution']['sangat_baik']);
    }

    // ── 2. snapshot() is idempotent per org+date ────────────────────────────

    public function test_snapshot_is_idempotent_per_org_and_date(): void
    {
        $date = Carbon::today();

        $first = $this->analytics->snapshot($this->org, $date);
        $second = $this->analytics->snapshot($this->org, $date);

        $this->assertSame($first->id, $second->fresh()->id);
        $this->assertSame(1, AnalyticsSnapshot::withoutGlobalScopes()
            ->where('organization_id', $this->org->id)->count());
    }

    public function test_snapshot_updates_metrics_on_rerun(): void
    {
        $date = Carbon::today();
        $this->analytics->snapshot($this->org, $date);

        // Add a task, re-run → same row, updated metrics.
        $this->makeTask($this->org, $this->adminPemda, TaskStatus::COMPLETED->value);
        $snap = $this->analytics->snapshot($this->org, $date);

        $this->assertSame(1, (int) $snap->metrics['tasks']['total']);
        $this->assertSame(1, AnalyticsSnapshot::withoutGlobalScopes()
            ->where('organization_id', $this->org->id)->count());
    }

    // ── 3. analytics:snapshot command persists a snapshot per org ───────────

    public function test_command_persists_snapshot_per_org(): void
    {
        $this->artisan('analytics:snapshot', ['--date' => Carbon::today()->toDateString()])
            ->assertSuccessful();

        // One per organization (A, B, + seeded orgs). At least A and B exist.
        $this->assertSame(1, AnalyticsSnapshot::withoutGlobalScopes()
            ->where('organization_id', $this->org->id)->count());
        $this->assertSame(1, AnalyticsSnapshot::withoutGlobalScopes()
            ->where('organization_id', $this->orgB->id)->count());
    }

    // ── 6. tenant isolation: org B's data not counted in org A ──────────────

    public function test_metrics_are_tenant_scoped(): void
    {
        $userB = $this->makeUser($this->orgB, '199101012020011002', 'User B', 'userb@an.id', 'asn');

        // 3 completed tasks in org B; org A has none.
        $this->makeTask($this->orgB, $userB, TaskStatus::COMPLETED->value);
        $this->makeTask($this->orgB, $userB, TaskStatus::COMPLETED->value);
        $this->makeTask($this->orgB, $userB, TaskStatus::COMPLETED->value);

        $mA = $this->analytics->computeSnapshot($this->org, Carbon::today());
        $mB = $this->analytics->computeSnapshot($this->orgB, Carbon::today());

        $this->assertSame(0, $mA['tasks']['total']);
        $this->assertSame(3, $mB['tasks']['total']);
        $this->assertSame(3, $mB['tasks']['completed']);
    }

    public function test_trend_returns_series_for_org(): void
    {
        $this->analytics->snapshot($this->org, Carbon::today()->subDays(2));
        $this->analytics->snapshot($this->org, Carbon::today()->subDay());
        $this->analytics->snapshot($this->org, Carbon::today());

        $trend = $this->analytics->trend($this->org, 30);

        $this->assertCount(3, $trend);
        $this->assertArrayHasKey('completion_rate', $trend[0]);
        $this->assertArrayHasKey('date', $trend[0]);
    }
}
