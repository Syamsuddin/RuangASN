<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;
use App\Models\User;

/**
 * Common contract for the specialised agents the orchestrator routes to.
 *
 * An agent is responsible for THREE things, all derived deterministically
 * from the active user's own (authorized) data — never escalating beyond
 * the user's permissions (AXIOM-04 + AXIOM-08):
 *
 *  - systemPrompt(): the role framing prepended to every provider call.
 *  - buildContext(): extra system messages assembled from authorized entity
 *    loads (e.g. the meeting's agenda/decisions, the report's data sources).
 *  - proposeActions(): structured proposed_actions the agent suggests (stored
 *    only; never executed). Most agents return [] and rely on the provider.
 *
 * Drafting agents (Meeting / Report) additionally implement DraftingAgent to
 * return a deterministic draft as the assistant content for human review.
 */
interface AiAgent
{
    public function type(): AiAgentType;

    public function systemPrompt(): string;

    /**
     * Assemble agent-specific context as provider system messages. Every entity
     * load MUST authorize through the user's own policies.
     *
     * @param array<string, mixed> $ctx  e.g. {context_type, context_id, ...}
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(User $user, array $ctx): array;

    /**
     * Structured actions this agent proposes from the user request (stored,
     * never executed). Default agents return [] and defer to the provider.
     *
     * @param array<string, mixed> $ctx
     * @return array<int, array{type: string, payload: array<string, mixed>}>
     */
    public function proposeActions(User $user, string $content, array $ctx): array;
}
