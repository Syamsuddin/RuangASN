<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Not a BaseModel — there is no organization_id column. Tenant isolation is
 * enforced via the parent channel's organization + membership checks.
 *
 * @property string $id
 * @property string $channel_id
 * @property string $sender_id
 * @property string|null $parent_id
 * @property string $content
 * @property string $content_type
 * @property array|null $attachments
 * @property array|null $mentions
 * @property array|null $reactions
 * @property int $data_classification
 * @property bool $is_pinned
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ChatChannel $channel
 * @property-read User $sender
 * @property-read ChatMessage|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChatMessage> $replies
 */
class ChatMessage extends Model
{
    use HasUlid;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'channel_id', 'sender_id', 'parent_id', 'content', 'content_type',
        'attachments', 'mentions', 'reactions', 'data_classification', 'is_pinned', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments'         => 'array',
            'mentions'            => 'array',
            'reactions'           => 'array',
            'data_classification' => 'integer',
            'is_pinned'           => 'boolean',
            'edited_at'           => 'datetime',
            'deleted_at'          => 'datetime',
        ];
    }

    public function channel(): BelongsTo { return $this->belongsTo(ChatChannel::class, 'channel_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(ChatMessage::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(ChatMessage::class, 'parent_id'); }
}
