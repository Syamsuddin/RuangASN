<?php

namespace App\Policies;

use App\Enums\PerformanceStatus;
use App\Models\SkpPlan;
use App\Models\User;

class SkpPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'performance.view.own',
            'performance.view.team',
            'performance.view.all',
        ]);
    }

    public function view(User $user, SkpPlan $plan): bool
    {
        // Owner always can view
        if ($plan->user_id === $user->id) {
            return true;
        }

        // Global view.all
        if ($user->hasPermissionTo('performance.view.all')) {
            return true;
        }

        // Superior can view their subordinate's plan
        if ($plan->superior_id === $user->id) {
            return true;
        }

        // Team-level view (same org) requires view.team
        if ($user->hasPermissionTo('performance.view.team')
            && $plan->organization_id === $user->organization_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('performance.skp.create');
    }

    public function update(User $user, SkpPlan $plan): bool
    {
        return $plan->user_id === $user->id
            && $plan->status === PerformanceStatus::PLANNING;
    }

    public function submit(User $user, SkpPlan $plan): bool
    {
        return $user->hasPermissionTo('performance.skp.submit')
            && $plan->user_id === $user->id;
    }

    /**
     * Authority to evaluate/review a subordinate's plan.
     *
     * Requires performance.skp.review AND mutation authority over THIS plan —
     * i.e. being the assigned superior OR holding the dedicated
     * performance.skp.approve permission. Bare performance.view.all is a READ
     * permission and MUST NOT grant the ability to drive someone else's SKP
     * through arbitrary states.
     */
    public function review(User $user, SkpPlan $plan): bool
    {
        // Cannot self-evaluate
        if ($plan->user_id === $user->id) {
            return false;
        }

        if (! $user->hasPermissionTo('performance.skp.review')) {
            return false;
        }

        return $this->hasMutationAuthority($user, $plan);
    }

    public function evaluate(User $user, SkpPlan $plan): bool
    {
        return $this->review($user, $plan);
    }

    /**
     * Authority to approve a plan or drive its state transitions.
     * Requires being the assigned superior OR the dedicated
     * performance.skp.approve permission — NOT bare performance.view.all.
     */
    public function approve(User $user, SkpPlan $plan): bool
    {
        return $this->hasMutationAuthority($user, $plan);
    }

    public function addRealization(User $user, SkpPlan $plan): bool
    {
        return $plan->user_id === $user->id
            && $plan->status === PerformanceStatus::ACTIVE;
    }

    /**
     * Authority to mutate (approve / transition / evaluate) another user's plan:
     * the assigned superior, or a holder of the dedicated approval permission.
     * Read-only permissions (view.all / view.team) deliberately do NOT count.
     */
    private function hasMutationAuthority(User $user, SkpPlan $plan): bool
    {
        return $plan->superior_id === $user->id
            || $user->hasPermissionTo('performance.skp.approve');
    }

    public function delete(User $user, SkpPlan $plan): bool
    {
        return $plan->user_id === $user->id
            && $plan->status === PerformanceStatus::PLANNING;
    }
}
