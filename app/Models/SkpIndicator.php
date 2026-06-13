<?php

namespace App\Models;

use App\Enums\SkpPerspective;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $skp_plan_id
 * @property string|null $parent_indicator_id
 * @property SkpPerspective $perspective
 * @property string $name
 * @property float $target_value
 * @property string $target_unit
 * @property float $weight
 * @property float|null $realization_value
 * @property float|null $achievement_pct
 * @property string|null $superior_expectation
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SkpIndicator extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'skp_plan_id', 'parent_indicator_id', 'perspective',
        'name', 'target_value', 'target_unit', 'weight',
        'realization_value', 'achievement_pct', 'superior_expectation', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'perspective'        => SkpPerspective::class,
            'target_value'       => 'decimal:4',
            'weight'             => 'decimal:2',
            'realization_value'  => 'decimal:4',
            'achievement_pct'    => 'decimal:2',
            'sort_order'         => 'integer',
            'deleted_at'         => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SkpPlan::class, 'skp_plan_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_indicator_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_indicator_id');
    }

    public function realizations(): HasMany
    {
        return $this->hasMany(SkpRealization::class, 'indicator_id');
    }

    /**
     * Recompute realization_value (sum of all realizations) and achievement_pct.
     * Persists both columns to DB.
     */
    public function recomputeAchievement(): void
    {
        $sum = (float) $this->realizations()->sum('realization_value');
        $target = (float) $this->target_value;
        $pct = ($target > 0) ? round($sum / $target * 100, 2) : 0.0;

        $this->update([
            'realization_value' => $sum,
            'achievement_pct'   => $pct,
        ]);
    }
}
