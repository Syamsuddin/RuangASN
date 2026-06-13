<?php
namespace App\Models;

use App\Enums\AiAgentType;
use App\Enums\AiModelProvider;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property AiAgentType $agent_type
 * @property string|null $title
 * @property string|null $context_type
 * @property string|null $context_id
 * @property int $total_tokens
 * @property AiModelProvider|null $model_provider
 * @property string|null $model_name
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiMessage> $messages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiInteractionLog> $interactionLogs
 *
 * @phpstan-consistent-constructor
 */
class AiConversation extends Model
{
    use BelongsToOrganization;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'user_id', 'agent_type', 'title',
        'context_type', 'context_id', 'total_tokens',
        'model_provider', 'model_name', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'agent_type'     => AiAgentType::class,
            'model_provider' => AiModelProvider::class,
            'total_tokens'   => 'integer',
            'archived_at'    => 'datetime',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function interactionLogs(): HasMany
    {
        return $this->hasMany(AiInteractionLog::class, 'conversation_id');
    }
}
