<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'task_type'       => $this->task_type,
            'status'          => $this->status,
            'priority'        => $this->priority,
            'due_date'        => $this->due_date?->toDateString(),
            'started_at'      => $this->started_at?->toISOString(),
            'completed_at'    => $this->completed_at?->toISOString(),
            'organization_id' => $this->organization_id,
            'creator'         => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id, 'name' => $this->creator->name,
            ]),
            'assignee'        => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id, 'name' => $this->assignee->name,
            ] : null),
            'reviewer'        => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id, 'name' => $this->reviewer->name,
            ] : null),
            'evidence_count'  => $this->whenLoaded('evidences', fn () => $this->evidences->count()),
            'evidences'       => $this->whenLoaded('evidences', fn () => $this->evidences->map(fn ($e) => [
                'id'            => $e->id,
                'evidence_type' => $e->evidence_type,
                'title'         => $e->title,
                'created_at'    => $e->created_at?->toISOString(),
            ])),
            'checklists'      => $this->whenLoaded('checklists', fn () => $this->checklists->map(fn ($c) => [
                'id'         => $c->id,
                'title'      => $c->title,
                'is_done'    => $c->is_done,
                'sort_order' => $c->sort_order,
            ])),
            'has_evidence'    => $this->evidences_count ?? null,
            'tags'            => $this->tags,
            'version'         => $this->version,
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
