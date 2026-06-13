<?php

namespace Tests\Feature\Scheduler;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Models\AppNotification;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OverdueTaskTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $assignee;
    private User $creator;

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

        $this->assignee = $this->createUser('assignee@test.id', '199001012020011001');
        $this->creator  = $this->createUser('creator@test.id', '199001012020011002');
    }

    private function createUser(string $email, string $nip): User
    {
        $user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => $nip,
            'name'            => 'User ' . $email,
            'email'           => $email,
            'password'        => Hash::make('password'),
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

    private function createTask(array $overrides = []): Task
    {
        return Task::withoutGlobalScopes()->create(array_merge([
            'id'              => (string) Str::ulid(),
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'title'           => 'Test Task',
            'description'     => 'Test description',
            'task_type'       => 'personal',
            'status'          => TaskStatus::IN_PROGRESS->value,
            'priority'        => 'medium',
            'creator_id'      => $this->creator->id,
            'assignee_id'     => $this->assignee->id,
            'due_date'        => now()->subDays(2)->toDateString(),
            'is_recurring'    => false,
            'data_classification' => 2,
            'created_by'      => $this->creator->id,
            'version'         => 1,
        ], $overrides));
    }

    // 6a. Overdue task generates a notification for the assignee
    public function test_detect_overdue_dispatches_notification_for_overdue_task(): void
    {
        $task = $this->createTask();

        $this->artisan('tasks:detect-overdue')
            ->assertExitCode(0);

        $this->assertDatabaseHas('app_notifications', [
            'recipient_id'      => $this->assignee->id,
            'notification_type' => NotificationType::TASK_OVERDUE->value,
        ]);
    }

    // 6b. Completed task does NOT generate notification
    public function test_detect_overdue_skips_completed_task(): void
    {
        $this->createTask(['status' => TaskStatus::COMPLETED->value]);

        $this->artisan('tasks:detect-overdue')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('app_notifications', [
            'recipient_id'      => $this->assignee->id,
            'notification_type' => NotificationType::TASK_OVERDUE->value,
        ]);
    }

    // 6c. Future task (due_date in future) does NOT generate notification
    public function test_detect_overdue_skips_future_task(): void
    {
        $this->createTask(['due_date' => now()->addDays(3)->toDateString()]);

        $this->artisan('tasks:detect-overdue')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('app_notifications', [
            'recipient_id'      => $this->assignee->id,
            'notification_type' => NotificationType::TASK_OVERDUE->value,
        ]);
    }

    // 6d. Running twice same day does NOT duplicate notifications
    public function test_detect_overdue_does_not_duplicate_same_day(): void
    {
        $this->createTask();

        $this->artisan('tasks:detect-overdue')->assertExitCode(0);
        $this->artisan('tasks:detect-overdue')->assertExitCode(0);

        $count = AppNotification::withoutGlobalScopes()
            ->where('recipient_id', $this->assignee->id)
            ->where('notification_type', NotificationType::TASK_OVERDUE->value)
            ->count();

        $this->assertSame(1, $count);
    }
}
