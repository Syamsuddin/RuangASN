<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatusHistory extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id', 'task_id', 'from_status', 'to_status', 'changed_by', 'reason', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
