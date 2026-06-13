<?php
namespace App\Services\Ai;

use App\Enums\AiAgentType;
use App\Enums\AiInteractionStatus;
use App\Enums\AiInteractionType;
use App\Models\AiInteractionLog;
use App\Models\User;

/**
 * Writes ai_interaction_logs rows (AXIOM-04 & AXIOM-06: every AI query,
 * routing, response, and action request/confirm/reject is traced).
 */
class AiInteractionLogger
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function log(
        User $user,
        AiInteractionType $interaction,
        AiAgentType $agentType,
        array $attributes = [],
    ): AiInteractionLog {
        $status = $attributes['status'] ?? AiInteractionStatus::COMPLETED;

        return AiInteractionLog::create([
            'organization_id'    => $user->organization_id,
            'user_id'            => $user->id,
            'conversation_id'    => $attributes['conversation_id'] ?? null,
            'message_id'         => $attributes['message_id'] ?? null,
            'agent_type'         => $agentType->value,
            'interaction'        => $interaction->value,
            'intent'             => $attributes['intent'] ?? null,
            'model_provider'     => $attributes['model_provider'] ?? null,
            'model_name'         => $attributes['model_name'] ?? null,
            'prompt_tokens'      => $attributes['prompt_tokens'] ?? null,
            'completion_tokens'  => $attributes['completion_tokens'] ?? null,
            'latency_ms'         => $attributes['latency_ms'] ?? null,
            'status'             => $status instanceof AiInteractionStatus ? $status->value : (string) $status,
            'error_message'      => $attributes['error_message'] ?? null,
            'proposed_action'    => $attributes['proposed_action'] ?? null,
            'actor_ip'           => $attributes['actor_ip'] ?? request()->ip(),
            'data_classification'=> $attributes['data_classification'] ?? 3,
        ]);
    }
}
