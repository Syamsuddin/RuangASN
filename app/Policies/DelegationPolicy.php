<?php
namespace App\Policies;

use App\Models\Delegation;
use App\Models\User;

class DelegationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['organization.delegation.view', 'organization.delegation.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('organization.delegation.manage');
    }

    public function update(User $user, Delegation $delegation): bool
    {
        return $user->hasPermissionTo('organization.delegation.manage');
    }

    public function revoke(User $user, Delegation $delegation): bool
    {
        return $user->hasPermissionTo('organization.delegation.manage');
    }
}
