<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'report.view.own', 'report.view.team', 'report.view.all',
        ]);
    }

    public function view(User $user, Report $report): bool
    {
        if ($report->author_id === $user->id) {
            return true;
        }

        if ($user->hasPermissionTo('report.view.all')) {
            return true;
        }

        if ($user->hasPermissionTo('report.view.team')
            && $report->organization_id === $user->organization_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('report.create');
    }

    public function update(User $user, Report $report): bool
    {
        $editableStatuses = [ReportStatus::DRAFT, ReportStatus::REVISION];

        return $user->hasPermissionTo('report.create')
            && $report->author_id === $user->id
            && in_array($report->status, $editableStatuses);
    }

    public function submit(User $user, Report $report): bool
    {
        return $user->hasPermissionTo('report.submit')
            && $report->author_id === $user->id;
    }

    public function approve(User $user, Report $report): bool
    {
        return $user->hasPermissionTo('report.approve')
            && $report->author_id !== $user->id;
    }

    public function publish(User $user, Report $report): bool
    {
        return $user->hasPermissionTo('report.publish');
    }

    public function delete(User $user, Report $report): bool
    {
        return $report->author_id === $user->id
            && $report->status === ReportStatus::DRAFT;
    }

    public function generateAiDraft(User $user, Report $report): bool
    {
        return $this->update($user, $report);
    }
}
