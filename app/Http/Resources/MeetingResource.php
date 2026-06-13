<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Meeting */
class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduledAt = $this->scheduled_at;
        $estimatedEnd = $scheduledAt
            ? $scheduledAt->copy()->addMinutes($this->duration_minutes ?? 60)
            : null;

        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'description'      => $this->description,
            'meeting_type'     => $this->meeting_type,
            'meeting_mode'     => $this->meeting_mode,
            'status'           => $this->status,
            'scheduled_at'     => $scheduledAt?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'estimated_end_at' => $estimatedEnd?->toISOString(),
            'actual_start_at'  => $this->actual_start_at?->toISOString(),
            'actual_end_at'    => $this->actual_end_at?->toISOString(),
            'location'         => $this->location,
            'online_url'       => $this->online_url,
            'agenda_notes'     => $this->agenda_notes,
            'organization_id'  => $this->organization_id,
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),

            'host' => $this->whenLoaded('host', fn () => $this->host ? [
                'id' => $this->host->id, 'name' => $this->host->name,
            ] : null),

            'secretary' => $this->whenLoaded('secretary', fn () => $this->secretary ? [
                'id' => $this->secretary->id, 'name' => $this->secretary->name,
            ] : null),

            'participant_count' => $this->whenLoaded(
                'participants',
                fn () => $this->participants->count()
            ),

            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($p) => [
                'id'                => $p->id,
                'user_id'           => $p->user_id,
                'name'              => $p->user?->name,
                'role'              => $p->role,
                'attendance_status' => $p->attendance_status,
                'check_in_at'       => $p->check_in_at?->toISOString(),
            ])),

            'agenda_items' => $this->whenLoaded('agendaItems', fn () => $this->agendaItems->map(fn ($a) => [
                'id'               => $a->id,
                'title'            => $a->title,
                'description'      => $a->description,
                'duration_minutes' => $a->duration_minutes,
                'sort_order'       => $a->sort_order,
                'is_completed'     => $a->is_completed,
                'presenter_id'     => $a->presenter_id,
            ])),

            'decisions' => $this->whenLoaded('decisions', fn () => $this->decisions->map(fn ($d) => [
                'id'             => $d->id,
                'content'        => $d->content,
                'agenda_item_id' => $d->agenda_item_id,
                'recorded_by'    => $d->recorded_by,
                'recorded_at'    => $d->recorded_at?->toISOString(),
            ])),

            'action_items' => $this->whenLoaded('actionItems', fn () => $this->actionItems->map(fn ($ai) => [
                'id'              => $ai->id,
                'title'           => $ai->title,
                'description'     => $ai->description,
                'assignee_id'     => $ai->assignee_id,
                'assignee_name'   => $ai->assignee?->name,
                'due_date'        => $ai->due_date?->toDateString(),
                'is_task_created' => $ai->is_task_created,
                'task_id'         => $ai->task_id,
            ])),

            'minutes' => $this->whenLoaded('minutes', fn () => $this->minutes ? [
                'id'                  => $this->minutes->id,
                'content'             => $this->minutes->content,
                'ai_draft'            => $this->minutes->ai_draft,
                'status'              => $this->minutes->status,
                'data_classification' => $this->minutes->data_classification,
                'approved_by'         => $this->minutes->approved_by,
                'approved_at'         => $this->minutes->approved_at?->toISOString(),
            ] : null),
        ];
    }
}
