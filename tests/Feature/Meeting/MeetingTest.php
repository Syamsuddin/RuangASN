<?php

namespace Tests\Feature\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $host;
    private User $participant;

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

        $this->host = User::create([
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
        $this->host->assignRole('kepala_bidang');

        $this->participant = User::create([
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
        $this->participant->assignRole('asn');
    }

    private function meetingPayload(array $override = []): array
    {
        return array_merge([
            'title'            => 'Rapat Koordinasi Tim',
            'description'      => 'Pembahasan rencana kerja',
            'meeting_type'     => 'internal',
            'meeting_mode'     => 'offline',
            'scheduled_at'     => now()->addDays(2)->toISOString(),
            'duration_minutes' => 60,
            'location'         => 'Ruang Rapat Lantai 3',
        ], $override);
    }

    // 1. Happy path: host creates meeting
    public function test_host_can_create_meeting(): void
    {
        $response = $this->actingAs($this->host)
            ->post('/meetings', $this->meetingPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'title'   => 'Rapat Koordinasi Tim',
            'host_id' => $this->host->id,
            'status'  => MeetingStatus::DRAFT->value,
        ]);

        // Host is auto-added as participant
        $meeting = Meeting::where('host_id', $this->host->id)->first();
        $this->assertNotNull($meeting);
        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id'        => $meeting->id,
            'user_id'           => $this->host->id,
            'role'              => 'host',
            'attendance_status' => 'accepted',
        ]);
    }

    // 2. RBAC: asn without meeting.create cannot create (403)
    public function test_asn_without_create_permission_cannot_create_meeting(): void
    {
        // asn role does NOT have meeting.create permission
        $response = $this->actingAs($this->participant)
            ->post('/meetings', $this->meetingPayload());

        $response->assertStatus(403);
    }

    // 3. Tenant isolation: org B user cannot view org A meeting
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
            'name'            => 'Other Kepala',
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

        // Create meeting for org A
        $this->actingAs($this->host)->post('/meetings', $this->meetingPayload());
        $meeting = Meeting::where('host_id', $this->host->id)->first();

        // User from org B gets 404 — BelongsToOrganization global scope hides the record
        // (same pattern as TaskCrudTest tenant isolation)
        $response = $this->actingAs($otherUser)->get("/meetings/{$meeting->id}");
        $response->assertStatus(404);
    }

    // 4a. Valid transition draft -> scheduled works
    public function test_valid_status_transition_draft_to_scheduled(): void
    {
        $this->actingAs($this->host)->post('/meetings', $this->meetingPayload());
        $meeting = Meeting::where('host_id', $this->host->id)->first();

        $response = $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        $response->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'id'     => $meeting->id,
            'status' => MeetingStatus::SCHEDULED->value,
        ]);
    }

    // 4b. Invalid transition draft -> completed is rejected (redirect with session errors)
    public function test_invalid_status_transition_draft_to_completed_fails(): void
    {
        $this->actingAs($this->host)->post('/meetings', $this->meetingPayload());
        $meeting = Meeting::where('host_id', $this->host->id)->first();

        $response = $this->actingAs($this->host)
            ->from("/meetings/{$meeting->id}")
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'completed']);

        // Web Inertia routes redirect back with session errors on ValidationException
        $response->assertSessionHasErrors('status');

        // Meeting status must NOT have changed
        $this->assertDatabaseHas('meetings', [
            'id'     => $meeting->id,
            'status' => MeetingStatus::DRAFT->value,
        ]);
    }

    // 5. Action item auto-creates a linked task when create_task=true
    public function test_action_item_auto_creates_linked_task(): void
    {
        $this->actingAs($this->host)->post('/meetings', $this->meetingPayload());
        $meeting = Meeting::where('host_id', $this->host->id)->first();

        $response = $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/action-items", [
                'title'       => 'Tindak lanjut laporan bulanan',
                'description' => 'Buat laporan untuk diserahkan bulan depan',
                'assignee_id' => $this->participant->id,
                'due_date'    => now()->addDays(7)->toDateString(),
                'create_task' => true,
            ]);

        $response->assertRedirect();

        // Assert tasks table has the row
        $this->assertDatabaseHas('tasks', [
            'title'           => 'Tindak lanjut laporan bulanan',
            'organization_id' => $this->org->id,
        ]);

        // Assert action_item has task_id set + is_task_created = true
        $this->assertDatabaseHas('meeting_action_items', [
            'meeting_id'      => $meeting->id,
            'title'           => 'Tindak lanjut laporan bulanan',
            'is_task_created' => true,
        ]);

        $actionItem = $meeting->actionItems()->where('title', 'Tindak lanjut laporan bulanan')->first();
        $this->assertNotNull($actionItem->task_id);
    }

    // 6a. Secretary/host can upsert minutes
    public function test_host_can_upsert_minutes(): void
    {
        $this->actingAs($this->host)->post('/meetings', $this->meetingPayload());
        $meeting = Meeting::where('host_id', $this->host->id)->first();

        $response = $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/minutes", [
                'content' => 'Rapat dipimpin oleh Kepala Bidang. Hadir 10 orang.',
                'status'  => 'draft',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_minutes', [
            'meeting_id' => $meeting->id,
            'status'     => 'draft',
        ]);
    }

    // 6b. Approve sets approved_at
    public function test_approve_minutes_sets_approved_at(): void
    {
        $this->actingAs($this->host)->post('/meetings', $this->meetingPayload());
        $meeting = Meeting::where('host_id', $this->host->id)->first();

        // Upsert minutes first
        $this->actingAs($this->host)->post("/meetings/{$meeting->id}/minutes", [
            'content' => 'Notulensi rapat.',
            'status'  => 'draft',
        ]);

        $minutes = MeetingMinute::where('meeting_id', $meeting->id)->first();
        $this->assertNotNull($minutes);

        // Use a user with meeting.minutes.approve permission (kepala_bidang role does have it in RbacSeeder)
        $approverUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '198001012010011001',
            'name'            => 'Kepala OPD',
            'email'           => 'kepalaard@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $approverUser->assignRole('admin_pemda');

        $response = $this->actingAs($approverUser)
            ->post("/meetings/minutes/{$minutes->id}/approve");

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_minutes', [
            'id'     => $minutes->id,
            'status' => 'approved',
        ]);
        $minutes->refresh();
        $this->assertNotNull($minutes->approved_at);
    }
}
