<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['project.view.own', 'project.view.all']);
    }

    public function view(User $user, Project $project): bool
    {
        if ($project->isMember($user)) {
            return true;
        }

        return $user->hasPermissionTo('project.view.all')
            && $project->organization_id === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('project.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('project.edit')
            && ($project->owner_id === $user->id || $project->manager_id === $user->id);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('project.member.manage')
            && ($project->owner_id === $user->id || $project->manager_id === $user->id);
    }

    public function manageMilestone(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('project.milestone.manage')
            && $project->isMember($user);
    }

    public function manageRisk(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('project.risk.manage')
            && $project->isMember($user);
    }

    public function close(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('project.close')
            && $project->owner_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id
            && $project->status === \App\Enums\ProjectStatus::DRAFT;
    }
}
