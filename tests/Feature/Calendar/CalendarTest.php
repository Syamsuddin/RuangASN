<?php

namespace Tests\Feature\Calendar;

use App\Enums\CalendarType;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\MeetingMode;
use App\Models\CalendarEvent;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $kepala;
    private User $asn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Pemda Kalender',
            'code'      => 'CALTEST',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->kepala = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011001',
            'name'            => 'Kepala Bidang Kalender',
            'email'           => 'kepala.cal@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->kepala->assignRole('kepala_bidang');

        $this->asn = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199501012023011001',
            'name'            => 'Staf ASN Kalender',
            'email'           => 'asn.cal@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->asn->assignRole('asn');
    }

    private function eventPayload(array $override = []): array
    {
        $start = now()->addDays(2)->format('Y-m-d\TH:i');
        $end   = now()->addDays(2)->addHours(2)->format('Y-m-d\TH:i');

        return array_merge([
            'title'         => 'Rapat Koordinasi Internal',
            'description'   => 'Pembahasan rencana kerja bulan ini',
            'calendar_type' => 'personal',
            'location'      => 'Ruang Rapat A',
            'start_at'      => $start,
            'end_at'        => $end,
            'all_day'       => false,
            'is_public'     => false,
        ], $override);
    }

    // 1. Happy path: user creates a personal event
    public function test_user_can_create_personal_event(): void
    {
        $response = $this->actingAs($this->kepala)
            ->post('/calendar', $this->eventPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'title'    => 'Rapat Koordinasi Internal',
            'owner_id' => $this->kepala->id,
        ]);

        $event = CalendarEvent::where('owner_id', $this->kepala->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals($this->kepala->organization_id, $event->organization_id);
        $this->assertEquals($this->kepala->id, $event->created_by);
    }

    // 2. RBAC: end_at before start_at fails validation (422)
    public function test_create_with_end_before_start_fails_validation(): void
    {
        $start = now()->addDays(2)->format('Y-m-d\TH:i');
        $end   = now()->addDays(1)->format('Y-m-d\TH:i'); // before start

        $response = $this->actingAs($this->kepala)
            ->post('/calendar', $this->eventPayload([
                'start_at' => $start,
                'end_at'   => $end,
            ]));

        $response->assertSessionHasErrors('end_at');
    }

    // 3. Tenant isolation: user cannot update event from another org
    public function test_tenant_isolation_prevents_cross_org_event_update(): void
    {
        $otherOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Other Org Kalender',
            'code'      => 'OTHER_CAL',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $otherOrg->update(['pemda_id' => $otherOrg->id]);

        $otherUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011099',
            'name'            => 'Other Kepala',
            'email'           => 'other.cal@other.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $otherOrg->id,
            'pemda_id'        => $otherOrg->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $otherUser->assignRole('kepala_bidang');

        // Create event for otherOrg user
        $event = CalendarEvent::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $otherOrg->id,
            'calendar_type'   => CalendarType::PERSONAL->value,
            'owner_id'        => $otherUser->id,
            'created_by'      => $otherUser->id,
            'title'           => 'Event Org Lain',
            'start_at'        => now()->addDay(),
            'end_at'          => now()->addDay()->addHour(),
        ]);

        // kepala from org A tries to update event from otherOrg.
        // BelongsToOrganization global scope hides the record → route model binding returns 404.
        $response = $this->actingAs($this->kepala)
            ->patch("/calendar/{$event->id}", ['title' => 'Coba Ubah']);

        $response->assertStatus(404);
    }

    // 4. Feed aggregation: meeting + task in range appear in feed
    public function test_feed_includes_meetings_and_tasks_in_range(): void
    {
        // Create a meeting where kepala is host, scheduled in current month
        $scheduledAt = Carbon::now()->startOfMonth()->addDays(5)->setHour(9);
        $meeting = Meeting::create([
            'id'               => (string) Str::ulid(),
            'organization_id'  => $this->org->id,
            'pemda_id'         => $this->org->id,
            'title'            => 'Rapat Test Feed',
            'meeting_type'     => MeetingType::INTERNAL->value,
            'meeting_mode'     => MeetingMode::OFFLINE->value,
            'status'           => MeetingStatus::SCHEDULED->value,
            'scheduled_at'     => $scheduledAt,
            'duration_minutes' => 60,
            'host_id'          => $this->kepala->id,
            'created_by'       => $this->kepala->id,
            'version'          => 1,
            'data_classification' => 2,
        ]);

        // Create task assigned to kepala with due_date in current month
        $dueDate = Carbon::now()->startOfMonth()->addDays(10)->toDateString();
        $task = Task::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'title'           => 'Task Test Feed',
            'task_type'       => 'personal',
            'status'          => 'open',
            'priority'        => 'medium',
            'creator_id'      => $this->kepala->id,
            'assignee_id'     => $this->kepala->id,
            'due_date'        => $dueDate,
            'created_by'      => $this->kepala->id,
            'version'         => 1,
            'data_classification' => 2,
        ]);

        // Test the JSON feed endpoint directly (avoids Vite manifest dependency)
        $start = Carbon::now()->startOfMonth()->toISOString();
        $end   = Carbon::now()->endOfMonth()->toISOString();
        $feedResponse = $this->actingAs($this->kepala)
            ->getJson("/calendar/feed?start={$start}&end={$end}");

        $feedResponse->assertStatus(200);
        $feedResponse->assertJsonStructure(['events']);

        $events = $feedResponse->json('events');
        $sources = array_column($events, 'source');

        $this->assertContains('meeting', $sources, 'Feed should contain meeting source');
        $this->assertContains('task', $sources, 'Feed should contain task source');
    }

    // 5a. Owner can update own event; non-owner asn gets 403
    public function test_owner_can_update_own_event(): void
    {
        $event = CalendarEvent::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $this->org->id,
            'calendar_type'   => CalendarType::PERSONAL->value,
            'owner_id'        => $this->kepala->id,
            'created_by'      => $this->kepala->id,
            'title'           => 'Acara Asli',
            'start_at'        => now()->addDays(3),
            'end_at'          => now()->addDays(3)->addHour(),
        ]);

        $response = $this->actingAs($this->kepala)
            ->patch("/calendar/{$event->id}", ['title' => 'Acara Diubah']);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'id'    => $event->id,
            'title' => 'Acara Diubah',
        ]);
    }

    public function test_non_owner_asn_cannot_update_someone_else_event(): void
    {
        $event = CalendarEvent::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $this->org->id,
            'calendar_type'   => CalendarType::PERSONAL->value,
            'owner_id'        => $this->kepala->id,
            'created_by'      => $this->kepala->id,
            'title'           => 'Acara Kepala',
            'start_at'        => now()->addDays(3),
            'end_at'          => now()->addDays(3)->addHour(),
        ]);

        // asn tries to update kepala's event
        $response = $this->actingAs($this->asn)
            ->patch("/calendar/{$event->id}", ['title' => 'Coba Ubah']);

        $response->assertStatus(403);
    }

    // 6. Delete soft-deletes the event
    public function test_owner_can_soft_delete_event(): void
    {
        $event = CalendarEvent::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $this->org->id,
            'calendar_type'   => CalendarType::PERSONAL->value,
            'owner_id'        => $this->kepala->id,
            'created_by'      => $this->kepala->id,
            'title'           => 'Acara Akan Dihapus',
            'start_at'        => now()->addDays(5),
            'end_at'          => now()->addDays(5)->addHour(),
        ]);

        $response = $this->actingAs($this->kepala)
            ->delete("/calendar/{$event->id}");

        $response->assertRedirect(route('calendar.index'));

        // Row must be soft-deleted (not hard-deleted)
        $this->assertSoftDeleted('calendar_events', ['id' => $event->id]);
    }
}
