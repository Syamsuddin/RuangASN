<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $meeting_id
 * @property string|null $decision_id
 * @property string|null $task_id
 * @property string $title
 * @property string|null $description
 * @property string|null $assignee_id
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property bool $is_task_created
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MeetingActionItem extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'meeting_id', 'decision_id', 'task_id', 'title', 'description',
        'assignee_id', 'due_date', 'is_task_created', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date'        => 'date',
            'is_task_created' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function decision(): BelongsTo { return $this->belongsTo(MeetingDecision::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
