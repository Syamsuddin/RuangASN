<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $meeting_id
 * @property string $title
 * @property string|null $description
 * @property int|null $duration_minutes
 * @property string|null $presenter_id
 * @property int $sort_order
 * @property bool $is_completed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MeetingAgendaItem extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'meeting_id', 'title', 'description',
        'duration_minutes', 'presenter_id', 'sort_order', 'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function presenter(): BelongsTo { return $this->belongsTo(User::class, 'presenter_id'); }
}
