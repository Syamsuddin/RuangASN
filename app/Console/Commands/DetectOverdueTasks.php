<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Models\AppNotification;
use App\Models\Task;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class DetectOverdueTasks extends Command
{
    protected $signature = 'tasks:detect-overdue';
    protected $description = 'Detect overdue tasks and notify assignees. Runs once per task per day.';

    private array $terminalStatuses = [
        TaskStatus::COMPLETED->value,
        TaskStatus::CANCELLED->value,
        TaskStatus::CLOSED->value,
        TaskStatus::ARCHIVED->value,
    ];

    public function handle(NotificationService $notificationService): int
    {
        $today = now()->startOfDay();
        $count = 0;

        Task::withoutGlobalScopes()
            ->whereDate('due_date', '<', $today)
            ->whereNotIn('status', $this->terminalStatuses)
            ->whereNotNull('assignee_id')
            ->with('assignee:id,name,email,organization_id', 'creator:id,name,email,organization_id')
            ->chunk(100, function ($tasks) use ($notificationService, &$count) {
                foreach ($tasks as $task) {
                    if ($this->alreadyNotifiedToday($task->id, $task->assignee_id)) {
                        continue;
                    }

                    /** @var \App\Models\User|null $assignee */
                    $assignee = $task->assignee;
                    if ($assignee instanceof \App\Models\User) {
                        $notificationService->notifyTaskOverdue($assignee, $task);
                        $count++;
                    }

                    // Notify creator if different from assignee
                    if ($task->creator_id && $task->creator_id !== $task->assignee_id) {
                        if (! $this->alreadyNotifiedToday($task->id, $task->creator_id)) {
                            /** @var \App\Models\User|null $creator */
                            $creator = $task->creator;
                            if ($creator instanceof \App\Models\User) {
                                $notificationService->notifyTaskOverdue($creator, $task);
                            }
                        }
                    }
                }
            });

        $this->info("Processed {$count} overdue task notification(s).");
        return self::SUCCESS;
    }

    private function alreadyNotifiedToday(string $taskId, string $userId): bool
    {
        return AppNotification::withoutGlobalScopes()
            ->where('recipient_id', $userId)
            ->where('notification_type', NotificationType::TASK_OVERDUE->value)
            ->whereDate('created_at', today())
            ->whereJsonContains('data->task_id', $taskId)
            ->exists();
    }
}
