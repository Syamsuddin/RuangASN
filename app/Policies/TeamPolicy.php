<?php
namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('admin.units.manage');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['admin.units.manage', 'organization.view.tree']);
    }
}
