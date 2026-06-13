<?php

namespace App\Models;

use App\Enums\CalendarType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property CalendarType $calendar_type
 * @property string|null $owner_id
 * @property string|null $team_id
 * @property string|null $project_id
 * @property string|null $meeting_id
 * @property string|null $task_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $start_at
 * @property Carbon|null $end_at
 * @property bool $all_day
 * @property bool $is_recurring
 * @property string|null $rrule
 * @property string|null $recurring_parent_id
 * @property string|null $color
 * @property bool $is_public
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class CalendarEvent extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id', 'organization_id', 'calendar_type', 'owner_id',
        'team_id', 'project_id', 'meeting_id', 'task_id',
        'title', 'description', 'location',
        'start_at', 'end_at', 'all_day', 'is_recurring',
        'rrule', 'recurring_parent_id', 'color', 'is_public',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'calendar_type' => CalendarType::class,
            'start_at'      => 'datetime',
            'end_at'        => 'datetime',
            'all_day'       => 'boolean',
            'is_recurring'  => 'boolean',
            'is_public'     => 'boolean',
            'deleted_at'    => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function recurringParent(): BelongsTo { return $this->belongsTo(CalendarEvent::class, 'recurring_parent_id'); }

    public function scopeInRange(Builder $query, string $start, string $end): Builder
    {
        return $query->where('start_at', '<=', $end)->where('end_at', '>=', $start);
    }
}
