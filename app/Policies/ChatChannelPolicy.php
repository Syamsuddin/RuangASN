<?php

namespace App\Policies;

use App\Models\ChatChannel;
use App\Models\User;

class ChatChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['chat.view.channel', 'chat.view.dm']);
    }

    /**
     * Read access is member-only — even admins must be a member to read a
     * channel. Tenant isolation: the BelongsToOrganization scope already hides
     * cross-org channels, but we assert org equality defensively.
     */
    public function view(User $user, ChatChannel $channel): bool
    {
        if ($channel->organization_id !== $user->organization_id) {
            return false;
        }
        return $channel->isMember($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('chat.channel.create');
    }

    public function sendMessage(User $user, ChatChannel $channel): bool
    {
        return $user->hasPermissionTo('chat.send') && $channel->isMember($user);
    }

    public function archive(User $user, ChatChannel $channel): bool
    {
        return $user->hasPermissionTo('chat.channel.archive')
            && $channel->created_by === $user->id;
    }
}
