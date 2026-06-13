<?php

namespace Tests\Feature\Search;

use App\Enums\DataClassification;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\KnowledgeStatus;
use App\Enums\KnowledgeType;
use App\Enums\MeetingMode;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Document;
use App\Models\KnowledgeArticle;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private User $asnUser;
    private User $adminUser;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Dinas Utama',
            'code'      => 'MAIN',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->otherOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Dinas Lain',
            'code'      => 'OTHER',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->otherOrg->update(['pemda_id' => $this->otherOrg->id]);

        $this->asnUser = $this->makeUser('asn@search.id', $this->org, 'asn');
        $this->adminUser = $this->makeUser('admin@search.id', $this->org, 'admin_pemda');
        $this->otherUser = $this->makeUser('other@search.id', $this->otherOrg, 'asn');
    }

    private function makeUser(string $email, Organization $org, string $role): User
    {
        $user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => substr(md5($email), 0, 18),
            'name'            => 'User ' . $email,
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

    private function makeTask(User $user, string $title, string $description = ''): Task
    {
        return Task::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $user->organization_id,
            'pemda_id'            => $user->pemda_id,
            'title'               => $title,
            'description'         => $description,
            'task_type'           => TaskType::PERSONAL->value,
            'status'              => TaskStatus::OPEN->value,
            'priority'            => TaskPriority::MEDIUM->value,
            'data_classification' => DataClassification::INTERNAL->value,
            'creator_id'          => $user->id,
            'created_by'          => $user->id,
            'version'             => 1,
        ]);
    }

    private function makeMeeting(User $user, string $title): Meeting
    {
        return Meeting::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $user->organization_id,
            'pemda_id'            => $user->pemda_id,
            'title'               => $title,
            'meeting_type'        => MeetingType::INTERNAL->value,
            'meeting_mode'        => MeetingMode::OFFLINE->value,
            'status'              => MeetingStatus::SCHEDULED->value,
            'scheduled_at'        => now()->addDay(),
            'host_id'             => $user->id,
            'data_classification' => DataClassification::INTERNAL->value,
            'created_by'          => $user->id,
            'version'             => 1,
        ]);
    }

    private function makeDocument(User $user, string $title, int $classification = 2): Document
    {
        return Document::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $user->organization_id,
            'pemda_id'            => $user->pemda_id,
            'title'               => $title,
            'document_type'       => DocumentType::MEMO->value,
            'status'              => DocumentStatus::DRAFT->value,
            'data_classification' => $classification,
            'owner_id'            => $user->id,
            'created_by'          => $user->id,
            'version'             => 1,
            'version_number'      => 1,
            'is_latest'           => true,
        ]);
    }

    private function makeKnowledge(User $user, string $title, string $status = 'published'): KnowledgeArticle
    {
        return KnowledgeArticle::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $user->organization_id,
            'pemda_id'            => $user->pemda_id,
            'title'               => $title,
            'knowledge_type'      => KnowledgeType::WIKI->value,
            'status'              => $status,
            'data_classification' => DataClassification::INTERNAL->value,
            'author_id'           => $user->id,
            'created_by'          => $user->id,
            'version_number'      => 1,
            'is_latest'           => true,
        ]);
    }

    private function makeReport(User $user, string $title): Report
    {
        return Report::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $user->organization_id,
            'pemda_id'            => $user->pemda_id,
            'title'               => $title,
            'report_type'         => ReportType::MONTHLY->value,
            'period_type'         => ReportPeriodType::MONTHLY->value,
            'status'              => ReportStatus::DRAFT->value,
            'data_classification' => DataClassification::INTERNAL->value,
            'author_id'           => $user->id,
            'created_by'          => $user->id,
            'period_start_date'   => now()->startOfMonth()->toDateString(),
            'period_end_date'     => now()->endOfMonth()->toDateString(),
            'version'             => 1,
        ]);
    }

    // 1. Search finds items across multiple entity types by title keyword
    public function test_search_finds_task_meeting_document_knowledge_by_keyword(): void
    {
        $this->makeTask($this->asnUser, 'Rapat Koordinasi Bulanan');
        $this->makeMeeting($this->asnUser, 'Rapat Koordinasi Tim');
        $this->makeDocument($this->asnUser, 'Notulen Rapat Koordinasi');
        $this->makeKnowledge($this->asnUser, 'Panduan Rapat Koordinasi');

        $response = $this->actingAs($this->asnUser)
            ->getJson('/search/quick?q=Koordinasi');

        $response->assertOk();
        $data = $response->json('results');

        $types = collect($data)->pluck('type')->unique()->values()->toArray();
        sort($types);
        $this->assertContains('task', $types);
        $this->assertContains('meeting', $types);
        $this->assertContains('document', $types);
        $this->assertContains('knowledge', $types);
    }

    // 2. Permission filter: CONFIDENTIAL (3) document hidden from asn, visible for admin_pemda
    public function test_confidential_document_hidden_from_asn_but_visible_for_admin(): void
    {
        // asn does not have document.view.confidential
        $confDoc = $this->makeDocument($this->adminUser, 'Laporan Rahasia Anggaran', DataClassification::CONFIDENTIAL->value);

        // asn search
        $asnResponse = $this->withoutVite()->actingAs($this->asnUser)
            ->get('/search?q=Rahasia');
        $asnResponse->assertOk();
        $asnResults = collect($asnResponse->inertiaProps('results.document') ?? []);
        $this->assertEmpty(
            $asnResults->where('id', $confDoc->id)->all(),
            'ASN should not see confidential document in search results'
        );

        // admin search
        $adminResponse = $this->withoutVite()->actingAs($this->adminUser)
            ->get('/search?q=Rahasia');
        $adminResponse->assertOk();
        $adminResults = collect($adminResponse->inertiaProps('results.document') ?? []);
        $this->assertNotEmpty(
            $adminResults->where('id', $confDoc->id)->all(),
            'Admin should see confidential document in search results'
        );
    }

    // 3. Tenant isolation: results from another org never appear
    public function test_tenant_isolation_search_results(): void
    {
        $this->makeTask($this->otherUser, 'Tugas Rahasia Dinas Lain');

        $response = $this->withoutVite()->actingAs($this->asnUser)
            ->get('/search?q=Rahasia+Dinas+Lain');
        $response->assertOk();

        // More direct: no task from other org should appear
        $taskResults = collect($response->inertiaProps('results.task') ?? []);
        $this->assertEmpty($taskResults->all(), 'No tasks from other org should appear');
    }

    // 4. Types filter narrows results to the requested type
    public function test_types_filter_narrows_results(): void
    {
        $this->makeTask($this->asnUser, 'Laporan Akhir Tahun Tugas');
        $this->makeReport($this->asnUser, 'Laporan Akhir Tahun Report');

        $response = $this->withoutVite()->actingAs($this->asnUser)
            ->get('/search?q=Laporan+Akhir+Tahun&types[]=task');
        $response->assertOk();

        $this->assertNotEmpty($response->inertiaProps('results.task') ?? [], 'task results expected');
        $this->assertEmpty($response->inertiaProps('results.report') ?? [], 'report results should be empty when filtered to task');
        $this->assertEmpty($response->inertiaProps('results.meeting') ?? [], 'meeting results should be empty when filtered to task');
    }

    // 5. Quick endpoint returns JSON suggestions
    public function test_quick_endpoint_returns_json(): void
    {
        $this->makeTask($this->asnUser, 'Evaluasi Kinerja ASN 2025');

        $response = $this->actingAs($this->asnUser)
            ->getJson('/search/quick?q=Evaluasi');

        $response->assertOk()
                 ->assertJsonStructure(['results' => [['type', 'id', 'title', 'snippet', 'meta', 'url']]]);
    }

    // 6. RBAC: user without search.search gets 403
    public function test_user_without_search_permission_gets_403(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create a bare role with no permissions and assign to a new user
        $bareRole = Role::firstOrCreate(['name' => 'no_search_role', 'guard_name' => 'web']);
        $bareRole->syncPermissions(['dashboard.view.own']);

        $noSearchUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199912312020011999',
            'name'            => 'No Search User',
            'email'           => 'nosearch@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $noSearchUser->assignRole('no_search_role');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertFalse($noSearchUser->can('search.search'), 'User should not have search.search');

        $response = $this->withoutVite()->actingAs($noSearchUser)
            ->get('/search?q=test');
        $response->assertStatus(403);

        $quickResponse = $this->actingAs($noSearchUser)
            ->getJson('/search/quick?q=test');
        $quickResponse->assertStatus(403);
    }

    // 7. Empty query returns empty results (not an error)
    public function test_empty_query_returns_empty_results(): void
    {
        $response = $this->withoutVite()->actingAs($this->asnUser)
            ->get('/search?q=');
        $response->assertOk();

        $results = $response->inertiaProps('results') ?? [];
        $this->assertEmpty($results);
    }

    // 8. Quick endpoint with empty query returns empty array
    public function test_quick_empty_query_returns_empty(): void
    {
        $response = $this->actingAs($this->asnUser)
            ->getJson('/search/quick?q=');

        $response->assertOk()->assertJson(['results' => []]);
    }
}
