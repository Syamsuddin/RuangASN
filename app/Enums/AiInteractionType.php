<?php
namespace App\Enums;

/**
 * Nilai kolom ai_interaction_logs.interaction (AXIOM-04 & AXIOM-06).
 */
enum AiInteractionType: string
{
    case QUERY_RECEIVED     = 'query_received';
    case AGENT_ROUTED       = 'agent_routed';
    case RESPONSE_GENERATED = 'response_generated';
    case ACTION_REQUESTED   = 'action_requested';
    case ACTION_CONFIRMED   = 'action_confirmed';
    case ACTION_REJECTED    = 'action_rejected';
}
