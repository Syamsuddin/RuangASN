<?php
namespace App\Models;

use App\Enums\AiAgentType;
use App\Enums\AiInteractionStatus;
use App\Enums\AiInteractionType;
use App\Enums\AiModelProvider;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string|null $conversation_id
 * @property string|null $message_id
 * @property AiAgentType $agent_type
 * @property AiInteractionType $interaction
 * @property string|null $intent
 * @property AiModelProvider|null $model_provider
 * @property string|null $model_name
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $latency_ms
 * @property AiInteractionStatus $status
 * @property string|null $error_message
 * @property array<string, mixed>|null $proposed_action
 * @property string|null $actor_ip
 * @property int $data_classification
 * @property Carbon|null $created_at
 * @property-read Organization $organization
 * @property-read User $user
 * @property-read AiConversation|null $conversation
 *
 * @phpstan-consistent-constructor
 */
class AiInteractionLog extends Model
{
    use BelongsToOrganization;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'organization_id', 'user_id', 'conversation_id', 'message_id',
        'agent_type', 'interaction', 'intent', 'model_provider', 'model_name',
        'prompt_tokens', 'completion_tokens', 'latency_ms', 'status',
        'error_message', 'proposed_action', 'actor_ip', 'data_classification',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'agent_type'          => AiAgentType::class,
            'interaction'         => AiInteractionType::class,
            'status'              => AiInteractionStatus::class,
            'model_provider'      => AiModelProvider::class,
            'proposed_action'     => 'array',
            'prompt_tokens'       => 'integer',
            'completion_tokens'   => 'integer',
            'latency_ms'          => 'integer',
            'data_classification' => 'integer',
            'created_at'          => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiInteractionLog $log) {
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
