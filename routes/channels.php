<?php

use App\Models\ChatChannel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Private channel authorization for Reverb. A user may only receive events
| on their own user channel, and on a chat channel they are an active member
| of (within the same organization — enforced by the BelongsToOrganization
| global scope on ChatChannel, which is applied because the authenticated
| user is set during channel auth).
|
*/

Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});

Broadcast::channel('chat.channel.{channelId}', function (User $user, string $channelId) {
    // BelongsToOrganization global scope hides channels from other orgs, so a
    // cross-tenant lookup returns null → unauthorized. Membership is required.
    $channel = ChatChannel::find($channelId);

    return $channel !== null && $channel->isMember($user);
});
