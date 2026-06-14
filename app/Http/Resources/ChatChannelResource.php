<?php

namespace App\Http\Resources;

use App\Enums\ChatChannelType;
use App\Models\ChatChannelMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChatChannel */
class ChatChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $type = $this->channel_type;

        $name = $this->name;
        $counterpart = null;

        // DM channels are named after the *other* member for the current user.
        if ($type === ChatChannelType::DM && $user) {
            /** @var ChatChannelMember|null $other */
            $other = $this->members
                ->first(fn (ChatChannelMember $m) => $m->user_id !== $user->id);
            $counterpart = $other ? $other->user : null;
            $name = ($counterpart?->name) ?: ($name ?? 'Direct Message');
        }

        $lastMessage = $this->messages->sortByDesc('created_at')->first();

        return [
            'id'            => $this->id,
            'channel_type'  => $type,
            'name'          => $name,
            'description'   => $this->description,
            'is_archived'   => $this->is_archived,
            'team_id'       => $this->team_id,
            'project_id'    => $this->project_id,
            'meeting_id'    => $this->meeting_id,
            'created_by'    => $this->created_by,
            'member_count'  => $this->members->count(),
            'unread_count'  => $user ? $this->unreadCountFor($user) : 0,
            'counterpart'   => $counterpart ? ['id' => $counterpart->id, 'name' => $counterpart->name] : null,
            'last_message'  => $lastMessage ? [
                'content'    => mb_strlen($lastMessage->content) > 60
                    ? mb_substr($lastMessage->content, 0, 60) . '…'
                    : $lastMessage->content,
                'sender_id'  => $lastMessage->sender_id,
                'created_at' => $lastMessage->created_at?->toISOString(),
            ] : null,
            'members'       => $this->whenLoaded('members', fn () => $this->members
                ->map(fn (ChatChannelMember $m) => [
                    'id'           => $m->id,
                    'user_id'      => $m->user_id,
                    'name'         => $m->user?->name,
                    'role'         => $m->role,
                    'last_read_at' => $m->last_read_at?->toISOString(),
                ])
                ->values()
                ->all()),
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
