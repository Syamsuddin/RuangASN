<?php
namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['task.view.own', 'task.view.team', 'task.view.all']);
    }

    public function view(User $user, Task $task): bool
    {
        if ($task->creator_id === $user->id || $task->assignee_id === $user->id) {
            return true;
        }
        if ($user->hasPermissionTo('task.view.subordinate') && $user->subordinateIds()->contains($task->assignee_id)) {
            return true;
        }
        return $user->hasPermissionTo('task.view.all');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('task.create');
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasPermissionTo('task.edit.any')) {
            return true;
        }
        if ($user->hasPermissionTo('task.edit.own') && $task->creator_id === $user->id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        if ($user->hasPermissionTo('task.delete.any')) {
            return true;
        }
        return $user->hasPermissionTo('task.delete.own')
            && $task->creator_id === $user->id
            && $task->status->value === 'draft';
    }
}
