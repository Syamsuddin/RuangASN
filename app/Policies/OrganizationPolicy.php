<?php
namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $org): bool
    {
        return $user->hasPermissionTo('admin.organizations.view')
            || $user->hasPermissionTo('organization.view.tree');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('admin.organizations.create');
    }

    public function update(User $user, Organization $org): bool
    {
        return $user->hasPermissionTo('admin.organizations.edit');
    }
}
