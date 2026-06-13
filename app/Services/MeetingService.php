<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\AuditAction;
use App\Enums\CalendarType;
use App\Enums\MeetingStatus;
use App\Enums\TaskType;
use App\Models\CalendarEvent;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingDecision;
use App\Models\MeetingMinute;
use App\Models\MeetingParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
        private TaskService $taskService,
    ) {}

    public function create(array $data, User $host): Meeting
    {
        return DB::transaction(function () use ($data, $host) {
            $meeting = Meeting::create([
                ...$data,
                'organization_id' => $host->organization_id,
                'pemda_id'        => $host->pemda_id,
                'host_id'         => $host->id,
                'created_by'      => $host->id,
                'status'          => MeetingStatus::DRAFT->value,
            ]);

            // Auto-add host as participant
            MeetingParticipant::create([
                'meeting_id'        => $meeting->id,
                'user_id'           => $host->id,
                'role'              => 'host',
                'attendance_status' => AttendanceStatus::ACCEPTED->value,
            ]);

            $this->outbox->publish('meeting.created', $meeting->fresh()->toArray(), 'Meeting', $meeting->id);
            return $meeting->fresh();
        });
    }

    public function update(Meeting $meeting, array $data): Meeting
    {
        return DB::transaction(function () use ($meeting, $data) {
            $meeting->update($data);
            $this->outbox->publish('meeting.updated', $meeting->fresh()->toArray(), 'Meeting', $meeting->id);
            $this->syncCalendarEvent($meeting->fresh());
            return $meeting->fresh();
        });
    }

    public function transitionStatus(Meeting $meeting, MeetingStatus $new, User $actor, ?string $reason = null): Meeting
    {
        return DB::transaction(function () use ($meeting, $new, $actor, $reason) {
            if (! $meeting->canTransitionTo($new, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat berpindah dari {$meeting->status->value} ke {$new->value}.",
                ]);
            }

            $old = $meeting->status;

            $updates = ['status' => $new->value];
            if ($new === MeetingStatus::IN_PROGRESS && ! $meeting->actual_start_at) {
                $updates['actual_start_at'] = now();
            }
            if ($new === MeetingStatus::COMPLETED) {
                $updates['actual_end_at'] = now();
            }

            $meeting->update($updates);
            $fresh = $meeting->fresh();

            if ($new === MeetingStatus::SCHEDULED) {
                $this->ensureCalendarEvent($fresh, $actor);
            } elseif ($new === MeetingStatus::CANCELLED) {
                $this->deleteCalendarEvent($fresh);
            }

            $this->outbox->publish('meeting.status_changed', [
                'meeting_id'      => $meeting->id,
                'from_status'     => $old->value,
                'to_status'       => $new->value,
                'changed_by'      => $actor->id,
                'organization_id' => $meeting->organization_id,
                'reason'          => $reason,
            ], 'Meeting', $meeting->id);

            $this->audit->log(
                AuditAction::STATUS_CHANGED, 'Meeting', $meeting->id,
                ['status' => $old->value],
                ['status' => $new->value]
            );

            return $fresh;
        });
    }

    /** Idempotent: create CalendarEvent if one doesn't exist for this meeting yet. */
    public function ensureCalendarEvent(Meeting $meeting, User $host): CalendarEvent
    {
        $existing = CalendarEvent::where('meeting_id', $meeting->id)->first();
        if ($existing) {
            return $existing;
        }

        $endAt = $meeting->scheduled_at?->copy()->addMinutes($meeting->duration_minutes ?? 60);

        return CalendarEvent::create([
            'organization_id' => $meeting->organization_id,
            'calendar_type'   => CalendarType::MEETING->value,
            'owner_id'        => $meeting->host_id ?? $host->id,
            'meeting_id'      => $meeting->id,
            'title'           => $meeting->title,
            'start_at'        => $meeting->scheduled_at,
            'end_at'          => $endAt,
            'location'        => $meeting->location,
            'color'           => '#3B82F6',
            'all_day'         => false,
            'is_recurring'    => false,
            'is_public'       => false,
            'created_by'      => $meeting->host_id ?? $host->id,
        ]);
    }

    /** Sync time/title changes to an existing linked CalendarEvent. */
    private function syncCalendarEvent(Meeting $meeting): void
    {
        $event = CalendarEvent::where('meeting_id', $meeting->id)->first();
        if (! $event || ! $meeting->scheduled_at) {
            return;
        }

        $endAt = $meeting->scheduled_at->copy()->addMinutes($meeting->duration_minutes ?? 60);
        $event->update([
            'title'    => $meeting->title,
            'start_at' => $meeting->scheduled_at,
            'end_at'   => $endAt,
            'location' => $meeting->location,
        ]);
    }

    /** Soft-delete the linked CalendarEvent when a meeting is cancelled. */
    private function deleteCalendarEvent(Meeting $meeting): void
    {
        CalendarEvent::where('meeting_id', $meeting->id)->get()->each->delete();
    }

    public function addParticipant(Meeting $meeting, array $data): MeetingParticipant
    {
        return DB::transaction(function () use ($meeting, $data) {
            return MeetingParticipant::firstOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $data['user_id']],
                [
                    'role'              => $data['role'] ?? 'participant',
                    'attendance_status' => AttendanceStatus::INVITED->value,
                ]
            );
        });
    }

    public function recordAttendance(MeetingParticipant $participant, AttendanceStatus $status): MeetingParticipant
    {
        return DB::transaction(function () use ($participant, $status) {
            $updates = ['attendance_status' => $status->value];
            if ($status === AttendanceStatus::PRESENT) {
                $updates['check_in_at'] = now();
            }
            if ($status === AttendanceStatus::LEFT_EARLY) {
                $updates['check_out_at'] = now();
            }
            $participant->update($updates);
            return $participant->fresh();
        });
    }

    public function addAgendaItem(Meeting $meeting, array $data): MeetingAgendaItem
    {
        return DB::transaction(function () use ($meeting, $data) {
            $maxOrder = $meeting->agendaItems()->max('sort_order') ?? 0;
            return MeetingAgendaItem::create([
                ...$data,
                'meeting_id' => $meeting->id,
                'sort_order' => $maxOrder + 1,
            ]);
        });
    }

    public function addDecision(Meeting $meeting, array $data, User $recorder): MeetingDecision
    {
        return DB::transaction(function () use ($meeting, $data, $recorder) {
            return MeetingDecision::create([
                ...$data,
                'meeting_id'  => $meeting->id,
                'recorded_by' => $recorder->id,
            ]);
        });
    }

    public function addActionItem(Meeting $meeting, array $data, User $creator): MeetingActionItem
    {
        return DB::transaction(function () use ($meeting, $data, $creator) {
            $taskId = null;
            $isTaskCreated = false;

            if (! empty($data['create_task'])) {
                $task = $this->taskService->create([
                    'title'       => $data['title'],
                    'description' => $data['description'] ?? null,
                    'assignee_id' => $data['assignee_id'] ?? null,
                    'due_date'    => $data['due_date'] ?? null,
                    'task_type'   => TaskType::PERSONAL->value,
                    'priority'    => 'medium',
                    'meeting_id'  => $meeting->id,
                ], $creator);
                $taskId = $task->id;
                $isTaskCreated = true;
            }

            return MeetingActionItem::create([
                'meeting_id'      => $meeting->id,
                'decision_id'     => $data['decision_id'] ?? null,
                'task_id'         => $taskId,
                'title'           => $data['title'],
                'description'     => $data['description'] ?? null,
                'assignee_id'     => $data['assignee_id'] ?? null,
                'due_date'        => $data['due_date'] ?? null,
                'is_task_created' => $isTaskCreated,
                'created_by'      => $creator->id,
            ]);
        });
    }

    public function upsertMinutes(Meeting $meeting, array $data, User $author): MeetingMinute
    {
        return DB::transaction(function () use ($meeting, $data, $author) {
            return MeetingMinute::updateOrCreate(
                ['meeting_id' => $meeting->id],
                [...$data, 'created_by' => $author->id]
            );
        });
    }

    public function approveMinutes(MeetingMinute $minutes, User $approver): MeetingMinute
    {
        return DB::transaction(function () use ($minutes, $approver) {
            $minutes->update([
                'status'      => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->audit->log(
                AuditAction::APPROVED, 'MeetingMinute', $minutes->id,
                ['status' => 'draft'],
                ['status' => 'approved']
            );

            return $minutes->fresh();
        });
    }
}
