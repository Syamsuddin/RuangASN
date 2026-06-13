<?php

namespace App\Models;

use App\Enums\MeetingMode;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $pemda_id
 * @property string|null $project_id
 * @property string|null $team_id
 * @property string $title
 * @property string|null $description
 * @property MeetingType $meeting_type
 * @property MeetingMode $meeting_mode
 * @property MeetingStatus $status
 * @property Carbon|null $scheduled_at
 * @property int|null $duration_minutes
 * @property Carbon|null $actual_start_at
 * @property Carbon|null $actual_end_at
 * @property string|null $location
 * @property string|null $online_url
 * @property string|null $host_id
 * @property string|null $secretary_id
 * @property string|null $agenda_notes
 * @property array|null $pre_read_docs
 * @property string|null $recording_path
 * @property string|null $transcript_path
 * @property string|null $data_classification
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Meeting extends BaseModel
{
    protected $fillable = [
        'id', 'organization_id', 'pemda_id', 'project_id', 'team_id',
        'title', 'description', 'meeting_type', 'meeting_mode', 'status',
        'scheduled_at', 'duration_minutes', 'actual_start_at', 'actual_end_at',
        'location', 'online_url', 'host_id', 'secretary_id',
        'agenda_notes', 'pre_read_docs', 'recording_path', 'transcript_path',
        'data_classification', 'created_by', 'updated_by', 'deleted_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'   => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at'   => 'datetime',
            'status'          => MeetingStatus::class,
            'meeting_type'    => MeetingType::class,
            'meeting_mode'    => MeetingMode::class,
            'pre_read_docs'   => 'array',
            'version'         => 'integer',
            'deleted_at'      => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function host(): BelongsTo { return $this->belongsTo(User::class, 'host_id'); }
    public function secretary(): BelongsTo { return $this->belongsTo(User::class, 'secretary_id'); }
    public function participants(): HasMany { return $this->hasMany(MeetingParticipant::class); }
    public function agendaItems(): HasMany { return $this->hasMany(MeetingAgendaItem::class)->orderBy('sort_order'); }
    public function decisions(): HasMany { return $this->hasMany(MeetingDecision::class); }
    public function actionItems(): HasMany { return $this->hasMany(MeetingActionItem::class); }
    public function minutes(): HasOne { return $this->hasOne(MeetingMinute::class); }

    public function canTransitionTo(MeetingStatus $new, User $user): bool
    {
        $allowed = match ($this->status) {
            MeetingStatus::DRAFT       => [MeetingStatus::SCHEDULED, MeetingStatus::CANCELLED],
            MeetingStatus::SCHEDULED   => [MeetingStatus::CONFIRMED, MeetingStatus::IN_PROGRESS, MeetingStatus::POSTPONED, MeetingStatus::CANCELLED],
            MeetingStatus::CONFIRMED   => [MeetingStatus::IN_PROGRESS, MeetingStatus::POSTPONED, MeetingStatus::CANCELLED],
            MeetingStatus::IN_PROGRESS => [MeetingStatus::COMPLETED],
            MeetingStatus::COMPLETED   => [MeetingStatus::ARCHIVED],
            MeetingStatus::POSTPONED   => [MeetingStatus::SCHEDULED, MeetingStatus::CANCELLED, MeetingStatus::ARCHIVED],
            MeetingStatus::CANCELLED   => [MeetingStatus::ARCHIVED],
            MeetingStatus::ARCHIVED    => [],
        };
        return in_array($new, $allowed);
    }
}
