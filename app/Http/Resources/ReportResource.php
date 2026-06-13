<?php

namespace App\Http\Resources;

use App\Enums\DataClassification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Report */
class ReportResource extends JsonResource
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
            'id'                   => $this->id,
            'title'                => $this->title,
            'content'              => $this->content,
            'report_type'          => $this->report_type,
            'period_type'          => $this->period_type,
            'status'               => $this->status,
            'status_label'         => $this->statusLabel(),
            'period_start_date'    => $this->period_start_date?->toDateString(),
            'period_end_date'      => $this->period_end_date?->toDateString(),
            'data_sources'         => $this->data_sources ?? [],
            'data_classification'  => $level,
            'classification_label' => $classificationLabels[$level],
            'has_ai_draft'         => ! empty($this->ai_draft),
            'ai_draft'             => $this->ai_draft,
            'submitted_at'         => $this->submitted_at?->toISOString(),
            'approved_at'          => $this->approved_at?->toISOString(),
            'published_at'         => $this->published_at?->toISOString(),
            'organization_id'      => $this->organization_id,
            'author_id'            => $this->author_id,
            'version'              => $this->version,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),

            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id' => $this->author->id, 'name' => $this->author->name,
            ] : null),

            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id, 'name' => $this->approver->name,
            ] : null),

            'status_histories' => $this->whenLoaded('statusHistories', fn () =>
                $this->statusHistories->map(fn ($h) => [
                    'id'          => $h->id,
                    'from_status' => $h->from_status,
                    'to_status'   => $h->to_status,
                    'notes'       => $h->notes,
                    'changed_at'  => $h->changed_at?->toISOString(),
                    'changed_by'  => $h->changedBy ? [
                        'id' => $h->changedBy->id, 'name' => $h->changedBy->name,
                    ] : null,
                ])
            ),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status?->value ?? $this->status) {
            'draft'     => 'Draft',
            'submitted' => 'Diajukan',
            'in_review' => 'Sedang Direview',
            'revision'  => 'Perlu Revisi',
            'approved'  => 'Disetujui',
            'published' => 'Dipublikasikan',
            'archived'  => 'Diarsipkan',
            'rejected'  => 'Ditolak',
            default     => 'Unknown',
        };
    }
}
