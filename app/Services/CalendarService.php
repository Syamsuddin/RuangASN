<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CalendarType;
use App\Models\CalendarEvent;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    public function create(array $data, User $owner): CalendarEvent
    {
        return DB::transaction(function () use ($data, $owner) {
            $event = CalendarEvent::create([
                ...$data,
                'organization_id' => $owner->organization_id,
                'owner_id'        => $owner->id,
                'created_by'      => $owner->id,
                'calendar_type'   => $data['calendar_type'] ?? CalendarType::PERSONAL->value,
            ]);

            $this->outbox->publish(
                'calendar.event.created',
                ['event_id' => $event->id, 'organization_id' => $event->organization_id],
                'CalendarEvent',
                $event->id
            );

            return $event;
        });
    }

    public function update(CalendarEvent $event, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($event, $data) {
            $event->update($data);

            $this->outbox->publish(
                'calendar.event.updated',
                ['event_id' => $event->id, 'organization_id' => $event->organization_id],
                'CalendarEvent',
                $event->id
            );

            return $event;
        });
    }

    public function delete(CalendarEvent $event, User $actor): void
    {
        DB::transaction(function () use ($event) {
            $this->audit->log(
                AuditAction::DELETED, 'CalendarEvent', $event->id,
                ['title' => $event->title], []
            );
            $event->delete();
        });
    }

    public function feedForUser(User $user, Carbon $start, Carbon $end): array
    {
        $startIso = $start->toISOString();
        $endIso   = $end->toISOString();

        $events   = $this->fetchCalendarEvents($user, $startIso, $endIso);
        $meetings = $this->fetchMeetingEvents($user, $start, $end);
        $tasks    = $this->fetchTaskEvents($user, $start, $end);

        return array_merge($events, $meetings, $tasks);
    }

    private function fetchCalendarEvents(User $user, string $start, string $end): array
    {
        return CalendarEvent::inRange($start, $end)
            ->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhere('is_public', true)
            )
            ->get()
            ->map(fn (CalendarEvent $e) => [
                'id'            => $e->id,
                'source'        => 'event',
                'calendar_type' => $e->calendar_type->value,
                'title'         => $e->title,
                'start'         => $e->start_at->toISOString(),
                'end'           => $e->end_at->toISOString(),
                'all_day'       => $e->all_day,
                'color'         => $e->color ?? '#8B5CF6',
                'url'           => null,
            ])
            ->values()
            ->all();
    }

    private function fetchMeetingEvents(User $user, Carbon $start, Carbon $end): array
    {
        $participantMeetingIds = MeetingParticipant::where('user_id', $user->id)
            ->pluck('meeting_id');

        return Meeting::whereBetween('scheduled_at', [$start, $end])
            ->where(fn ($q) => $q
                ->where('host_id', $user->id)
                ->orWhere('secretary_id', $user->id)
                ->orWhereIn('id', $participantMeetingIds)
            )
            ->get()
            ->map(function (Meeting $m) {
                $endAt = $m->scheduled_at->copy()->addMinutes($m->duration_minutes ?? 60);
                return [
                    'id'            => $m->id,
                    'source'        => 'meeting',
                    'calendar_type' => CalendarType::MEETING->value,
                    'title'         => $m->title,
                    'start'         => $m->scheduled_at->toISOString(),
                    'end'           => $endAt->toISOString(),
                    'all_day'       => false,
                    'color'         => '#3B82F6',
                    'url'           => '/meetings/' . $m->id,
                ];
            })
            ->values()
            ->all();
    }

    private function fetchTaskEvents(User $user, Carbon $start, Carbon $end): array
    {
        return Task::whereNotNull('due_date')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->where(fn ($q) => $q
                ->where('assignee_id', $user->id)
                ->orWhere('creator_id', $user->id)
            )
            ->get()
            ->map(fn (Task $t) => [
                'id'            => $t->id,
                'source'        => 'task',
                'calendar_type' => 'task',
                'title'         => $t->title,
                'start'         => $t->due_date->toDateString() . 'T00:00:00.000Z',
                'end'           => $t->due_date->toDateString() . 'T23:59:59.000Z',
                'all_day'       => true,
                'color'         => '#F59E0B',
                'url'           => '/tasks/' . $t->id,
            ])
            ->values()
            ->all();
    }
}
