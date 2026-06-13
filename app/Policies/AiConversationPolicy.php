<?php
namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;

class AiConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ai.query');
    }

    /**
     * Own-only viewing (ai.conversation.view.own). Tenant isolation is also
     * enforced by the BelongsToOrganization global scope, but ownership is the
     * primary gate here.
     */
    public function view(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id
            && $conversation->organization_id === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('ai.query');
    }
}
