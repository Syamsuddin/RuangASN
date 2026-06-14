<?php

namespace Tests\Feature\Analytics;

use App\Enums\AiAgentType;
use App\Enums\AiIntent;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AiOrchestratorService;
use App\Services\Ai\IntentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExecutiveTest extends TestCase
{
    use RefreshDatabase;

    private Organization $pemda;
    private Organization $childOpd;
    private Organization $pemdaB;
    private User $adminPemda;
    private User $kepalaOpd;
    private User $kepalaBidang;
    private User $asn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        // pemda (self-parent) + a child OPD under it.
        $this->pemda = $this->makePemda('Pemda Kab Test', 'PKT');
        $this->childOpd = $this->makeChild('Dinas Anak', 'DAN', $this->pemda);
        $this->pemdaB = $this->makePemda('Pemda Lain', 'PLN');

        // admin_pemda has analytics.view.opd + view.pemda; kepala_opd has opd only.
        $this->adminPemda   = $this->makeUser($this->pemda, '199001012020011001', 'Admin Pemda', 'admin@ex.id', 'admin_pemda');
        $this->kepalaOpd    = $this->makeUser($this->pemda, '198001012010011001', 'Kepala OPD', 'opd@ex.id', 'kepala_opd');
        $this->kepalaBidang = $this->makeUser($this->pemda, '197501012005011001', 'Kepala Bidang', 'bidang@ex.id', 'kepala_bidang');
        $this->asn          = $this->makeUser($this->pemda, '199501012020011009', 'Pegawai ASN', 'asn@ex.id', 'asn');
    }

    private function makePemda(string $name, string $code): Organization
    {
        $org = Organization::create([
            'id' => (string) Str::ulid(), 'type' => 'government',
            'name' => $name, 'code' => $code, 'is_active' => true, 'depth' => 0,
        ]);
        $org->update(['pemda_id' => $org->id]);

        return $org;
    }

    private function makeChild(string $name, string $code, Organization $pemda): Organization
    {
        return Organization::create([
            'id' => (string) Str::ulid(), 'type' => 'department',
            'name' => $name, 'code' => $code, 'is_active' => true, 'depth' => 1,
            'parent_id' => $pemda->id, 'pemda_id' => $pemda->id,
        ]);
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

    // ── 4. Executive index gating ───────────────────────────────────────────

    public function test_admin_pemda_can_view_executive_dashboard(): void
    {
        $this->withoutVite()->actingAs($this->adminPemda)
            ->get('/executive')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Executive/Index')
                ->has('current.tasks')
                ->has('trend')
            );
    }

    public function test_kepala_opd_can_view_executive_dashboard(): void
    {
        $this->withoutVite()->actingAs($this->kepalaOpd)
            ->get('/executive')
            ->assertStatus(200);
    }

    public function test_asn_without_opd_permission_is_forbidden(): void
    {
        $this->withoutVite()->actingAs($this->asn)
            ->get('/executive')
            ->assertStatus(403);
    }

    // ── 5. opdComparison only for analytics.view.pemda ──────────────────────

    public function test_opd_comparison_populated_only_for_pemda_permission(): void
    {
        // admin_pemda has analytics.view.pemda → opdComparison present
        // (the pemda itself + its child OPD = 2 rows).
        $this->withoutVite()->actingAs($this->adminPemda)
            ->get('/executive')
            ->assertInertia(fn (Assert $page) => $page
                ->has('opdComparison', 2)
            );

        // kepala_opd lacks analytics.view.pemda → opdComparison null.
        $this->withoutVite()->actingAs($this->kepalaOpd)
            ->get('/executive')
            ->assertInertia(fn (Assert $page) => $page->where('opdComparison', null));
    }

    public function test_opd_comparison_is_scoped_to_pemda_children(): void
    {
        $comparison = app(\App\Services\AnalyticsService::class)->opdComparison($this->pemda);
        $ids = array_column($comparison, 'organization_id');

        $this->assertContains($this->pemda->id, $ids);
        $this->assertContains($this->childOpd->id, $ids);
        // Second pemda (and its absence of children) must NOT appear.
        $this->assertNotContains($this->pemdaB->id, $ids);
    }

    // ── 7. AI executive brief + workload + intent routing ───────────────────

    public function test_executive_agent_produces_summary_with_metric_figure(): void
    {
        // Give the org some completed tasks so the figure is non-trivial.
        $this->makeTask($this->pemda, $this->adminPemda, TaskStatus::COMPLETED->value);
        $this->makeTask($this->pemda, $this->adminPemda, TaskStatus::COMPLETED->value);

        $response = $this->actingAs($this->adminPemda)
            ->postJson('/executive/ai-brief');

        $response->assertStatus(200);
        $brief = $response->json('brief');

        $this->assertStringContainsString('Ringkasan Eksekutif', $brief);
        $this->assertStringContainsString('tugas selesai', $brief);
        // Contains the [AI Generated] provenance label from the orchestrator.
        $this->assertStringContainsString('[AI Generated]', $brief);
    }

    public function test_ai_brief_forbidden_without_opd_permission(): void
    {
        $this->actingAs($this->asn)
            ->postJson('/executive/ai-brief')
            ->assertStatus(403);
    }

    public function test_workload_agent_returns_workload_info(): void
    {
        $this->makeTask($this->pemda, $this->asn, TaskStatus::OPEN->value);

        $orchestrator = app(AiOrchestratorService::class);
        $message = $orchestrator->sendMessage($this->asn, null, 'Bagaimana beban kerja saya?');

        $this->assertStringContainsString('Beban Kerja', $message->content);
    }

    public function test_intent_classifier_routes_executive_and_workload(): void
    {
        $ic = app(IntentClassifier::class);

        $exec = $ic->classify('ringkasan eksekutif');
        $this->assertSame(AiIntent::EXECUTIVE_BRIEF, $exec['intent']);
        $this->assertSame(AiAgentType::EXECUTIVE, $exec['agent']);

        $work = $ic->classify('beban kerja saya');
        $this->assertSame(AiIntent::WORKLOAD_QUERY, $work['intent']);
        $this->assertSame(AiAgentType::WORKLOAD, $work['agent']);
    }
}
