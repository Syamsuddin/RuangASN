<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $task_id
 * @property string $title
 * @property bool $is_done
 * @property string|null $done_by
 * @property \Illuminate\Support\Carbon|null $done_at
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class TaskChecklist extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id', 'task_id', 'title', 'is_done', 'done_by', 'done_at', 'sort_order'];
    protected $casts = ['is_done' => 'boolean', 'done_at' => 'datetime', 'created_at' => 'datetime'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
