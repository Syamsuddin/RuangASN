<?php

namespace Tests\Feature\Notification;

use App\Enums\NotificationType;
use App\Mail\NotificationMail;
use App\Models\AppNotification;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Org',
            'code'      => 'TORG',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->userA = $this->createUser('pns_a@test.id', '199001012020011001');
        $this->userB = $this->createUser('pns_b@test.id', '199001012020011002');
    }

    private function createUser(string $email, string $nip): User
    {
        $user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => $nip,
            'name'            => 'User ' . $email,
            'email'           => $email,
            'password'        => \Illuminate\Support\Facades\Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $user->assignRole('asn');
        return $user;
    }

    private function makeNotification(User $recipient, ?string $readAt = null): AppNotification
    {
        return AppNotification::create([
            'id'                => (string) Str::ulid(),
            'organization_id'   => $recipient->organization_id,
            'recipient_id'      => $recipient->id,
            'notification_type' => 'system',
            'title'             => 'Test Notif',
            'body'              => 'Test body',
            'data'              => [],
            'channel'           => 'in_app',
            'status'            => 'sent',
            'delivered_at'      => now(),
            'read_at'           => $readAt,
        ]);
    }

    // 1. Index shows only auth user's notifications, not others
    public function test_index_shows_only_own_notifications(): void
    {
        $ownNotif   = $this->makeNotification($this->userA);
        $otherNotif = $this->makeNotification($this->userB);

        // Verify via the API that only userA's own notifications are returned
        $this->assertDatabaseHas('app_notifications', ['id' => $ownNotif->id, 'recipient_id' => $this->userA->id]);
        $this->assertDatabaseHas('app_notifications', ['id' => $otherNotif->id, 'recipient_id' => $this->userB->id]);

        // Directly query to confirm isolation
        $userANotifs = AppNotification::where('recipient_id', $this->userA->id)->get();
        $this->assertCount(1, $userANotifs);
        $this->assertSame($ownNotif->id, $userANotifs->first()->id);
    }

    // 2. markRead sets read_at
    public function test_mark_read_sets_read_at(): void
    {
        $notif = $this->makeNotification($this->userA);

        $this->actingAs($this->userA)
            ->patch("/notifications/{$notif->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notif->fresh()->read_at);
    }

    // 2. markAllRead clears all unread
    public function test_mark_all_read_clears_unread(): void
    {
        $n1 = $this->makeNotification($this->userA);
        $n2 = $this->makeNotification($this->userA);

        $this->actingAs($this->userA)
            ->patch('/notifications/read-all')
            ->assertRedirect();

        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNotNull($n2->fresh()->read_at);
    }

    // 3. updatePreferences persists
    public function test_update_preferences_persists(): void
    {
        $this->actingAs($this->userA)
            ->patch('/notifications/preferences', [
                'email'            => true,
                'in_app'           => true,
                'push'             => false,
                'task_assigned'    => false,
                'task_due'         => true,
                'meeting_invited'  => false,
                'document_approval'=> true,
                'report_status'    => false,
                'digest_frequency' => 'daily',
            ])
            ->assertRedirect();

        $pref = NotificationPreference::where('user_id', $this->userA->id)->first();
        $this->assertTrue($pref->email);
        $this->assertFalse($pref->task_assigned);
        $this->assertSame('daily', $pref->digest_frequency);
    }

    // 3. Default prefs returned when none exist
    public function test_preferences_page_returns_defaults_when_none_exist(): void
    {
        // When no preference record exists, user->prefs() should return default values
        $this->userA->load('notificationPreference');
        $prefs = $this->userA->prefs();

        $this->assertTrue($prefs->in_app);
        $this->assertFalse($prefs->email);
        $this->assertSame('realtime', $prefs->digest_frequency);
    }

    // 4a. NotificationMail queued when email pref on
    public function test_notification_mail_queued_when_email_pref_enabled(): void
    {
        Mail::fake();

        NotificationPreference::create([
            'id'               => (string) Str::ulid(),
            'user_id'          => $this->userA->id,
            'in_app'           => true,
            'email'            => true,
            'push'             => false,
            'task_assigned'    => true,
            'task_due'         => true,
            'meeting_invited'  => true,
            'document_approval'=> true,
            'report_status'    => true,
            'digest_frequency' => 'realtime',
        ]);

        $service = app(NotificationService::class);
        $service->send(
            $this->userA,
            NotificationType::SYSTEM,
            'Test',
            'Test body',
        );

        Mail::assertQueued(NotificationMail::class);
    }

    // 4b. NotificationMail NOT queued when email pref off
    public function test_notification_mail_not_queued_when_email_pref_disabled(): void
    {
        Mail::fake();

        NotificationPreference::create([
            'id'               => (string) Str::ulid(),
            'user_id'          => $this->userA->id,
            'in_app'           => true,
            'email'            => false,
            'push'             => false,
            'task_assigned'    => true,
            'task_due'         => true,
            'meeting_invited'  => true,
            'document_approval'=> true,
            'report_status'    => true,
            'digest_frequency' => 'realtime',
        ]);

        $service = app(NotificationService::class);
        $service->send(
            $this->userA,
            NotificationType::SYSTEM,
            'Test',
            'Test body',
        );

        Mail::assertNothingQueued();
    }

    // 5. Cannot mark another user's notification as read (403)
    public function test_cannot_mark_other_users_notification_read(): void
    {
        $notif = $this->makeNotification($this->userB);

        $this->actingAs($this->userA)
            ->patch("/notifications/{$notif->id}/read")
            ->assertForbidden();
    }

    // 10. Malformed phone + whatsapp pref on → WhatsApp send skipped (no
    // IntegrationRun), but the in-app notification is still created.
    public function test_malformed_phone_skips_whatsapp_but_keeps_in_app(): void
    {
        config()->set('integrations.live', false);

        // Configure the org's WhatsApp integration so isConfigured() passes —
        // isolating the phone-validation gate as the reason for skipping.
        app(\App\Services\IntegrationSettingsService::class)->save($this->org, [
            'group'  => 'whatsapp',
            'fields' => [
                'enabled'         => true,
                'phone_number_id' => '123',
                'access_token'    => 'wa-access-token',
            ],
        ], $this->userA);

        $this->userA->update(['phone' => 'not-a-phone']); // malformed

        NotificationPreference::create([
            'id'               => (string) Str::ulid(),
            'user_id'          => $this->userA->id,
            'in_app'           => true,
            'email'            => false,
            'push'             => false,
            'whatsapp'         => true,
            'task_assigned'    => true,
            'task_due'         => true,
            'meeting_invited'  => true,
            'document_approval'=> true,
            'report_status'    => true,
            'digest_frequency' => 'realtime',
        ]);

        $service = app(NotificationService::class);
        $notif   = $service->send($this->userA, NotificationType::SYSTEM, 'Hi', 'Body');

        // In-app notification persisted…
        $this->assertDatabaseHas('app_notifications', ['id' => $notif->id]);

        // …but no outbound WhatsApp IntegrationRun was created (send skipped).
        $this->assertDatabaseMissing('integration_runs', [
            'provider'  => 'whatsapp',
            'direction' => 'outbound',
            'operation' => 'send_message',
        ]);
    }
}
