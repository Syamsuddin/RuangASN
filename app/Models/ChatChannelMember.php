<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $channel_id
 * @property string $user_id
 * @property string $role
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $last_read_at
 * @property-read ChatChannel $channel
 * @property-read User $user
 */
class ChatChannelMember extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'channel_id', 'user_id', 'role', 'joined_at', 'left_at', 'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at'    => 'datetime',
            'left_at'      => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo { return $this->belongsTo(ChatChannel::class, 'channel_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
