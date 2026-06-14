<?php

namespace App\Models;

use App\Enums\ChatChannelType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property ChatChannelType $channel_type
 * @property string|null $name
 * @property string|null $description
 * @property bool $is_archived
 * @property string|null $team_id
 * @property string|null $project_id
 * @property string|null $meeting_id
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChatChannelMember> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChatMessage> $messages
 */
class ChatChannel extends BaseModel
{
    protected $fillable = [
        'id', 'organization_id', 'channel_type', 'name', 'description',
        'is_archived', 'team_id', 'project_id', 'meeting_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => ChatChannelType::class,
            'is_archived'  => 'boolean',
            'deleted_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function members(): HasMany { return $this->hasMany(ChatChannelMember::class, 'channel_id'); }
    public function messages(): HasMany { return $this->hasMany(ChatMessage::class, 'channel_id'); }

    /** A user is an active member if they have a membership row and have not left. */
    public function isMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }

    public function memberRecord(User $user): ?ChatChannelMember
    {
        /** @var ChatChannelMember|null $member */
        $member = $this->members()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        return $member;
    }

    /** Number of messages created after the user's last_read_at (0 if all read / never a member). */
    public function unreadCountFor(User $user): int
    {
        $member = $this->memberRecord($user);
        if (! $member) {
            return 0;
        }

        $query = $this->messages()->where('sender_id', '!=', $user->id);
        if ($member->last_read_at) {
            $query->where('created_at', '>', $member->last_read_at);
        }

        return $query->count();
    }
}
