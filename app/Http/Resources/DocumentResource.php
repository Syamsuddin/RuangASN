<?php

namespace App\Http\Resources;

use App\Enums\DataClassification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $classificationLabels = [
            DataClassification::PUBLIC->value       => 'Publik',
            DataClassification::INTERNAL->value     => 'Internal',
            DataClassification::CONFIDENTIAL->value => 'Rahasia',
            DataClassification::RESTRICTED->value   => 'Sangat Rahasia',
        ];

        $level = $this->data_classification->value;

        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'description'         => $this->description,
            'document_type'       => $this->document_type,
            'status'              => $this->status,
            'document_number'     => $this->document_number,
            'document_date'       => $this->document_date?->toDateString(),
            'effective_date'      => $this->effective_date?->toDateString(),
            'expiry_date'         => $this->expiry_date?->toDateString(),
            'file_name'           => $this->file_name,
            'file_size'           => $this->file_size,
            'mime_type'           => $this->mime_type,
            'page_count'          => $this->page_count,
            'version_number'      => $this->version_number,
            'is_latest'           => $this->is_latest,
            'ai_summary'          => $this->ai_summary,
            'ai_tags'             => $this->ai_tags,
            'tags'                => $this->tags,
            'data_classification' => $level,
            'classification_label'=> $classificationLabels[$level],
            'organization_id'     => $this->organization_id,
            'owner_id'            => $this->owner_id,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),

            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id, 'name' => $this->owner->name,
            ] : null),

            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id, 'name' => $this->creator->name,
            ] : null),

            'approvals' => $this->whenLoaded('approvals', fn () => $this->approvals->map(fn ($a) => [
                'id'          => $a->id,
                'step_number' => $a->step_number,
                'status'      => $a->status,
                'notes'       => $a->notes,
                'decided_at'  => $a->decided_at instanceof \Illuminate\Support\Carbon ? $a->decided_at->toISOString() : null,
                'approver'    => ($approver = $a->approver) ? ['id' => $approver->id, 'name' => $approver->name] : null,
            ])),

            'versions_count' => $this->whenLoaded('versions', fn () => $this->versions->count()),

            'meeting' => $this->whenLoaded('meeting', fn () => $this->meeting ? [
                'id' => $this->meeting->id, 'title' => $this->meeting->title,
            ] : null),

            'task' => $this->whenLoaded('task', fn () => $this->task ? [
                'id' => $this->task->id, 'title' => $this->task->title,
            ] : null),
        ];
    }
}
