<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ChatChannelType;
use App\Enums\NotificationType;
use App\Events\ChatMessageSent;
use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    /**
     * Create a channel of any type. The creator becomes an `owner` member.
     * - DM: also adds the counterpart (`counterpart_id`).
     * - group/team/project/meeting/announcement: attaches provided `member_ids`.
     */
    public function createChannel(array $data, User $creator): ChatChannel
    {
        return DB::transaction(function () use ($data, $creator) {
            $type = $data['channel_type'] instanceof ChatChannelType
                ? $data['channel_type']
                : ChatChannelType::from($data['channel_type']);

            $channel = ChatChannel::create([
                'organization_id' => $creator->organization_id,
                'channel_type'    => $type->value,
                'name'            => $data['name'] ?? null,
                'description'     => $data['description'] ?? null,
                'team_id'         => $data['team_id'] ?? null,
                'project_id'      => $data['project_id'] ?? null,
                'meeting_id'      => $data['meeting_id'] ?? null,
                'created_by'      => $creator->id,
                'is_archived'     => false,
            ]);

            $this->joinMember($channel, $creator, 'owner');

            if ($type === ChatChannelType::DM && ! empty($data['counterpart_id'])) {
                $counterpart = $this->resolveOrgUser($creator, $data['counterpart_id']);
                if ($counterpart) {
                    $this->joinMember($channel, $counterpart, 'member');
                }
            } else {
                foreach ($data['member_ids'] ?? [] as $memberId) {
                    if ($memberId === $creator->id) {
                        continue;
                    }
                    $member = $this->resolveOrgUser($creator, $memberId);
                    if ($member) {
                        $this->joinMember($channel, $member, 'member');
                    }
                }
            }

            $this->outbox->publish('chat.channel.created', [
                'channel_id'      => $channel->id,
                'channel_type'    => $type->value,
                'organization_id' => $channel->organization_id,
                'created_by'      => $creator->id,
            ], 'ChatChannel', $channel->id);

            $this->audit->log(AuditAction::CREATED, 'ChatChannel', $channel->id, [], [
                'channel_type' => $type->value,
                'name'         => $channel->name,
            ]);

            return $channel->fresh();
        });
    }

    /**
     * Idempotent DM channel between two users in the same org. Dedupes on the
     * exact membership pair so a second call returns the existing channel.
     */
    public function findOrCreateDm(User $a, User $b): ChatChannel
    {
        if ($a->organization_id !== $b->organization_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Tidak dapat memulai DM dengan pengguna dari organisasi lain.',
            ]);
        }

        $existing = ChatChannel::query()
            ->where('channel_type', ChatChannelType::DM->value)
            ->whereHas('members', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $b->id))
            ->withCount('members')
            ->get()
            ->firstWhere('members_count', 2);

        if ($existing) {
            return $existing;
        }

        return $this->createChannel([
            'channel_type'   => ChatChannelType::DM,
            'counterpart_id' => $b->id,
        ], $a);
    }

    /**
     * Send a message in a channel. The sender must be an active member.
     * Broadcasts ChatMessageSent and notifies @mentioned users in-app.
     */
    public function sendMessage(ChatChannel $channel, User $sender, array $data): ChatMessage
    {
        if (! $channel->isMember($sender)) {
            throw ValidationException::withMessages([
                'channel' => 'Anda bukan anggota channel ini.',
            ]);
        }

        return DB::transaction(function () use ($channel, $sender, $data) {
            $message = ChatMessage::create([
                'channel_id'   => $channel->id,
                'sender_id'    => $sender->id,
                'parent_id'    => $data['parent_id'] ?? null,
                'content'      => $data['content'] ?? '',
                'content_type' => $data['content_type'] ?? 'text',
                'attachments'  => $data['attachments'] ?? null,
                'mentions'     => $data['mentions'] ?? null,
                'data_classification' => $data['data_classification'] ?? 3,
            ]);

            $fresh = $message->fresh();
            $fresh->setRelation('sender', $sender);

            broadcast(new ChatMessageSent($fresh))->toOthers();

            $this->notifyMentions($channel, $sender, $fresh);

            $this->outbox->publish('chat.message.sent', [
                'channel_id'      => $channel->id,
                'message_id'      => $message->id,
                'sender_id'       => $sender->id,
                'organization_id' => $channel->organization_id,
            ], 'ChatMessage', $message->id);

            return $fresh;
        });
    }

    public function editMessage(ChatMessage $message, User $user, string $content): ChatMessage
    {
        if ($message->sender_id !== $user->id) {
            throw ValidationException::withMessages([
                'content' => 'Hanya pengirim yang dapat menyunting pesan ini.',
            ]);
        }

        return DB::transaction(function () use ($message, $content) {
            $message->update([
                'content'   => $content,
                'edited_at' => now(),
            ]);

            return $message->fresh();
        });
    }

    /**
     * Soft-delete a message. The sender (chat.message.delete.own) or a
     * moderator (chat.message.delete.any) may delete.
     */
    public function deleteMessage(ChatMessage $message, User $user): void
    {
        $isOwner    = $message->sender_id === $user->id;
        $canDeleteOwn = $isOwner && $user->hasPermissionTo('chat.message.delete.own');
        $canDeleteAny = $user->hasPermissionTo('chat.message.delete.any');

        if (! $canDeleteOwn && ! $canDeleteAny) {
            throw ValidationException::withMessages([
                'message' => 'Anda tidak memiliki izin menghapus pesan ini.',
            ]);
        }

        DB::transaction(function () use ($message, $user) {
            $message->delete();
            $this->audit->log(AuditAction::DELETED, 'ChatMessage', $message->id, [], [
                'deleted_by' => $user->id,
            ]);
        });
    }

    public function addMember(ChatChannel $channel, User $actor, string $userId, string $role = 'member'): ?ChatChannelMember
    {
        $this->assertManager($channel, $actor);

        $user = $this->resolveOrgUser($actor, $userId);
        if (! $user) {
            return null;
        }

        return DB::transaction(fn () => $this->joinMember($channel, $user, $role));
    }

    public function removeMember(ChatChannel $channel, User $actor, ChatChannelMember $member): void
    {
        $this->assertManager($channel, $actor);

        DB::transaction(function () use ($member) {
            $member->update(['left_at' => now()]);
        });
    }

    public function markRead(ChatChannel $channel, User $user): void
    {
        $member = $channel->memberRecord($user);
        if (! $member) {
            return;
        }

        $member->update(['last_read_at' => now()]);
    }

    /** Toggle the user's reaction with a given emoji in the reactions json. */
    public function react(ChatMessage $message, User $user, string $emoji): ChatMessage
    {
        return DB::transaction(function () use ($message, $user, $emoji) {
            $reactions = $message->reactions ?? [];
            $users = $reactions[$emoji] ?? [];

            if (in_array($user->id, $users, true)) {
                $users = array_values(array_filter($users, fn ($id) => $id !== $user->id));
            } else {
                $users[] = $user->id;
            }

            if (empty($users)) {
                unset($reactions[$emoji]);
            } else {
                $reactions[$emoji] = array_values($users);
            }

            $message->update(['reactions' => $reactions ?: null]);

            return $message->fresh();
        });
    }

    public function archiveChannel(ChatChannel $channel, User $actor): ChatChannel
    {
        return DB::transaction(function () use ($channel) {
            $channel->update(['is_archived' => true]);

            $this->audit->log(AuditAction::STATUS_CHANGED, 'ChatChannel', $channel->id,
                ['is_archived' => false],
                ['is_archived' => true],
            );

            return $channel->fresh();
        });
    }

    // ── internals ──────────────────────────────────────────────────────────

    /** Add (or re-activate) a membership row, keeping the (channel,user) UNIQUE constraint intact. */
    private function joinMember(ChatChannel $channel, User $user, string $role): ChatChannelMember
    {
        /** @var ChatChannelMember $member */
        $member = ChatChannelMember::firstOrNew([
            'channel_id' => $channel->id,
            'user_id'    => $user->id,
        ]);

        $member->role     = $role;
        $member->left_at  = null;
        if (! $member->exists) {
            $member->joined_at = now();
        }
        $member->save();

        return $member;
    }

    private function notifyMentions(ChatChannel $channel, User $sender, ChatMessage $message): void
    {
        foreach ($message->mentions ?? [] as $mentionedId) {
            if ($mentionedId === $sender->id) {
                continue;
            }
            $user = $this->resolveOrgUser($sender, $mentionedId);
            if (! $user || ! $channel->isMember($user)) {
                continue;
            }

            $preview = mb_strlen($message->content) > 80
                ? mb_substr($message->content, 0, 80) . '…'
                : $message->content;

            $this->notifications->send(
                $user,
                NotificationType::MENTION,
                'Anda disebut dalam chat',
                "{$sender->name}: {$preview}",
                ['channel_id' => $channel->id, 'message_id' => $message->id, 'url' => "/chat?channel={$channel->id}"],
            );
        }
    }

    /** Membership management requires owner/admin role on the channel. */
    private function assertManager(ChatChannel $channel, User $actor): void
    {
        $member = $channel->memberRecord($actor);
        if (! $member || ! in_array($member->role, ['owner', 'admin'], true)) {
            throw ValidationException::withMessages([
                'channel' => 'Hanya pemilik atau admin channel yang dapat mengelola anggota.',
            ]);
        }
    }

    /** Resolve a user within the actor's organization (tenant guard). */
    private function resolveOrgUser(User $actor, string $userId): ?User
    {
        return User::where('id', $userId)
            ->where('organization_id', $actor->organization_id)
            ->first();
    }
}
