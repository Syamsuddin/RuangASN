<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\RiskLevel;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    public function create(array $data, User $owner): Project
    {
        return DB::transaction(function () use ($data, $owner) {
            $project = Project::create([
                ...$data,
                'organization_id' => $owner->organization_id,
                'pemda_id'        => $owner->pemda_id,
                'owner_id'        => $owner->id,
                'created_by'      => $owner->id,
                'status'          => ProjectStatus::DRAFT->value,
            ]);

            // Owner is always a member (role owner).
            ProjectMember::create([
                'id'         => (string) Str::ulid(),
                'project_id' => $project->id,
                'user_id'    => $owner->id,
                'role'       => 'owner',
                'joined_at'  => now(),
            ]);

            $this->outbox->publish('project.created', $project->fresh()->toArray(), 'Project', $project->id);
            $this->audit->log(AuditAction::CREATED, 'Project', $project->id, [], $project->only('name', 'status'));

            return $project->fresh();
        });
    }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $old = $project->only('name', 'description', 'objectives', 'budget', 'budget_spent', 'progress_percent');
            $project->update($data);
            $this->outbox->publish('project.updated', $project->fresh()->toArray(), 'Project', $project->id);
            $this->audit->log(AuditAction::UPDATED, 'Project', $project->id, $old, $data);

            return $project->fresh();
        });
    }

    public function transition(Project $project, ProjectStatus $new, User $actor, ?string $notes = null): Project
    {
        return DB::transaction(function () use ($project, $new, $actor, $notes) {
            if (! $project->canTransitionTo($new, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat beralih dari status {$project->status->value} ke {$new->value}.",
                ]);
            }

            $from = $project->status->value;

            ProjectStatusHistory::create([
                'id'          => (string) Str::ulid(),
                'project_id'  => $project->id,
                'from_status' => $from,
                'to_status'   => $new->value,
                'changed_by'  => $actor->id,
                'notes'       => $notes,
            ]);

            $extra = [];
            if ($new === ProjectStatus::ACTIVE && $project->actual_start_date === null) {
                $extra['actual_start_date'] = now()->toDateString();
            }
            if ($new === ProjectStatus::COMPLETED) {
                $extra['actual_end_date'] = now()->toDateString();
            }

            $project->update(array_merge(['status' => $new->value], $extra));

            $this->outbox->publish('project.status_changed', [
                'project_id'      => $project->id,
                'organization_id' => $project->organization_id,
                'from_status'     => $from,
                'to_status'       => $new->value,
                'changed_by'      => $actor->id,
            ], 'Project', $project->id);

            $this->audit->log(
                AuditAction::STATUS_CHANGED,
                'Project',
                $project->id,
                ['status' => $from],
                ['status' => $new->value],
            );

            return $project->fresh();
        });
    }

    public function close(Project $project, User $actor, ?string $notes = null): Project
    {
        return $this->transition($project, ProjectStatus::COMPLETED, $actor, $notes);
    }

    // ── Members ─────────────────────────────────────────────────────────────

    public function addMember(Project $project, string $userId, string $role): ProjectMember
    {
        // Tenant guard: only users that belong to the project's organization may
        // be added. exists:users,id alone is NOT org-scoped, so resolve here to
        // block cross-org membership escalation (mirrors ChatService::resolveOrgUser).
        $user = $this->resolveOrgUser($project->organization_id, $userId);
        if ($user === null) {
            throw ValidationException::withMessages([
                'user_id' => 'Pengguna tidak ditemukan di organisasi ini.',
            ]);
        }

        return DB::transaction(function () use ($project, $userId, $role) {
            /** @var ProjectMember|null $existing */
            $existing = ProjectMember::where('project_id', $project->id)
                ->where('user_id', $userId)->first();

            if ($existing) {
                $existing->update(['role' => $role, 'left_at' => null]);
                $member = $existing;
            } else {
                $member = ProjectMember::create([
                    'id'         => (string) Str::ulid(),
                    'project_id' => $project->id,
                    'user_id'    => $userId,
                    'role'       => $role,
                    'joined_at'  => now(),
                ]);
            }

            $this->outbox->publish('project.member_added', [
                'project_id'      => $project->id,
                'organization_id' => $project->organization_id,
                'user_id'         => $userId,
                'role'            => $role,
            ], 'Project', $project->id);
            $this->audit->log(AuditAction::UPDATED, 'ProjectMember', $member->id, [], ['user_id' => $userId, 'role' => $role]);

            return $member;
        });
    }

    public function removeMember(Project $project, ProjectMember $member): void
    {
        DB::transaction(function () use ($project, $member) {
            $member->update(['left_at' => now()]);
            $this->outbox->publish('project.member_removed', [
                'project_id'      => $project->id,
                'organization_id' => $project->organization_id,
                'user_id'         => $member->user_id,
            ], 'Project', $project->id);
            $this->audit->log(AuditAction::UPDATED, 'ProjectMember', $member->id, ['active' => true], ['active' => false]);
        });
    }

    // ── Milestones ──────────────────────────────────────────────────────────

    public function addMilestone(Project $project, array $data): ProjectMilestone
    {
        return DB::transaction(function () use ($project, $data) {
            $milestone = ProjectMilestone::create([
                'id'          => (string) Str::ulid(),
                'project_id'  => $project->id,
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'status'      => $data['status'] ?? MilestoneStatus::PENDING->value,
                'due_date'    => $data['due_date'],
                'sort_order'  => $data['sort_order'] ?? $project->milestones()->count(),
            ]);

            $this->recomputeProgress($project);
            $this->audit->log(AuditAction::CREATED, 'ProjectMilestone', $milestone->id, [], $milestone->only('name', 'status'));

            return $milestone;
        });
    }

    public function updateMilestone(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        return DB::transaction(function () use ($milestone, $data) {
            // Keep completed_at in sync with status changes through the editor.
            if (array_key_exists('status', $data)) {
                if ($data['status'] === MilestoneStatus::COMPLETED->value && $milestone->completed_at === null) {
                    $data['completed_at'] = now();
                } elseif ($data['status'] !== MilestoneStatus::COMPLETED->value) {
                    $data['completed_at'] = null;
                }
            }

            $milestone->update($data);

            /** @var Project|null $project */
            $project = $milestone->project;
            if ($project) {
                $this->recomputeProgress($project);
            }

            return $milestone->fresh();
        });
    }

    public function completeMilestone(ProjectMilestone $milestone): ProjectMilestone
    {
        return DB::transaction(function () use ($milestone) {
            $milestone->update([
                'status'       => MilestoneStatus::COMPLETED->value,
                'completed_at' => now(),
            ]);

            /** @var Project|null $project */
            $project = $milestone->project;
            if ($project) {
                $this->recomputeProgress($project);
                $this->outbox->publish('project.milestone_completed', [
                    'project_id'      => $project->id,
                    'organization_id' => $project->organization_id,
                    'milestone_id'    => $milestone->id,
                ], 'Project', $project->id);
            }

            return $milestone->fresh();
        });
    }

    public function deleteMilestone(ProjectMilestone $milestone): void
    {
        DB::transaction(function () use ($milestone) {
            /** @var Project|null $project */
            $project = $milestone->project;
            $milestone->delete();
            if ($project) {
                $this->recomputeProgress($project);
            }
        });
    }

    public function recomputeProgress(Project $project): Project
    {
        $milestones = $project->milestones()->get();
        $total = $milestones->count();

        if ($total > 0) {
            $completed = $milestones->where('status', MilestoneStatus::COMPLETED)->count();
            $project->update(['progress_percent' => (int) round($completed / $total * 100)]);
        }

        return $project->fresh();
    }

    // ── Risks ───────────────────────────────────────────────────────────────

    public function addRisk(Project $project, array $data): ProjectRisk
    {
        // Tenant guard: a risk owner must belong to the project's org (else a
        // cross-org name could be surfaced via risks.owner). Drop unknown owners.
        if (! empty($data['owner_id'])
            && $this->resolveOrgUser($project->organization_id, (string) $data['owner_id']) === null) {
            throw ValidationException::withMessages([
                'owner_id' => 'Pengguna tidak ditemukan di organisasi ini.',
            ]);
        }

        return DB::transaction(function () use ($project, $data) {
            $risk = ProjectRisk::create([
                'id'          => (string) Str::ulid(),
                'project_id'  => $project->id,
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'risk_level'  => $data['risk_level'] ?? RiskLevel::MEDIUM->value,
                'probability' => $data['probability'] ?? null,
                'impact'      => $data['impact'] ?? null,
                'mitigation'  => $data['mitigation'] ?? null,
                'status'      => $data['status'] ?? 'open',
                'owner_id'    => $data['owner_id'] ?? null,
            ]);

            $this->audit->log(AuditAction::CREATED, 'ProjectRisk', $risk->id, [], $risk->only('title', 'risk_level'));

            return $risk;
        });
    }

    public function updateRisk(ProjectRisk $risk, array $data): ProjectRisk
    {
        if (! empty($data['owner_id'])) {
            /** @var Project|null $project */
            $project = $risk->project;
            $orgId   = $project?->organization_id;
            if ($orgId === null || $this->resolveOrgUser($orgId, (string) $data['owner_id']) === null) {
                throw ValidationException::withMessages([
                    'owner_id' => 'Pengguna tidak ditemukan di organisasi ini.',
                ]);
            }
        }

        return DB::transaction(function () use ($risk, $data) {
            $risk->update($data);

            return $risk->fresh();
        });
    }

    /** Resolve a user within a given organization (tenant guard). */
    private function resolveOrgUser(string $organizationId, string $userId): ?User
    {
        return User::where('id', $userId)
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function closeRisk(ProjectRisk $risk): ProjectRisk
    {
        return DB::transaction(function () use ($risk) {
            $risk->update(['status' => 'closed']);

            return $risk->fresh();
        });
    }
}
