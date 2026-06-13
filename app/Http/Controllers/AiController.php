<?php
namespace App\Http\Controllers;

use App\Http\Resources\AiConversationResource;
use App\Http\Resources\AiMessageResource;
use App\Models\AiConversation;
use App\Models\AiMemory;
use App\Models\AiMessage;
use App\Services\Ai\AiOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiController extends Controller
{
    public function __construct(private AiOrchestratorService $orchestrator) {}

    /** Minimal Inertia landing page (full chat UI ships in the next pass). */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('ai.query'), 403);

        $conversations = AiConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return Inertia::render('Ai/Index', [
            'conversations' => AiConversationResource::collection($conversations),
        ]);
    }

    /** Own conversations only (ai.conversation.view.own). */
    public function conversations(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ai.query'), 403);

        $conversations = AiConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => AiConversationResource::collection($conversations),
        ]);
    }

    public function show(AiConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load('messages');

        return response()->json([
            'data' => new AiConversationResource($conversation),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ai.query'), 403);

        $data = $request->validate([
            'content'         => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'size:26'],
            'context'         => ['nullable', 'array'],
        ]);

        $conversation = null;
        if (! empty($data['conversation_id'])) {
            // Org-scoped lookup; only the owner may continue a conversation.
            $conversation = AiConversation::findOrFail($data['conversation_id']);
            $this->authorize('view', $conversation);
        }

        $message = $this->orchestrator->sendMessage(
            $request->user(),
            $conversation,
            $data['content'],
            $data['context'] ?? [],
        );

        return response()->json([
            'data' => new AiMessageResource($message),
        ], 201);
    }

    public function confirmAction(Request $request, AiMessage $message): JsonResponse
    {
        abort_unless($request->user()?->can('ai.query'), 403);
        $this->authorizeMessageOwner($message);

        $data = $request->validate([
            'action_index' => ['required', 'integer', 'min:0'],
        ]);

        $reference = $this->orchestrator->confirmAction(
            $message,
            (int) $data['action_index'],
            $request->user(),
        );

        return response()->json([
            'data'    => new AiMessageResource($message->fresh()),
            'created' => $reference,
        ]);
    }

    public function rejectAction(Request $request, AiMessage $message): JsonResponse
    {
        abort_unless($request->user()?->can('ai.query'), 403);
        $this->authorizeMessageOwner($message);

        $data = $request->validate([
            'action_index' => ['required', 'integer', 'min:0'],
        ]);

        $updated = $this->orchestrator->rejectAction(
            $message,
            (int) $data['action_index'],
            $request->user(),
        );

        return response()->json([
            'data' => new AiMessageResource($updated),
        ]);
    }

    /** Inertia page listing the current user's AI memories (delete-only). */
    public function memories(Request $request): Response
    {
        abort_unless($request->user()?->can('ai.query'), 403);

        $memories = AiMemory::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AiMemory $m) => [
                'id'          => $m->id,
                'memory_type' => $m->memory_type,
                'scope'       => $m->scope,
                'content'     => $m->content,
                'source_type' => $m->source_type,
                'expires_at'  => $m->expires_at?->toISOString(),
                'created_at'  => $m->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Ai/Memories', [
            'memories' => $memories,
        ]);
    }

    public function updateMemory(Request $request, AiMemory $memory): RedirectResponse
    {
        abort_unless($request->user()?->can('ai.query'), 403);
        abort_unless($memory->user_id === $request->user()->id, 403);

        $action = $request->validate([
            'action' => ['required', 'in:delete'],
        ]);

        if ($action['action'] === 'delete') {
            $memory->delete();
        }

        return back()->with('success', 'Memori AI diperbarui.');
    }

    /**
     * Conversation ownership gate for message-scoped actions. Resolves the
     * conversation via the org-scoped relation so cross-tenant access 403s
     * (no leak through the message route binding).
     */
    private function authorizeMessageOwner(AiMessage $message): void
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        $this->authorize('view', $conversation);
    }
}
