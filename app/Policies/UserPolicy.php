<?php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('admin.users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('admin.users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('admin.users.edit');
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->hasPermissionTo('admin.users.deactivate');
    }
}
