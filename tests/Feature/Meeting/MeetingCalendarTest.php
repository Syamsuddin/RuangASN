<?php

namespace Tests\Feature\Meeting;

use App\Enums\CalendarType;
use App\Enums\MeetingStatus;
use App\Models\CalendarEvent;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use App\Services\CalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Pemda',
            'code'      => 'TCAL',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->host = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011002',
            'name'            => 'Kepala Bidang Cal',
            'email'           => 'kepala.cal@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->host->assignRole('kepala_bidang');
    }

    private function createMeeting(): Meeting
    {
        $this->actingAs($this->host)->post('/meetings', [
            'title'            => 'Rapat Kalender',
            'meeting_type'     => 'internal',
            'meeting_mode'     => 'offline',
            'scheduled_at'     => now()->addDays(2)->toISOString(),
            'duration_minutes' => 90,
            'location'         => 'Aula Utama',
        ]);

        return Meeting::where('host_id', $this->host->id)->latest()->first();
    }

    /** 1. Transition to scheduled auto-creates a linked CalendarEvent. */
    public function test_scheduling_meeting_creates_calendar_event(): void
    {
        $meeting = $this->createMeeting();

        $this->assertDatabaseMissing('calendar_events', ['meeting_id' => $meeting->id]);

        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        $this->assertDatabaseHas('calendar_events', [
            'meeting_id'    => $meeting->id,
            'calendar_type' => CalendarType::MEETING->value,
        ]);

        $event = CalendarEvent::where('meeting_id', $meeting->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals($meeting->fresh()->scheduled_at->toDateTimeString(), $event->start_at->toDateTimeString());

        $expectedEnd = $meeting->fresh()->scheduled_at->copy()->addMinutes(90);
        $this->assertEquals($expectedEnd->toDateTimeString(), $event->end_at->toDateTimeString());
    }

    /** 2. Idempotent: scheduling again does not create a second CalendarEvent. */
    public function test_scheduling_twice_does_not_duplicate_calendar_event(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        // Should not throw, second call to ensureCalendarEvent is idempotent
        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'postponed']);

        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        $this->assertEquals(1, CalendarEvent::where('meeting_id', $meeting->id)->count());
    }

    /** 3. Cancelling a meeting soft-deletes the linked CalendarEvent. */
    public function test_cancelling_meeting_soft_deletes_calendar_event(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        $this->assertDatabaseHas('calendar_events', ['meeting_id' => $meeting->id]);

        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'cancelled']);

        // Row exists but soft-deleted
        $this->assertSoftDeleted('calendar_events', ['meeting_id' => $meeting->id]);
    }

    /** 4. Rescheduling (update) syncs start_at on the linked event. */
    public function test_rescheduling_meeting_updates_calendar_event_start_at(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        $newTime = now()->addDays(5)->toISOString();
        $this->actingAs($this->host)
            ->patch("/meetings/{$meeting->id}", ['scheduled_at' => $newTime]);

        $event = CalendarEvent::where('meeting_id', $meeting->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals(
            Carbon::parse($newTime)->toDateTimeString(),
            $event->fresh()->start_at->toDateTimeString()
        );
    }

    /** 5. feedForUser does NOT duplicate a meeting with a real CalendarEvent. */
    public function test_feed_dedupes_meeting_with_real_calendar_event(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        $this->assertDatabaseHas('calendar_events', ['meeting_id' => $meeting->id]);

        /** @var CalendarService $service */
        $service = app(CalendarService::class);

        $start = now()->startOfMonth();
        $end   = now()->endOfMonth()->addDays(10);

        $feed = $service->feedForUser($this->host, $start, $end);

        // Count items with this meeting's id (could appear as event or virtual meeting)
        $matchingByMeetingId = array_filter($feed, fn ($item) =>
            ($item['meeting_id'] ?? null) === $meeting->id
        );
        $matchingById = array_filter($feed, fn ($item) =>
            $item['id'] === $meeting->id && $item['source'] === 'meeting'
        );

        // The meeting should appear exactly once (as a real event, not also as a virtual meeting)
        $total = count($matchingByMeetingId) + count($matchingById);
        $this->assertEquals(1, $total, 'Meeting appears more than once in feed after real CalendarEvent created');
    }
}
