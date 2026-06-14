<?php

namespace App\Http\Controllers;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\RiskLevel;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        $query = Project::with(['owner:id,name', 'manager:id,name'])
            ->withCount(['milestones', 'risks', 'tasks', 'meetings'])
            ->when(
                ! $user->hasPermissionTo('project.view.all'),
                fn ($q) => $q->where(function ($sq) use ($user) {
                    $sq->where('owner_id', $user->id)
                        ->orWhere('manager_id', $user->id)
                        ->orWhereHas('members', fn ($m) =>
                            $m->where('user_id', $user->id)->whereNull('left_at'));
                })
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $query->map(fn (Project $p) => $this->formatCard($p)),
            'filters'  => $request->only(['status', 'search']),
            'statuses' => array_column(ProjectStatus::cases(), 'value'),
            'teams'    => \App\Models\Team::where('pemda_id', $user->pemda_id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users'    => User::where('organization_id', $user->organization_id)
                ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'nip']),
            'can'      => [
                'create' => $user->can('create', Project::class),
            ],
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'owner:id,name',
            'manager:id,name',
            'team:id,name',
            'members.user:id,name',
            'milestones',
            'risks.owner:id,name',
            'statusHistories.changedBy:id,name',
            'tasks:id,project_id,title,status',
            'meetings:id,project_id,title,status,scheduled_at',
        ])->loadCount(['tasks', 'meetings']);

        /** @var User $user */
        $user = auth()->user();

        return Inertia::render('Projects/Show', [
            'project' => new ProjectResource($project),
            'linked'  => [
                'tasks'    => $project->tasks->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'status' => $t->status,
                ]),
                'meetings' => $project->meetings->map(fn ($m) => [
                    'id' => $m->id, 'title' => $m->title, 'status' => $m->status,
                    'scheduled_at' => $m->scheduled_at?->toISOString(),
                ]),
            ],
            'users'        => User::where('organization_id', $user->organization_id)
                ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'nip']),
            'transitions'  => $this->allowedTransitions($project, $user),
            'can'          => [
                'update'          => $user->can('update', $project),
                'manageMembers'   => $user->can('manageMembers', $project),
                'manageMilestone' => $user->can('manageMilestone', $project),
                'manageRisk'      => $user->can('manageRisk', $project),
                'transition'      => $user->can('update', $project),
                'close'           => $user->can('close', $project),
                'delete'          => $user->can('delete', $project),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $user = $request->user();
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:500'],
            'description'         => ['nullable', 'string'],
            'objectives'          => ['nullable', 'string'],
            'planned_start_date'  => ['nullable', 'date'],
            'planned_end_date'    => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'budget'              => ['nullable', 'numeric', 'min:0'],
            'team_id'             => ['nullable', $this->teamOwnedByActor($user)],
            'manager_id'          => ['nullable', $this->userInActorOrg($user)],
            'data_classification' => ['nullable', Rule::in([1, 2, 3, 4])],
            'tags'                => ['nullable', 'array'],
        ]);

        $project = $this->projectService->create($data, $request->user());

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyek berhasil dibuat.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $user = $request->user();
        $data = $request->validate([
            'name'                => ['sometimes', 'string', 'max:500'],
            'description'         => ['nullable', 'string'],
            'objectives'          => ['nullable', 'string'],
            'planned_start_date'  => ['nullable', 'date'],
            'planned_end_date'    => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'budget'              => ['nullable', 'numeric', 'min:0'],
            'budget_spent'        => ['nullable', 'numeric', 'min:0'],
            'team_id'             => ['nullable', $this->teamOwnedByActor($user)],
            'manager_id'          => ['nullable', $this->userInActorOrg($user)],
            'progress_percent'    => ['sometimes', 'integer', 'min:0', 'max:100'],
            'data_classification' => ['sometimes', Rule::in([1, 2, 3, 4])],
            'tags'                => ['nullable', 'array'],
        ]);

        $this->projectService->update($project, $data);

        return back()->with('success', 'Proyek berhasil diperbarui.');
    }

    public function transition(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
            'notes'  => ['nullable', 'string'],
        ]);

        $new = ProjectStatus::from($data['status']);
        $this->projectService->transition($project, $new, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Status proyek berhasil diubah.');
    }

    public function close(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('close', $project);

        $data = $request->validate(['notes' => ['nullable', 'string']]);

        $this->projectService->close($project, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Proyek berhasil diselesaikan.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->update(['deleted_by' => auth()->id()]);
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Proyek berhasil dihapus.');
    }

    // ── Members ─────────────────────────────────────────────────────────────

    public function addMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['nullable', 'string', 'max:30'],
        ]);

        $this->projectService->addMember($project, $data['user_id'], $data['role'] ?? 'member');

        return back()->with('success', 'Anggota proyek ditambahkan.');
    }

    public function removeMember(Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        abort_unless($member->project_id === $project->id, 404);

        $this->projectService->removeMember($project, $member);

        return back()->with('success', 'Anggota proyek dikeluarkan.');
    }

    // ── Milestones ──────────────────────────────────────────────────────────

    public function addMilestone(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMilestone', $project);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', Rule::in(array_column(MilestoneStatus::cases(), 'value'))],
            'due_date'    => ['required', 'date'],
            'sort_order'  => ['nullable', 'integer'],
        ]);

        $this->projectService->addMilestone($project, $data);

        return back()->with('success', 'Milestone ditambahkan.');
    }

    public function updateMilestone(Request $request, ProjectMilestone $milestone): RedirectResponse
    {
        $project = $this->projectForMilestone($milestone);
        $this->authorize('manageMilestone', $project);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'status'      => ['sometimes', Rule::in(array_column(MilestoneStatus::cases(), 'value'))],
            'due_date'    => ['sometimes', 'date'],
            'sort_order'  => ['sometimes', 'integer'],
        ]);

        $this->projectService->updateMilestone($milestone, $data);

        return back()->with('success', 'Milestone diperbarui.');
    }

    public function completeMilestone(ProjectMilestone $milestone): RedirectResponse
    {
        $project = $this->projectForMilestone($milestone);
        $this->authorize('manageMilestone', $project);

        $this->projectService->completeMilestone($milestone);

        return back()->with('success', 'Milestone diselesaikan.');
    }

    public function deleteMilestone(ProjectMilestone $milestone): RedirectResponse
    {
        $project = $this->projectForMilestone($milestone);
        $this->authorize('manageMilestone', $project);

        $this->projectService->deleteMilestone($milestone);

        return back()->with('success', 'Milestone dihapus.');
    }

    // ── Risks ───────────────────────────────────────────────────────────────

    public function addRisk(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageRisk', $project);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'risk_level'  => ['nullable', Rule::in(array_column(RiskLevel::cases(), 'value'))],
            'probability' => ['nullable', 'integer', 'min:1', 'max:5'],
            'impact'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'mitigation'  => ['nullable', 'string'],
            'owner_id'    => ['nullable', 'exists:users,id'],
        ]);

        $this->projectService->addRisk($project, $data);

        return back()->with('success', 'Risiko ditambahkan.');
    }

    public function updateRisk(Request $request, ProjectRisk $risk): RedirectResponse
    {
        $project = $this->projectForRisk($risk);
        $this->authorize('manageRisk', $project);

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'risk_level'  => ['sometimes', Rule::in(array_column(RiskLevel::cases(), 'value'))],
            'probability' => ['nullable', 'integer', 'min:1', 'max:5'],
            'impact'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'mitigation'  => ['nullable', 'string'],
            'status'      => ['sometimes', Rule::in(['open', 'mitigating', 'closed'])],
            'owner_id'    => ['nullable', 'exists:users,id'],
        ]);

        $this->projectService->updateRisk($risk, $data);

        return back()->with('success', 'Risiko diperbarui.');
    }

    public function closeRisk(ProjectRisk $risk): RedirectResponse
    {
        $project = $this->projectForRisk($risk);
        $this->authorize('manageRisk', $project);

        $this->projectService->closeRisk($risk);

        return back()->with('success', 'Risiko ditutup.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Org-scoped exists rule for manager_id. manager_id grants privileged access
     * (ProjectPolicy::update/manageMembers via Project::isMember), so a cross-org
     * manager would be an authorization escalation — restrict to the acting org.
     */
    private function userInActorOrg(User $actor): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('users', 'id')->where('organization_id', $actor->organization_id);
    }

    /**
     * Org-scoped exists rule for team_id. A team belongs to a pemda; restrict to
     * the acting user's pemda so a cross-tenant team can't be attached.
     */
    private function teamOwnedByActor(User $actor): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('teams', 'id')->where('pemda_id', $actor->pemda_id);
    }

    /**
     * Resolve the (tenant-scoped) parent project of a milestone, or 404.
     */
    private function projectForMilestone(ProjectMilestone $milestone): Project
    {
        /** @var Project|null $project */
        $project = Project::find($milestone->project_id);
        abort_if($project === null, 404);

        return $project;
    }

    private function projectForRisk(ProjectRisk $risk): Project
    {
        /** @var Project|null $project */
        $project = Project::find($risk->project_id);
        abort_if($project === null, 404);

        return $project;
    }

    /**
     * @return list<string>
     */
    private function allowedTransitions(Project $project, User $user): array
    {
        return array_values(array_filter(
            array_column(ProjectStatus::cases(), 'value'),
            fn (string $s) => $s !== $project->status->value
                && $project->canTransitionTo(ProjectStatus::from($s), $user)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCard(Project $p): array
    {
        $budget = $p->budget !== null ? (float) $p->budget : null;
        $spent  = (float) $p->budget_spent;

        /** @var User|null $owner */
        $owner = $p->owner;

        return [
            'id'                 => $p->id,
            'name'               => $p->name,
            'status'             => $p->status,
            'progress_percent'   => (int) $p->progress_percent,
            'budget'             => $budget,
            'budget_spent'       => $spent,
            'budget_utilization' => $budget && $budget > 0 ? (int) round($spent / $budget * 100) : 0,
            'planned_end_date'   => $p->planned_end_date?->toDateString(),
            'milestones_count'   => $p->milestones_count ?? 0,
            'risks_count'        => $p->risks_count ?? 0,
            'tasks_count'        => $p->tasks_count ?? 0,
            'meetings_count'     => $p->meetings_count ?? 0,
            'updated_at'         => $p->updated_at?->toISOString(),
            'owner'              => $owner ? ['id' => $owner->id, 'name' => $owner->name] : null,
        ];
    }
}
