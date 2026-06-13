<?php

namespace Tests\Feature\Task;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $creator;
    private User $assignee;
    private string $creatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Pemda',
            'code'      => 'TEST',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->creator = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011001',
            'name'            => 'Kepala Bidang',
            'email'           => 'kepala@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->creator->assignRole('kepala_bidang');

        $this->assignee = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199501012023011001',
            'name'            => 'Staf ASN',
            'email'           => 'staf@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->assignee->assignRole('asn');

        $loginRes = $this->postJson('/api/v1/auth/login', ['login' => 'kepala@test.id', 'password' => 'password']);
        $this->creatorToken = $loginRes->json('token');
    }

    private function makeTask(array $override = []): Task
    {
        return Task::create(array_merge([
            'id'              => (string) Str::ulid(),
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'creator_id'      => $this->creator->id,
            'created_by'      => $this->creator->id,
            'task_type'       => 'personal',
            'priority'        => 'medium',
            'status'          => 'open',
        ], $override));
    }

    public function test_can_create_task(): void
    {
        $response = $this->withToken($this->creatorToken)->postJson('/api/v1/tasks', [
            'title'       => 'Buat laporan bulanan',
            'description' => 'Laporan bulan Juni 2026',
            'task_type'   => 'personal',
            'priority'    => 'high',
            'assignee_id' => $this->assignee->id,
            'due_date'    => now()->addDays(7)->toISOString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'Buat laporan bulanan');
    }

    public function test_can_list_tasks(): void
    {
        $this->makeTask(['title' => 'Task Test']);

        $this->withToken($this->creatorToken)
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_transition_task_to_in_progress(): void
    {
        $task = $this->makeTask([
            'title'       => 'Task In Progress',
            'assignee_id' => $this->assignee->id,
            'status'      => 'assigned',
        ]);

        $this->withToken($this->creatorToken)
            ->postJson("/api/v1/tasks/{$task->id}/transition", [
                'status' => 'in_progress',
                'note'   => 'Mulai dikerjakan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_cannot_complete_task_without_evidence(): void
    {
        $task = $this->makeTask([
            'title'  => 'Task No Evidence',
            'status' => 'in_progress',
        ]);

        $this->withToken($this->creatorToken)
            ->postJson("/api/v1/tasks/{$task->id}/transition", [
                'status' => 'completed',
            ])
            ->assertStatus(422);
    }

    public function test_tenant_isolation_prevents_cross_org_access(): void
    {
        $otherOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Other Pemda',
            'code'      => 'OTHER',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $otherOrg->update(['pemda_id' => $otherOrg->id]);

        $otherUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011099',
            'name'            => 'Other User',
            'email'           => 'other@other.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $otherOrg->id,
            'pemda_id'        => $otherOrg->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $otherUser->assignRole('kepala_bidang');

        $otherLoginRes = $this->postJson('/api/v1/auth/login', ['login' => 'other@other.id', 'password' => 'password']);
        $otherToken = $otherLoginRes->json('token');

        $task = $this->makeTask(['title' => 'Secret Task']);

        // User from other org must NOT see this task (BelongsToOrganization scope)
        $this->withToken($otherToken)
            ->getJson("/api/v1/tasks/{$task->id}")
            ->assertStatus(404);
    }
}
