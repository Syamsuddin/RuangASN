<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $meeting_id
 * @property string|null $content
 * @property string|null $ai_draft
 * @property string|null $status
 * @property string|null $data_classification
 * @property string|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MeetingMinute extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'meeting_minutes';

    protected $fillable = [
        'id', 'meeting_id', 'content', 'ai_draft', 'status',
        'data_classification', 'approved_by', 'approved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
