<?php

namespace Tests\Feature\Meeting;

use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingAttendanceTest extends TestCase
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
            'name'      => 'Test Pemda Att',
            'code'      => 'TATT',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->host = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011003',
            'name'            => 'Host Attendance',
            'email'           => 'host.att@test.id',
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
            'nip'             => '199501012023011002',
            'name'            => 'Participant Att',
            'email'           => 'participant.att@test.id',
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

    private function createScheduledMeeting(): Meeting
    {
        $this->actingAs($this->host)->post('/meetings', [
            'title'            => 'Rapat Absensi QR',
            'meeting_type'     => 'internal',
            'meeting_mode'     => 'offline',
            'scheduled_at'     => now()->addHours(1)->toISOString(),
            'duration_minutes' => 60,
            'location'         => 'Ruang A',
            'participant_ids'  => [$this->participant->id],
        ]);

        $meeting = Meeting::where('host_id', $this->host->id)->latest()->first();

        // Transition to scheduled so QR is available
        $this->actingAs($this->host)
            ->post("/meetings/{$meeting->id}/transition", ['status' => 'scheduled']);

        return $meeting->fresh();
    }

    /** 1. Valid signed check-in URL records attendance as PRESENT for a participant. */
    public function test_checkin_via_signed_url_records_present(): void
    {
        $meeting = $this->createScheduledMeeting();

        $signedUrl = URL::temporarySignedRoute(
            'meetings.checkin',
            now()->addHours(4),
            ['meeting' => $meeting->id]
        );

        $response = $this->withoutVite()->actingAs($this->participant)->get($signedUrl);

        $response->assertStatus(200);

        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id'        => $meeting->id,
            'user_id'           => $this->participant->id,
            'attendance_status' => AttendanceStatus::PRESENT->value,
        ]);

        $p = MeetingParticipant::where('meeting_id', $meeting->id)
            ->where('user_id', $this->participant->id)
            ->first();
        $this->assertNotNull($p->check_in_at);
    }

    /** 2. A non-participant gets 403 when trying to check in. */
    public function test_non_participant_checkin_returns_403(): void
    {
        $meeting = $this->createScheduledMeeting();

        $outsider = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199701012023011099',
            'name'            => 'Outsider',
            'email'           => 'outsider@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $outsider->assignRole('asn');

        $signedUrl = URL::temporarySignedRoute(
            'meetings.checkin',
            now()->addHours(4),
            ['meeting' => $meeting->id]
        );

        $response = $this->actingAs($outsider)->get($signedUrl);
        $response->assertStatus(403);
    }

    /** 3. An unsigned URL is rejected (403 invalid signature). */
    public function test_unsigned_checkin_url_is_rejected(): void
    {
        $meeting = $this->createScheduledMeeting();

        // Plain URL without signature
        $response = $this->actingAs($this->participant)
            ->get("/meetings/{$meeting->id}/checkin");

        $response->assertStatus(403);
    }

    /** 4. Checking in after grace period records LATE. */
    public function test_checkin_after_grace_records_late(): void
    {
        // Create directly in DB with a past scheduled_at (20 min ago, past 15-min grace)
        $meeting = Meeting::create([
            'organization_id'  => $this->org->id,
            'pemda_id'         => $this->org->id,
            'title'            => 'Rapat Terlambat',
            'meeting_type'     => 'internal',
            'meeting_mode'     => 'offline',
            'status'           => MeetingStatus::SCHEDULED->value,
            'scheduled_at'     => now()->subMinutes(20),
            'duration_minutes' => 60,
            'location'         => 'Ruang B',
            'host_id'          => $this->host->id,
            'created_by'       => $this->host->id,
        ]);

        // Add participant
        MeetingParticipant::create([
            'meeting_id'        => $meeting->id,
            'user_id'           => $this->participant->id,
            'role'              => 'participant',
            'attendance_status' => AttendanceStatus::INVITED->value,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'meetings.checkin',
            now()->addHours(4),
            ['meeting' => $meeting->id]
        );

        $response = $this->withoutVite()->actingAs($this->participant)->get($signedUrl);
        $response->assertStatus(200);

        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id'        => $meeting->id,
            'user_id'           => $this->participant->id,
            'attendance_status' => AttendanceStatus::LATE->value,
        ]);
    }

    /** 5. checkInQr is denied for a non-host participant (403). */
    public function test_checkin_qr_denied_for_non_host(): void
    {
        $meeting = $this->createScheduledMeeting();

        $response = $this->actingAs($this->participant)
            ->get("/meetings/{$meeting->id}/checkin-qr");

        $response->assertStatus(403);
    }

    /** 6. Host can access checkInQr page. */
    public function test_host_can_access_checkin_qr_page(): void
    {
        $meeting = $this->createScheduledMeeting();

        $response = $this->withoutVite()->actingAs($this->host)
            ->get("/meetings/{$meeting->id}/checkin-qr");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Meetings/CheckInQr')
                 ->has('qrSvg')
                 ->has('signedUrl')
        );
    }
}
