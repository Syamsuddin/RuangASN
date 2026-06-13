<?php
namespace App\Services\Ai;

use App\Enums\MeetingType;
use App\Enums\ProposedActionType;
use App\Enums\ReportPeriodType;
use App\Enums\ReportType;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\MeetingService;
use App\Services\ReportService;
use App\Services\TaskService;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Executes a confirmed proposed action THROUGH the confirming user's policies
 * (AXIOM-04: no privilege escalation). Each branch authorizes via the SAME
 * policy the normal feature uses; if the user can't, neither can the AI.
 *
 * This class never runs on its own — it is only invoked by
 * AiOrchestratorService::confirmAction after the user explicitly confirms.
 */
class ProposedActionExecutor
{
    public function __construct(
        private TaskService $tasks,
        private MeetingService $meetings,
        private ReportService $reports,
        private CalendarService $calendar,
    ) {}

    /**
     * @param array<string, mixed> $action  {type, payload}
     * @return array{type: string, entity: string, id: string, title: string}
     */
    public function execute(array $action, User $user): array
    {
        $type    = ProposedActionType::tryFrom((string) ($action['type'] ?? ''));
        $payload = (array) ($action['payload'] ?? []);

        if ($type === null) {
            throw new InvalidArgumentException('Tipe aksi tidak dikenal.');
        }

        return match ($type) {
            ProposedActionType::CREATE_TASK           => $this->createTask($payload, $user),
            ProposedActionType::SCHEDULE_MEETING      => $this->scheduleMeeting($payload, $user),
            ProposedActionType::GENERATE_REPORT_DRAFT => $this->generateReportDraft($payload, $user),
            ProposedActionType::ADD_CALENDAR_EVENT    => $this->addCalendarEvent($payload, $user),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{type: string, entity: string, id: string, title: string}
     */
    private function createTask(array $payload, User $user): array
    {
        Gate::forUser($user)->authorize('create', Task::class);

        $task = $this->tasks->create([
            'title'       => (string) ($payload['title'] ?? 'Tugas dari asisten AI'),
            'description' => $payload['description'] ?? null,
            'task_type'   => $payload['task_type'] ?? TaskType::PERSONAL->value,
            'priority'    => $payload['priority'] ?? TaskPriority::MEDIUM->value,
            'due_date'    => $payload['due_date'] ?? null,
            'assignee_id' => $payload['assignee_id'] ?? null,
        ], $user);

        return ['type' => ProposedActionType::CREATE_TASK->value, 'entity' => 'Task', 'id' => $task->id, 'title' => $task->title];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{type: string, entity: string, id: string, title: string}
     */
    private function scheduleMeeting(array $payload, User $user): array
    {
        Gate::forUser($user)->authorize('create', Meeting::class);

        $meeting = $this->meetings->create([
            'title'            => (string) ($payload['title'] ?? 'Rapat dari asisten AI'),
            'description'      => $payload['description'] ?? null,
            'meeting_type'     => $payload['meeting_type'] ?? MeetingType::INTERNAL->value,
            'meeting_mode'     => $payload['meeting_mode'] ?? 'offline',
            'scheduled_at'     => $payload['scheduled_at'] ?? now()->addDay(),
            'duration_minutes' => $payload['duration_minutes'] ?? 60,
            'location'         => $payload['location'] ?? null,
        ], $user);

        return ['type' => ProposedActionType::SCHEDULE_MEETING->value, 'entity' => 'Meeting', 'id' => $meeting->id, 'title' => $meeting->title];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{type: string, entity: string, id: string, title: string}
     */
    private function generateReportDraft(array $payload, User $user): array
    {
        Gate::forUser($user)->authorize('create', Report::class);

        $report = $this->reports->create([
            'title'               => (string) ($payload['title'] ?? 'Laporan dari asisten AI'),
            'report_type'         => $payload['report_type'] ?? ReportType::ACTIVITY->value,
            'period_type'         => $payload['period_type'] ?? ReportPeriodType::MONTHLY->value,
            'period_start_date'   => $payload['period_start_date'] ?? now()->startOfMonth(),
            'period_end_date'     => $payload['period_end_date'] ?? now()->endOfMonth(),
            'data_classification' => $payload['data_classification'] ?? 3,
            'content'             => $payload['content'] ?? null,
        ], $user);

        $report = $this->reports->generateAiDraft($report);

        return ['type' => ProposedActionType::GENERATE_REPORT_DRAFT->value, 'entity' => 'Report', 'id' => $report->id, 'title' => $report->title];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{type: string, entity: string, id: string, title: string}
     */
    private function addCalendarEvent(array $payload, User $user): array
    {
        Gate::forUser($user)->authorize('create', \App\Models\CalendarEvent::class);

        $start = $payload['start_at'] ?? now()->addDay();
        $end   = $payload['end_at'] ?? \Illuminate\Support\Carbon::parse($start)->addHour();

        $event = $this->calendar->create([
            'title'         => (string) ($payload['title'] ?? 'Agenda dari asisten AI'),
            'description'   => $payload['description'] ?? null,
            'calendar_type' => $payload['calendar_type'] ?? 'personal',
            'start_at'      => $start,
            'end_at'        => $end,
            'all_day'       => $payload['all_day'] ?? false,
            'location'      => $payload['location'] ?? null,
        ], $user);

        return ['type' => ProposedActionType::ADD_CALENDAR_EVENT->value, 'entity' => 'CalendarEvent', 'id' => $event->id, 'title' => $event->title];
    }
}
