<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $task_id
 * @property string $user_id
 * @property string $content
 * @property string|null $parent_id
 * @property array|null $mentions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class TaskComment extends Model
{
    use HasUlid, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'task_id', 'user_id', 'content', 'parent_id', 'mentions'];
    protected $casts = ['mentions' => 'array', 'deleted_at' => 'datetime'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parent(): BelongsTo { return $this->belongsTo(TaskComment::class, 'parent_id'); }
}
