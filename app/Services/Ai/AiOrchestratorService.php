<?php
namespace App\Services\Ai;

use App\Enums\AiAgentType;
use App\Enums\AiInteractionStatus;
use App\Enums\AiInteractionType;
use App\Enums\AiIntent;
use App\Enums\AuditAction;
use App\Enums\ProposedActionType;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\Agents\AgentRegistry;
use App\Services\Ai\Agents\AiAgent;
use App\Services\Ai\Agents\DraftingAgent;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Core AI orchestrator (AXIOM-04 & AXIOM-06).
 *
 * sendMessage() classifies intent, routes to an agent, calls the provider
 * (with RAG context for knowledge-style agents), persists user + assistant
 * messages, and traces every step to ai_interaction_logs. Any proposed
 * actions are STORED ONLY — never executed here.
 *
 * confirmAction() is the ONLY execution path: it runs the chosen proposed
 * action through the confirming user's own policies (no escalation).
 */
class AiOrchestratorService
{
    public function __construct(
        private IntentClassifier $classifier,
        private AiProviderManager $providers,
        private RetrievalService $retrieval,
        private AiInteractionLogger $logger,
        private ProposedActionExecutor $executor,
        private AuditService $audit,
        private AgentRegistry $agents,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function sendMessage(
        User $user,
        ?AiConversation $conversation,
        string $content,
        array $context = [],
    ): AiMessage {
        $classification = $this->classifier->classify($content);
        /** @var AiIntent $intent */
        $intent = $classification['intent'];
        /** @var AiAgentType $agent */
        $agent = $classification['agent'];

        // 1. Ensure conversation (create if null). Outside provider call so it
        //    persists even if generation later fails.
        $conversation = DB::transaction(function () use ($user, $conversation, $content, $agent, $context) {
            if ($conversation === null) {
                $conversation = AiConversation::create([
                    'organization_id' => $user->organization_id,
                    'user_id'         => $user->id,
                    'agent_type'      => $agent->value,
                    'title'           => Str::limit(trim($content), 80, '...') ?: 'Percakapan baru',
                    'context_type'    => $context['context_type'] ?? null,
                    'context_id'      => $context['context_id'] ?? null,
                ]);
            }

            return $conversation;
        });

        // 2. Log QUERY_RECEIVED.
        $this->logger->log($user, AiInteractionType::QUERY_RECEIVED, $agent, [
            'conversation_id' => $conversation->id,
            'intent'          => $intent->value,
            'status'          => AiInteractionStatus::PROCESSING,
        ]);

        // 3. Log AGENT_ROUTED (intent + agent).
        $this->logger->log($user, AiInteractionType::AGENT_ROUTED, $agent, [
            'conversation_id' => $conversation->id,
            'intent'          => $intent->value,
        ]);

        // 4. Persist the user message.
        $userMessage = AiMessage::create([
            'conversation_id'     => $conversation->id,
            'role'                => 'user',
            'content'             => $content,
            'data_classification' => 3,
        ]);

        // 5. Resolve the specialised agent and build provider messages:
        //    agent system prompt + agent context + RAG context + history.
        $agentImpl = $this->agents->for($agent);
        $citations = $this->ragCitations($user, $agent, $content);
        $messages  = $this->buildMessages($conversation, $agentImpl, $user, $context, $citations);

        // 5b. Drafting agents (Meeting/Report) produce a deterministic draft as
        //     the assistant content for human review (never auto-saved).
        $agentDraft = $agentImpl instanceof DraftingAgent
            ? $agentImpl->draft($user, $content, $context)
            : null;

        // 6. Call provider with fallback; capture provider + tokens + latency.
        $startedAt = microtime(true);
        try {
            $outcome  = $this->providers->chatWithFallback($messages, ['intent' => $intent]);
            $result   = $outcome['result'];
            $provider = $outcome['provider'];
            $latency  = (int) round((microtime(true) - $startedAt) * 1000);

            // 7. Proposed actions: prefer the agent's structured proposals, fall
            //    back to the provider's. Either way they are STORED ONLY.
            $rawActions      = $agentImpl->proposeActions($user, $content, $context) ?: $result->proposedActions;
            $proposedActions = $this->mapProposedActions($rawActions);
            $allCitations    = array_merge($citations, $result->citations);
            $assistantBody   = $agentDraft ?? $result->content;

            // 7. Persist assistant message.
            $assistant = AiMessage::create([
                'conversation_id'     => $conversation->id,
                'role'                => 'assistant',
                'content'             => '[AI Generated] ' . $assistantBody,
                'tokens_used'         => $result->totalTokens(),
                'model_name'          => $result->modelName,
                'finish_reason'       => $result->finishReason,
                'citations'           => $allCitations,
                'proposed_actions'    => $proposedActions !== [] ? $proposedActions : null,
                'action_confirmed'    => null, // NEVER executed until confirmAction
                'data_classification' => 3,
            ]);

            // 8. Log RESPONSE_GENERATED.
            $this->logger->log($user, AiInteractionType::RESPONSE_GENERATED, $agent, [
                'conversation_id'   => $conversation->id,
                'message_id'        => $assistant->id,
                'intent'            => $intent->value,
                'model_provider'    => $this->providerEnumValue($provider),
                'model_name'        => $result->modelName,
                'prompt_tokens'     => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
                'latency_ms'        => $latency,
                'status'            => AiInteractionStatus::COMPLETED,
            ]);

            // 8b. If a proposed action exists, also log ACTION_REQUESTED.
            if ($proposedActions !== []) {
                $this->logger->log($user, AiInteractionType::ACTION_REQUESTED, $agent, [
                    'conversation_id' => $conversation->id,
                    'message_id'      => $assistant->id,
                    'intent'          => $intent->value,
                    'proposed_action' => $proposedActions[0],
                ]);
            }

            // 9. Update conversation metadata.
            $conversation->update([
                'total_tokens'   => $conversation->total_tokens + $result->totalTokens(),
                'model_provider' => $this->providerEnumValue($provider),
                'model_name'     => $result->modelName,
            ]);

            return $assistant;
        } catch (Throwable $e) {
            // Trace the failure (AXIOM-06) then rethrow.
            $this->logger->log($user, AiInteractionType::RESPONSE_GENERATED, $agent, [
                'conversation_id' => $conversation->id,
                'message_id'      => $userMessage->id,
                'intent'          => $intent->value,
                'status'          => AiInteractionStatus::FAILED,
                'error_message'   => $e->getMessage(),
                'latency_ms'      => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        }
    }

    /**
     * The ONLY execution path for AI-proposed actions (AXIOM-04). Runs the
     * action through the confirming user's policies; on missing permission an
     * AuthorizationException (403) is thrown and nothing is created.
     *
     * @return array{type: string, entity: string, id: string, title: string}
     */
    public function confirmAction(AiMessage $message, int $actionIndex, User $user): array
    {
        $actions = $message->proposed_actions ?? [];
        if (! isset($actions[$actionIndex])) {
            throw ValidationException::withMessages([
                'action_index' => 'Aksi yang diusulkan tidak ditemukan.',
            ]);
        }

        // AXIOM-04: a proposal may only be acted on ONCE. Block re-confirmation
        // (or confirmation after a rejection) so a double-submit/retry/replay
        // can never materialize duplicate domain records.
        if ($message->action_confirmed !== null) {
            throw ValidationException::withMessages([
                'action_index' => 'Aksi ini sudah diputuskan sebelumnya.',
            ]);
        }

        $action       = $actions[$actionIndex];
        $conversation = $message->conversation;
        if ($conversation === null) {
            throw ValidationException::withMessages([
                'message' => 'Percakapan tidak ditemukan.',
            ]);
        }
        $agent = $conversation->agent_type;

        try {
            $reference = DB::transaction(function () use ($action, $message, $user) {
                // Atomic claim: only the first confirm (action_confirmed still NULL)
                // proceeds. Concurrent double-submits race here — losers get 0 rows
                // and abort before any entity is created. The claim is inside the
                // transaction, so an authorization failure below rolls it back and
                // the proposal can be retried.
                $claimed = AiMessage::query()
                    ->whereKey($message->id)
                    ->whereNull('action_confirmed')
                    ->update([
                        'action_confirmed' => true,
                        'confirmed_by'     => $user->id,
                        'confirmed_at'     => now(),
                    ]);

                if ($claimed === 0) {
                    throw ValidationException::withMessages([
                        'action_index' => 'Aksi ini sudah diputuskan sebelumnya.',
                    ]);
                }

                // Authorizes via the user's own policy inside the executor.
                $reference = $this->executor->execute($action, $user);

                $message->refresh();

                $this->audit->log(
                    AuditAction::AI_ACTION_CONFIRMED,
                    $reference['entity'],
                    $reference['id'],
                    [],
                    ['action' => $action, 'message_id' => $message->id],
                );

                return $reference;
            });
        } catch (AuthorizationException $e) {
            // No escalation: log the (failed) confirmation attempt then rethrow.
            $this->logger->log($user, AiInteractionType::ACTION_CONFIRMED, $agent, [
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
                'status'          => AiInteractionStatus::FAILED,
                'error_message'   => $e->getMessage(),
                'proposed_action' => $action,
            ]);

            throw $e;
        }

        $this->logger->log($user, AiInteractionType::ACTION_CONFIRMED, $agent, [
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'status'          => AiInteractionStatus::COMPLETED,
            'proposed_action' => $action,
        ]);

        return $reference;
    }

    public function rejectAction(AiMessage $message, int $actionIndex, User $user): AiMessage
    {
        $actions = $message->proposed_actions ?? [];
        if (! isset($actions[$actionIndex])) {
            throw ValidationException::withMessages([
                'action_index' => 'Aksi yang diusulkan tidak ditemukan.',
            ]);
        }

        // Cannot reject an already-decided proposal (a confirmed action has
        // already created its entity; a re-decision would desync the record).
        if ($message->action_confirmed !== null) {
            throw ValidationException::withMessages([
                'action_index' => 'Aksi ini sudah diputuskan sebelumnya.',
            ]);
        }

        $conversation = $message->conversation;
        if ($conversation === null) {
            throw ValidationException::withMessages([
                'message' => 'Percakapan tidak ditemukan.',
            ]);
        }
        $agent = $conversation->agent_type;

        $message->update([
            'action_confirmed' => false,
            'confirmed_by'     => $user->id,
            'confirmed_at'     => now(),
        ]);

        $this->logger->log($user, AiInteractionType::ACTION_REJECTED, $agent, [
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'status'          => AiInteractionStatus::CANCELLED,
            'proposed_action' => $actions[$actionIndex],
        ]);

        return $message->fresh();
    }

    /**
     * @return array<int, array{source_type: string, source_id: string, title: string, excerpt: string}>
     */
    private function ragCitations(User $user, AiAgentType $agent, string $query): array
    {
        // Inject RAG context for knowledge-style agents.
        $ragAgents = [AiAgentType::KNOWLEDGE, AiAgentType::DOCUMENT, AiAgentType::GENERAL];
        if (! in_array($agent, $ragAgents, true)) {
            return [];
        }

        $k = (int) config('ai.retrieval.top_k', 5);

        return $this->retrieval->retrieve($user, $query, $k);
    }

    /**
     * Produce a deterministic DRAFT (minutes/report) from a drafting agent
     * outside the chat flow, for the Meeting/Report controllers to store in
     * their own *.ai_draft column for human review. Entity loads inside the
     * agent authorize via the user's own policies (no escalation).
     *
     * @param array<string, mixed> $context
     */
    public function generateDraft(AiAgentType $agent, User $user, string $content, array $context): ?string
    {
        $impl = $this->agents->for($agent);
        if (! $impl instanceof DraftingAgent) {
            return null;
        }

        return $impl->draft($user, $content, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $citations
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(
        AiConversation $conversation,
        AiAgent $agent,
        User $user,
        array $context,
        array $citations,
    ): array {
        $messages = [['role' => 'system', 'content' => $agent->systemPrompt()]];

        // Agent-specific context (authorized entity loads).
        foreach ($agent->buildContext($user, $context) as $ctxMessage) {
            $messages[] = $ctxMessage;
        }

        if ($citations !== []) {
            $contextLines = array_map(
                static fn ($c) => "- [{$c['source_type']}] {$c['title']}: {$c['excerpt']}",
                $citations
            );
            $messages[] = [
                'role'    => 'system',
                'content' => "Konteks dari basis pengetahuan organisasi (gunakan untuk menjawab):\n" . implode("\n", $contextLines),
            ];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, AiMessage> $history */
        $history = $conversation->messages()->orderBy('created_at')->get();
        foreach ($history as $m) {
            if (! in_array($m->role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        return $messages;
    }

    /**
     * Normalise provider proposed actions into stored ProposedActionType
     * payloads. Unknown types are dropped (defence in depth).
     *
     * @param array<int, array<string, mixed>> $raw
     * @return array<int, array{type: string, payload: array<string, mixed>}>
     */
    private function mapProposedActions(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $action) {
            $type = ProposedActionType::tryFrom((string) ($action['type'] ?? ''));
            if ($type === null) {
                continue;
            }
            $mapped[] = [
                'type'    => $type->value,
                'payload' => (array) ($action['payload'] ?? []),
            ];
        }

        return $mapped;
    }

    private function providerEnumValue(string $provider): ?string
    {
        // 'fake' is not an AiModelProvider enum value; store null for it.
        return \App\Enums\AiModelProvider::tryFrom($provider)?->value;
    }
}
