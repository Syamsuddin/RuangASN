<?php

namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $pemda_id
 * @property string|null $team_id
 * @property string $name
 * @property string|null $description
 * @property string|null $objectives
 * @property ProjectStatus $status
 * @property Carbon|null $planned_start_date
 * @property Carbon|null $planned_end_date
 * @property Carbon|null $actual_start_date
 * @property Carbon|null $actual_end_date
 * @property string|null $budget
 * @property string $budget_spent
 * @property string $owner_id
 * @property string|null $manager_id
 * @property int $progress_percent
 * @property array|null $tags
 * @property DataClassification $data_classification
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectMember> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectMilestone> $milestones
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectRisk> $risks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectStatusHistory> $statusHistories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Task> $tasks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Meeting> $meetings
 * @property-read int|null $tasks_count
 * @property-read int|null $meetings_count
 * @property-read int|null $milestones_count
 * @property-read int|null $risks_count
 * @property-read User|null $owner
 * @property-read User|null $manager
 * @property-read Team|null $team
 */
class Project extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id', 'organization_id', 'pemda_id', 'team_id',
        'name', 'description', 'objectives', 'status',
        'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date',
        'budget', 'budget_spent', 'owner_id', 'manager_id', 'progress_percent',
        'tags', 'data_classification',
        'created_by', 'updated_by', 'deleted_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'              => ProjectStatus::class,
            'data_classification' => DataClassification::class,
            'planned_start_date'  => 'date',
            'planned_end_date'    => 'date',
            'actual_start_date'   => 'date',
            'actual_end_date'     => 'date',
            'budget'              => 'decimal:2',
            'budget_spent'        => 'decimal:2',
            'progress_percent'    => 'integer',
            'tags'                => 'array',
            'version'             => 'integer',
            'deleted_at'          => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class)->orderBy('changed_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'project_id');
    }

    /**
     * Project lifecycle state machine (blueprint ProjectStatus).
     */
    public function canTransitionTo(ProjectStatus $new, User $user): bool
    {
        $allowed = match ($this->status) {
            ProjectStatus::DRAFT      => [ProjectStatus::PLANNING, ProjectStatus::CANCELLED],
            ProjectStatus::PLANNING   => [ProjectStatus::ACTIVE, ProjectStatus::CANCELLED],
            ProjectStatus::ACTIVE     => [ProjectStatus::ON_HOLD, ProjectStatus::MONITORING, ProjectStatus::CLOSING, ProjectStatus::CANCELLED],
            ProjectStatus::ON_HOLD    => [ProjectStatus::ACTIVE, ProjectStatus::CANCELLED],
            ProjectStatus::MONITORING => [ProjectStatus::ACTIVE, ProjectStatus::CLOSING],
            ProjectStatus::CLOSING    => [ProjectStatus::COMPLETED, ProjectStatus::ACTIVE],
            ProjectStatus::COMPLETED  => [ProjectStatus::ARCHIVED],
            ProjectStatus::CANCELLED  => [ProjectStatus::ARCHIVED],
            ProjectStatus::ARCHIVED   => [],
        };

        return in_array($new, $allowed, true);
    }

    public function isMember(User $user): bool
    {
        if ($this->owner_id === $user->id || $this->manager_id === $user->id) {
            return true;
        }

        if ($this->relationLoaded('members')) {
            return $this->members->contains(
                fn (ProjectMember $m) => $m->user_id === $user->id && $m->left_at === null
            );
        }

        return $this->members()->where('user_id', $user->id)->whereNull('left_at')->exists();
    }

    /**
     * Derive completion percentage from milestones (completed ratio) when any
     * exist, otherwise fall back to the explicit progress_percent column.
     */
    public function computeProgress(): int
    {
        $milestones = $this->relationLoaded('milestones')
            ? $this->milestones
            : $this->milestones()->get();

        $total = $milestones->count();
        if ($total === 0) {
            return (int) $this->progress_percent;
        }

        $completed = $milestones->where('status', \App\Enums\MilestoneStatus::COMPLETED)->count();

        return (int) round($completed / $total * 100);
    }
}
