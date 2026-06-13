<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $role
 * @property string $content
 * @property int|null $tokens_used
 * @property string|null $model_name
 * @property string|null $finish_reason
 * @property array<int, array<string, mixed>>|null $citations
 * @property int $data_classification
 * @property array<int, array<string, mixed>>|null $proposed_actions
 * @property bool|null $action_confirmed
 * @property Carbon|null $confirmed_at
 * @property string|null $confirmed_by
 * @property Carbon|null $created_at
 * @property-read AiConversation|null $conversation
 * @property-read User|null $confirmedBy
 */
class AiMessage extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'conversation_id', 'role', 'content',
        'tokens_used', 'model_name', 'finish_reason', 'citations',
        'data_classification', 'proposed_actions',
        'action_confirmed', 'confirmed_at', 'confirmed_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'citations'        => 'array',
            'proposed_actions' => 'array',
            'action_confirmed' => 'boolean',
            'confirmed_at'     => 'datetime',
            'tokens_used'      => 'integer',
            'data_classification' => 'integer',
            'created_at'       => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiMessage $message) {
            if (empty($message->created_at)) {
                $message->created_at = now();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function hasPendingActions(): bool
    {
        return $this->action_confirmed === null && ! empty($this->proposed_actions);
    }

    /** @return array<int, array<string, mixed>> */
    public function pendingActions(): array
    {
        return $this->hasPendingActions() ? ($this->proposed_actions ?? []) : [];
    }
}
