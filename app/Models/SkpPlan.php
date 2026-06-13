<?php

namespace App\Models;

use App\Enums\PerformanceStatus;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $period_id
 * @property string|null $superior_id
 * @property PerformanceStatus $status
 * @property string|null $document_path
 * @property Carbon|null $submitted_at
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SkpPlan extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'user_id', 'period_id', 'superior_id',
        'status', 'document_path', 'submitted_at', 'approved_by', 'approved_at',
        'created_by', 'updated_by', 'deleted_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'       => PerformanceStatus::class,
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
            'version'      => 'integer',
            'deleted_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SkpPeriod::class, 'period_id');
    }

    public function superior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superior_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(SkpIndicator::class, 'skp_plan_id')->orderBy('sort_order');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(SkpEvaluation::class, 'skp_plan_id');
    }

    /**
     * State machine: allowed transitions per PermenPANRB 6/2022 workflow.
     *
     * planning   → active, archived
     * active     → evaluating, archived
     * evaluating → finalized, active, archived
     * finalized  → archived
     * archived   → (terminal)
     */
    public function canTransitionTo(PerformanceStatus $new): bool
    {
        $allowed = match ($this->status) {
            PerformanceStatus::PLANNING   => [PerformanceStatus::ACTIVE, PerformanceStatus::ARCHIVED],
            PerformanceStatus::ACTIVE     => [PerformanceStatus::EVALUATING, PerformanceStatus::ARCHIVED],
            PerformanceStatus::EVALUATING => [PerformanceStatus::FINALIZED, PerformanceStatus::ACTIVE, PerformanceStatus::ARCHIVED],
            PerformanceStatus::FINALIZED  => [PerformanceStatus::ARCHIVED],
            PerformanceStatus::ARCHIVED   => [],
        };

        return in_array($new, $allowed);
    }
}
