<?php

namespace App\Http\Controllers;

use App\Enums\ChatChannelType;
use App\Http\Resources\ChatChannelResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChatChannel::class);

        $user = $request->user();

        $channels = ChatChannel::query()
            ->where('is_archived', false)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->whereNull('left_at'))
            ->with(['members.user:id,name', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->get()
            ->sortByDesc(fn ($c) => optional($c->messages->first())->created_at ?? $c->created_at)
            ->values();

        $resourced = ChatChannelResource::collection($channels)->resolve($request);

        $dms      = collect($resourced)->where('channel_type', ChatChannelType::DM)->values();
        $groups   = collect($resourced)->where('channel_type', '!=', ChatChannelType::DM)->values();

        // Preselect a channel (?channel=) if the user is a member.
        $active = null;
        if ($request->filled('channel')) {
            $candidate = $channels->firstWhere('id', $request->string('channel')->toString());
            if ($candidate && $candidate->isMember($user)) {
                $active = $this->activeChannelPayload($candidate, $request);
            }
        }

        $users = User::where('organization_id', $user->organization_id)
            ->where('id', '!=', $user->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Chat/Index', [
            'dms'           => $dms,
            'groups'        => $groups,
            'activeChannel' => $active,
            'users'         => $users,
            'channelTypes'  => array_map(fn ($t) => $t->value, ChatChannelType::cases()),
        ]);
    }

    public function show(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        return response()->json(['data' => $this->activeChannelPayload($channel, $request)]);
    }

    public function messages(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $messages = $channel->messages()
            ->with('sender:id,name')
            ->withCount('replies')
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => ChatMessageResource::collection($messages->getCollection()->reverse()->values()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    public function storeChannel(Request $request): RedirectResponse
    {
        $this->authorize('create', ChatChannel::class);

        $typeValues = array_column(ChatChannelType::cases(), 'value');

        $data = $request->validate([
            'channel_type' => ['required', Rule::in($typeValues)],
            'name'         => ['required_unless:channel_type,dm', 'nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
            'team_id'      => ['nullable', 'exists:teams,id'],
            'project_id'   => ['nullable', 'string', 'max:26'],
            'meeting_id'   => ['nullable', 'exists:meetings,id'],
        ]);

        $channel = $this->chat->createChannel($data, $request->user());

        return redirect()->route('chat.index', ['channel' => $channel->id])
            ->with('success', 'Channel berhasil dibuat.');
    }

    public function startDm(Request $request): RedirectResponse
    {
        $this->authorize('create', ChatChannel::class);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $counterpart = User::where('id', $data['user_id'])
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $channel = $this->chat->findOrCreateDm($request->user(), $counterpart);

        return redirect()->route('chat.index', ['channel' => $channel->id]);
    }

    public function sendMessage(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorize('sendMessage', $channel);

        $data = $request->validate([
            'content'      => ['required_without:attachments', 'nullable', 'string', 'max:10000'],
            'content_type' => ['nullable', 'string', 'max:30'],
            'attachments'  => ['nullable', 'array'],
            'mentions'     => ['nullable', 'array'],
            'mentions.*'   => ['string'],
            'parent_id'    => ['nullable', 'exists:chat_messages,id'],
        ]);

        $message = $this->chat->sendMessage($channel, $request->user(), $data);
        $message->loadCount('replies');

        return response()->json([
            'data' => ChatMessageResource::make($message)->resolve($request),
        ], 201);
    }

    public function editMessage(Request $request, ChatMessage $message): JsonResponse
    {
        $this->assertMemberOfMessageChannel($request, $message);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $updated = $this->chat->editMessage($message, $request->user(), $data['content']);
        $updated->load('sender:id,name');

        return response()->json(['data' => ChatMessageResource::make($updated)->resolve($request)]);
    }

    public function deleteMessage(Request $request, ChatMessage $message): JsonResponse
    {
        // Own-delete needs membership; moderation delete (delete.any) does not
        // strictly require membership but we still scope to the same org. The
        // BelongsToOrganization global scope on ChatChannel hides cross-org
        // channels, so a find() miss means the parent channel is out of tenant.
        $channel = ChatChannel::find($message->channel_id);
        abort_if($channel === null, 404);

        $this->chat->deleteMessage($message, $request->user());

        return response()->json(['deleted' => true]);
    }

    public function markRead(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $this->chat->markRead($channel, $request->user());

        return response()->json(['unread_count' => 0]);
    }

    public function react(Request $request, ChatMessage $message): JsonResponse
    {
        $this->assertMemberOfMessageChannel($request, $message);

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $updated = $this->chat->react($message, $request->user(), $data['emoji']);
        $updated->load('sender:id,name');

        return response()->json(['data' => ChatMessageResource::make($updated)->resolve($request)]);
    }

    public function addMember(Request $request, ChatChannel $channel): RedirectResponse
    {
        $this->authorize('view', $channel);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['nullable', 'string', 'in:member,admin,owner'],
        ]);

        $this->chat->addMember($channel, $request->user(), $data['user_id'], $data['role'] ?? 'member');

        return back()->with('success', 'Anggota berhasil ditambahkan.');
    }

    public function removeMember(Request $request, ChatChannel $channel, ChatChannelMember $member): RedirectResponse
    {
        $this->authorize('view', $channel);

        abort_if($member->channel_id !== $channel->id, 404);

        $this->chat->removeMember($channel, $request->user(), $member);

        return back()->with('success', 'Anggota berhasil dikeluarkan.');
    }

    public function archive(Request $request, ChatChannel $channel): RedirectResponse
    {
        $this->authorize('archive', $channel);

        $this->chat->archiveChannel($channel, $request->user());

        return redirect()->route('chat.index')->with('success', 'Channel berhasil diarsipkan.');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function activeChannelPayload(ChatChannel $channel, Request $request): array
    {
        $channel->load(['members.user:id,name']);

        $messages = $channel->messages()
            ->with('sender:id,name')
            ->withCount('replies')
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        // Mark read on view.
        $this->chat->markRead($channel, $request->user());

        return [
            'channel'  => ChatChannelResource::make($channel)->resolve($request),
            'messages' => ChatMessageResource::collection($messages)->resolve($request),
        ];
    }

    private function assertMemberOfMessageChannel(Request $request, ChatMessage $message): void
    {
        $channel = ChatChannel::find($message->channel_id);
        abort_if($channel === null, 404);
        $this->authorize('view', $channel);
    }
}
