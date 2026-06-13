<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['calendar.view.own', 'calendar.view.team', 'calendar.view.all']);
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        if ($event->owner_id === $user->id) {
            return true;
        }
        if ($event->is_public && $event->organization_id === $user->organization_id) {
            return true;
        }
        if ($user->hasPermissionTo('calendar.view.all')) {
            return true;
        }
        if ($event->calendar_type->value === 'team' && $user->hasPermissionTo('calendar.view.team')) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('calendar.create.own');
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $user->hasPermissionTo('calendar.edit.own') && $event->owner_id === $user->id;
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $user->hasPermissionTo('calendar.edit.own') && $event->owner_id === $user->id;
    }
}
