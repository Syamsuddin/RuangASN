<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $project_id
 * @property string $title
 * @property string|null $description
 * @property RiskLevel $risk_level
 * @property int|null $probability
 * @property int|null $impact
 * @property string|null $mitigation
 * @property string $status
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectRisk extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'project_id', 'title', 'description', 'risk_level',
        'probability', 'impact', 'mitigation', 'status', 'owner_id',
    ];

    protected $casts = [
        'risk_level'  => RiskLevel::class,
        'probability' => 'integer',
        'impact'      => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
