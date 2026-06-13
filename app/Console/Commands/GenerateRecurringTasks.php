<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring';
    protected $description = 'Generate next occurrences for recurring tasks. Idempotent — will not double-generate.';

    public function handle(): int
    {
        $count = 0;

        Task::withoutGlobalScopes()
            ->where('is_recurring', true)
            ->whereNull('recurring_parent_id') // only template/parent tasks
            ->whereNotNull('recurring_pattern')
            ->chunk(50, function ($tasks) use (&$count) {
                foreach ($tasks as $task) {
                    if ($this->generateNext($task)) {
                        $count++;
                    }
                }
            });

        $this->info("Generated {$count} recurring task occurrence(s).");
        return self::SUCCESS;
    }

    private function generateNext(Task $template): bool
    {
        $pattern = $template->recurring_pattern;

        if (! is_array($pattern) || empty($pattern['frequency'])) {
            return false;
        }

        $nextDue = $this->computeNextDueDate($template, $pattern);

        if ($nextDue === null) {
            return false;
        }

        // Idempotency: skip if a child already exists for this period
        $alreadyExists = Task::withoutGlobalScopes()
            ->where('recurring_parent_id', $template->id)
            ->whereDate('due_date', $nextDue->toDateString())
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        DB::transaction(function () use ($template, $nextDue) {
            Task::withoutGlobalScopes()->create([
                'id'                  => (string) Str::ulid(),
                'organization_id'     => $template->organization_id,
                'pemda_id'            => $template->pemda_id,
                'title'               => $template->title,
                'description'         => $template->description,
                'task_type'           => $template->getRawOriginal('task_type'),
                'status'              => TaskStatus::ASSIGNED->value,
                'priority'            => $template->getRawOriginal('priority'),
                'creator_id'          => $template->creator_id,
                'assignee_id'         => $template->assignee_id,
                'due_date'            => $nextDue->toDateString(),
                'is_recurring'        => false,
                'recurring_parent_id' => $template->id,
                'data_classification' => $template->getRawOriginal('data_classification'),
                'created_by'          => $template->creator_id,
                'version'             => 1,
            ]);
        });

        return true;
    }

    /**
     * Compute the next due date based on the most recent child task or the template itself.
     * Returns null if the next occurrence is not yet due (future template still active).
     */
    private function computeNextDueDate(Task $template, array $pattern): ?Carbon
    {
        $frequency = $pattern['frequency']; // daily|weekly|monthly
        $interval  = (int) ($pattern['interval'] ?? 1);

        // Find the most recent child occurrence
        $lastChild = Task::withoutGlobalScopes()
            ->where('recurring_parent_id', $template->id)
            ->orderByDesc('due_date')
            ->first();

        $baseDue = $lastChild ? Carbon::parse($lastChild->due_date) : Carbon::parse($template->due_date);

        // Determine if we should generate: generate when base period has elapsed or last task completed
        $shouldGenerate = $lastChild
            ? ($lastChild->status === TaskStatus::COMPLETED || $baseDue->isPast())
            : $baseDue->isPast();

        if (! $shouldGenerate) {
            return null;
        }

        return match ($frequency) {
            'daily'   => $baseDue->copy()->addDays($interval),
            'weekly'  => $baseDue->copy()->addWeeks($interval),
            'monthly' => $baseDue->copy()->addMonths($interval),
            default   => null,
        };
    }
}
