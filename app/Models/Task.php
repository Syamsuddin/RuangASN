<?php
namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $pemda_id
 * @property string|null $parent_task_id
 * @property string|null $meeting_id
 * @property string|null $project_id
 * @property string|null $skp_indicator_id
 * @property string $title
 * @property string|null $description
 * @property TaskType $task_type
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property string|null $creator_id
 * @property string|null $assignee_id
 * @property string|null $reviewer_id
 * @property Carbon|null $due_date
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property bool $is_recurring
 * @property array|null $recurring_pattern
 * @property string|null $recurring_parent_id
 * @property string|null $estimated_hours
 * @property string|null $actual_hours
 * @property array|null $tags
 * @property DataClassification $data_classification
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Task extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id', 'organization_id', 'pemda_id', 'parent_task_id', 'meeting_id',
        'project_id', 'skp_indicator_id', 'title', 'description',
        'task_type', 'status', 'priority',
        'creator_id', 'assignee_id', 'reviewer_id',
        'due_date', 'started_at', 'completed_at',
        'is_recurring', 'recurring_pattern', 'recurring_parent_id',
        'estimated_hours', 'actual_hours', 'tags', 'data_classification',
        'created_by', 'updated_by', 'deleted_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'due_date'            => 'date',
            'started_at'          => 'datetime',
            'completed_at'        => 'datetime',
            'is_recurring'        => 'boolean',
            'recurring_pattern'   => 'array',
            'tags'                => 'array',
            'estimated_hours'     => 'decimal:2',
            'actual_hours'        => 'decimal:2',
            'version'             => 'integer',
            'status'              => TaskStatus::class,
            'task_type'           => TaskType::class,
            'priority'            => TaskPriority::class,
            'data_classification' => DataClassification::class,
            'deleted_at'          => 'datetime',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function parent(): BelongsTo { return $this->belongsTo(Task::class, 'parent_task_id'); }
    public function subtasks(): HasMany { return $this->hasMany(Task::class, 'parent_task_id'); }
    public function evidences(): HasMany { return $this->hasMany(TaskEvidence::class); }
    public function statusHistories(): HasMany { return $this->hasMany(TaskStatusHistory::class); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class); }
    public function checklists(): HasMany { return $this->hasMany(TaskChecklist::class)->orderBy('sort_order'); }

    public function hasEvidence(): bool
    {
        return $this->evidences()->exists();
    }

    public function canTransitionTo(TaskStatus $newStatus, \App\Models\User $user): bool
    {
        $current = $this->status;
        $allowed = match ($current) {
            TaskStatus::DRAFT           => [TaskStatus::OPEN, TaskStatus::ASSIGNED],
            TaskStatus::OPEN            => [TaskStatus::ASSIGNED, TaskStatus::IN_PROGRESS, TaskStatus::CANCELLED],
            TaskStatus::ASSIGNED        => [TaskStatus::IN_PROGRESS, TaskStatus::CANCELLED],
            TaskStatus::IN_PROGRESS     => [TaskStatus::WAITING_REVIEW, TaskStatus::COMPLETED, TaskStatus::CANCELLED],
            TaskStatus::WAITING_REVIEW  => [TaskStatus::COMPLETED, TaskStatus::REVISION_NEEDED, TaskStatus::CLOSED],
            TaskStatus::REVISION_NEEDED => [TaskStatus::IN_PROGRESS, TaskStatus::CANCELLED],
            TaskStatus::COMPLETED       => [TaskStatus::CLOSED, TaskStatus::ARCHIVED],
            TaskStatus::CLOSED          => [TaskStatus::ARCHIVED],
            default                     => [],
        };
        return in_array($newStatus, $allowed);
    }
}
