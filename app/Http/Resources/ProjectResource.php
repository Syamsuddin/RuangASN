<?php

namespace App\Http\Resources;

use App\Enums\DataClassification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $classificationLabels = [
            DataClassification::PUBLIC->value       => 'Publik',
            DataClassification::INTERNAL->value     => 'Internal',
            DataClassification::CONFIDENTIAL->value => 'Rahasia',
            DataClassification::RESTRICTED->value   => 'Sangat Rahasia',
        ];

        $level  = $this->data_classification->value;
        $budget = $this->budget !== null ? (float) $this->budget : null;
        $spent  = (float) $this->budget_spent;

        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'description'         => $this->description,
            'objectives'          => $this->objectives,
            'status'              => $this->status,
            'status_label'        => $this->statusLabel(),
            'planned_start_date'  => $this->planned_start_date?->toDateString(),
            'planned_end_date'    => $this->planned_end_date?->toDateString(),
            'actual_start_date'   => $this->actual_start_date?->toDateString(),
            'actual_end_date'     => $this->actual_end_date?->toDateString(),
            'budget'              => $budget,
            'budget_spent'        => $spent,
            'budget_utilization'  => $budget && $budget > 0 ? (int) round($spent / $budget * 100) : 0,
            'progress_percent'    => (int) $this->progress_percent,
            'computed_progress'   => $this->computeProgress(),
            'tags'                => $this->tags ?? [],
            'data_classification' => $level,
            'classification_label' => $classificationLabels[$level],
            'organization_id'     => $this->organization_id,
            'owner_id'            => $this->owner_id,
            'manager_id'          => $this->manager_id,
            'team_id'             => $this->team_id,
            'version'             => $this->version,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),

            'tasks_count'    => $this->whenCounted('tasks'),
            'meetings_count' => $this->whenCounted('meetings'),

            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id, 'name' => $this->owner->name,
            ] : null),

            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id, 'name' => $this->manager->name,
            ] : null),

            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id, 'name' => $this->team->name,
            ] : null),

            'members' => $this->whenLoaded('members', fn () =>
                $this->members->map(fn ($m) => [
                    'id'        => $m->id,
                    'user_id'   => $m->user_id,
                    'role'      => $m->role,
                    'joined_at' => $m->joined_at?->toISOString(),
                    'left_at'   => $m->left_at?->toISOString(),
                    'user'      => $m->relationLoaded('user') && $m->user ? [
                        'id' => $m->user->id, 'name' => $m->user->name,
                    ] : null,
                ])
            ),

            'milestones' => $this->whenLoaded('milestones', fn () =>
                $this->milestones->map(fn ($ms) => [
                    'id'           => $ms->id,
                    'name'         => $ms->name,
                    'description'  => $ms->description,
                    'status'       => $ms->status,
                    'due_date'     => $ms->due_date?->toDateString(),
                    'completed_at' => $ms->completed_at?->toISOString(),
                    'sort_order'   => $ms->sort_order,
                ])
            ),

            'risks' => $this->whenLoaded('risks', fn () =>
                $this->risks->map(fn ($r) => [
                    'id'          => $r->id,
                    'title'       => $r->title,
                    'description' => $r->description,
                    'risk_level'  => $r->risk_level,
                    'probability' => $r->probability,
                    'impact'      => $r->impact,
                    'mitigation'  => $r->mitigation,
                    'status'      => $r->status,
                    'owner_id'    => $r->owner_id,
                    'owner'       => $r->relationLoaded('owner') && $r->owner ? [
                        'id' => $r->owner->id, 'name' => $r->owner->name,
                    ] : null,
                ])
            ),

            'status_histories' => $this->whenLoaded('statusHistories', fn () =>
                $this->statusHistories->map(fn ($h) => [
                    'id'          => $h->id,
                    'from_status' => $h->from_status,
                    'to_status'   => $h->to_status,
                    'notes'       => $h->notes,
                    'changed_at'  => $h->changed_at?->toISOString(),
                    'changed_by'  => $h->relationLoaded('changedBy') && $h->changedBy ? [
                        'id' => $h->changedBy->id, 'name' => $h->changedBy->name,
                    ] : null,
                ])
            ),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status?->value ?? $this->status) {
            'draft'      => 'Draft',
            'planning'   => 'Perencanaan',
            'active'     => 'Aktif',
            'on_hold'    => 'Ditangguhkan',
            'monitoring' => 'Monitoring',
            'closing'    => 'Penutupan',
            'completed'  => 'Selesai',
            'cancelled'  => 'Dibatalkan',
            'archived'   => 'Diarsipkan',
            default      => 'Unknown',
        };
    }
}
