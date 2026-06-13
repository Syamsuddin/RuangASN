<?php
namespace App\Services\Ai\Agents;

use App\Models\User;

/**
 * An agent that can produce a deterministic DRAFT (minutes / report body) as
 * the assistant content for human review. Drafts are returned as text and
 * NEVER auto-saved — the human always edits + saves (AXIOM-04).
 */
interface DraftingAgent extends AiAgent
{
    /**
     * Produce a draft from authorized context, or null if no context is
     * available (the orchestrator then falls back to the provider).
     *
     * @param array<string, mixed> $ctx
     */
    public function draft(User $user, string $content, array $ctx): ?string;
}
