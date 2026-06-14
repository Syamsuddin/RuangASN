<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $project_id
 * @property string $name
 * @property string|null $description
 * @property MilestoneStatus $status
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectMilestone extends Model
{
    use HasFactory;
    use HasUlid;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'project_id', 'name', 'description', 'status',
        'due_date', 'completed_at', 'sort_order',
    ];

    protected $casts = [
        'status'       => MilestoneStatus::class,
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'sort_order'   => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
