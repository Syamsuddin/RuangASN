<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $meeting_id
 * @property string|null $agenda_item_id
 * @property string $content
 * @property string|null $recorded_by
 * @property \Illuminate\Support\Carbon|null $recorded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MeetingDecision extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'meeting_id', 'agenda_item_id', 'content', 'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function agendaItem(): BelongsTo { return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id'); }
}
