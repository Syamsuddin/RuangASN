<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['meeting.view.own', 'meeting.view.all']);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if ($meeting->host_id === $user->id || $meeting->secretary_id === $user->id) {
            return true;
        }
        if ($meeting->participants()->where('user_id', $user->id)->exists()) {
            return true;
        }
        return $user->hasPermissionTo('meeting.view.all');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('meeting.create');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->hasPermissionTo('meeting.edit.own') && $meeting->host_id === $user->id;
    }

    public function cancel(User $user, Meeting $meeting): bool
    {
        return $user->hasPermissionTo('meeting.edit.own') && $meeting->host_id === $user->id;
    }

    public function recordMinutes(User $user, Meeting $meeting): bool
    {
        if (! $user->hasPermissionTo('meeting.minutes.create')) {
            return false;
        }
        return $meeting->host_id === $user->id || $meeting->secretary_id === $user->id;
    }

    public function approveMinutes(User $user): bool
    {
        return $user->hasPermissionTo('meeting.minutes.approve');
    }

    public function createActionItem(User $user, Meeting $meeting): bool
    {
        if (! $user->hasPermissionTo('meeting.action_item.create')) {
            return false;
        }
        return $meeting->host_id === $user->id || $meeting->secretary_id === $user->id;
    }
}
