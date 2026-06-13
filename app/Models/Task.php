<?php
namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
