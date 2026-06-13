<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SkpPlan */
class SkpPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'user_id'         => $this->user_id,
            'period_id'       => $this->period_id,
            'superior_id'     => $this->superior_id,
            'status'          => $this->status->value,
            'status_label'    => $this->statusLabel(),
            'document_path'   => $this->document_path,
            'submitted_at'    => $this->submitted_at?->toISOString(),
            'approved_at'     => $this->approved_at?->toISOString(),
            'version'         => $this->version,
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),

            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id, 'name' => $this->user->name,
            ] : null),

            'superior' => $this->whenLoaded('superior', fn () => $this->superior ? [
                'id' => $this->superior->id, 'name' => $this->superior->name,
            ] : null),

            'period' => $this->whenLoaded('period', fn () => $this->period ? [
                'id'         => $this->period->id,
                'name'       => $this->period->name,
                'year'       => $this->period->year,
                'semester'   => $this->period->semester,
                'start_date' => $this->period->start_date?->toDateString(),
                'end_date'   => $this->period->end_date?->toDateString(),
            ] : null),

            'indicators' => $this->whenLoaded('indicators', fn () =>
                $this->indicators->map(fn ($ind) => [
                    'id'                   => $ind->id,
                    'perspective'          => $ind->perspective?->value ?? $ind->perspective,
                    'name'                 => $ind->name,
                    'target_value'         => (float) $ind->target_value,
                    'target_unit'          => $ind->target_unit,
                    'weight'               => (float) $ind->weight,
                    'realization_value'    => $ind->realization_value !== null ? (float) $ind->realization_value : null,
                    'achievement_pct'      => $ind->achievement_pct !== null ? (float) $ind->achievement_pct : null,
                    'superior_expectation' => $ind->superior_expectation,
                    'sort_order'           => $ind->sort_order,
                    'parent_indicator_id'  => $ind->parent_indicator_id,
                ])
            ),

            'evaluation' => $this->whenLoaded('evaluation', fn () => $this->evaluation ? [
                'id'                  => $this->evaluation->id,
                'performance_score'   => $this->evaluation->performance_score !== null ? (float) $this->evaluation->performance_score : null,
                'behavior_score'      => $this->evaluation->behavior_score !== null ? (float) $this->evaluation->behavior_score : null,
                'final_score'         => $this->evaluation->final_score !== null ? (float) $this->evaluation->final_score : null,
                'predicate'           => $this->evaluation->predicate?->value ?? $this->evaluation->predicate,
                'predicate_label'     => $this->predicateLabel($this->evaluation->predicate?->value),
                'behavior_service'    => $this->evaluation->behavior_service !== null ? (float) $this->evaluation->behavior_service : null,
                'behavior_commit'     => $this->evaluation->behavior_commit !== null ? (float) $this->evaluation->behavior_commit : null,
                'behavior_initiative' => $this->evaluation->behavior_initiative !== null ? (float) $this->evaluation->behavior_initiative : null,
                'behavior_teamwork'   => $this->evaluation->behavior_teamwork !== null ? (float) $this->evaluation->behavior_teamwork : null,
                'behavior_leadership' => $this->evaluation->behavior_leadership !== null ? (float) $this->evaluation->behavior_leadership : null,
                'superior_feedback'   => $this->evaluation->superior_feedback,
                'evaluated_at'        => $this->evaluation->evaluated_at?->toISOString(),
                'finalized_at'        => $this->evaluation->finalized_at?->toISOString(),
            ] : null),

            // Preview performance score from indicators loaded
            'overall_achievement_pct' => $this->whenLoaded('indicators', function () {
                $indicators = $this->indicators;
                if ($indicators->isEmpty()) {
                    return 0;
                }
                $totalWeight = $indicators->sum('weight');
                if ($totalWeight <= 0) {
                    return 0;
                }
                $weightedSum = $indicators->sum(fn ($i) => (float) ($i->achievement_pct ?? 0) * (float) $i->weight);

                return min(round($weightedSum / $totalWeight, 2), 120.0);
            }),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status->value) {
            'planning'   => 'Perencanaan',
            'active'     => 'Aktif',
            'evaluating' => 'Evaluasi',
            'finalized'  => 'Selesai',
            default      => 'Diarsipkan',
        };
    }

    private function predicateLabel(?string $predicate): string
    {
        return match ($predicate) {
            'sangat_baik'   => 'Sangat Baik',
            'baik'          => 'Baik',
            'cukup'         => 'Cukup',
            'kurang'        => 'Kurang',
            'sangat_kurang' => 'Sangat Kurang',
            default         => '—',
        };
    }
}
