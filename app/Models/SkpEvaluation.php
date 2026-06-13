<?php

namespace App\Models;

use App\Enums\PerformancePredicate;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $skp_plan_id
 * @property float|null $performance_score
 * @property float|null $behavior_score
 * @property float|null $final_score
 * @property PerformancePredicate|null $predicate
 * @property float|null $behavior_service
 * @property float|null $behavior_commit
 * @property float|null $behavior_initiative
 * @property float|null $behavior_teamwork
 * @property float|null $behavior_leadership
 * @property string|null $superior_feedback
 * @property string|null $evaluated_by
 * @property Carbon|null $evaluated_at
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SkpEvaluation extends Model
{
    use HasFactory;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'skp_plan_id', 'performance_score', 'behavior_score', 'final_score',
        'predicate', 'behavior_service', 'behavior_commit', 'behavior_initiative',
        'behavior_teamwork', 'behavior_leadership', 'superior_feedback',
        'evaluated_by', 'evaluated_at', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'performance_score'   => 'decimal:2',
            'behavior_score'      => 'decimal:2',
            'final_score'         => 'decimal:2',
            'predicate'           => PerformancePredicate::class,
            'behavior_service'    => 'decimal:2',
            'behavior_commit'     => 'decimal:2',
            'behavior_initiative' => 'decimal:2',
            'behavior_teamwork'   => 'decimal:2',
            'behavior_leadership' => 'decimal:2',
            'evaluated_at'        => 'datetime',
            'finalized_at'        => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SkpPlan::class, 'skp_plan_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
