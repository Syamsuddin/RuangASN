<?php

namespace App\Http\Controllers;

use App\Enums\PerformanceStatus;
use App\Enums\SkpPerspective;
use App\Http\Resources\SkpPlanResource;
use App\Models\SkpIndicator;
use App\Models\SkpPlan;
use App\Services\SkpCalculationService;
use App\Services\SkpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function __construct(
        private SkpService $skpService,
        private SkpCalculationService $calcService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SkpPlan::class);

        $user  = $request->user();
        $plans = SkpPlan::with(['period', 'superior:id,name', 'indicators'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $activePeriod = \App\Models\SkpPeriod::where('is_active', true)->first();

        // Compute progress per plan
        $planData = $plans->map(fn ($p) => [
            ...(new SkpPlanResource($p))->toArray($request),
        ]);

        $superiors = \App\Models\User::where('organization_id', $user->organization_id)
            ->where('id', '!=', $user->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $periods = \App\Models\SkpPeriod::where('is_active', true)->get()->map(fn ($p) => [
            'id'   => $p->id,
            'name' => $p->name,
            'year' => $p->year,
        ]);

        return Inertia::render('Performance/Index', [
            'plans'        => $planData,
            'activePeriod' => $activePeriod ? [
                'id'   => $activePeriod->id,
                'name' => $activePeriod->name,
                'year' => $activePeriod->year,
            ] : null,
            'superiors'    => $superiors,
            'periods'      => $periods,
            'can'          => [
                'create' => $user->can('create', SkpPlan::class),
            ],
        ]);
    }

    public function show(SkpPlan $plan): Response
    {
        $this->authorize('view', $plan);

        $plan->load([
            'user:id,name',
            'superior:id,name',
            'period',
            'indicators.realizations',
            'evaluation.evaluator:id,name',
        ]);

        $user = auth()->user();

        // Group indicators by perspective
        $grouped = [];
        foreach (SkpPerspective::cases() as $perspective) {
            $grouped[$perspective->value] = $plan->indicators
                ->where('perspective.value', $perspective->value)
                ->values();
        }

        return Inertia::render('Performance/Show', [
            'plan'              => new SkpPlanResource($plan),
            'perspectiveGroups' => $grouped,
            'perspectives'      => array_column(SkpPerspective::cases(), 'value'),
            'can'               => [
                'update'         => $user->can('update', $plan),
                'submit'         => $user->can('submit', $plan),
                'approve'        => $user->can('approve', $plan),
                'review'         => $user->can('review', $plan),
                'evaluate'       => $user->can('evaluate', $plan),
                'addRealization' => $user->can('addRealization', $plan),
            ],
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $this->authorize('create', SkpPlan::class);

        $data = $request->validate([
            'period_id'   => ['required', 'string', 'exists:skp_periods,id'],
            'superior_id' => ['nullable', 'string', 'exists:users,id'],
        ]);

        $plan = $this->skpService->createPlan($data, $request->user());

        return redirect()->route('performance.show', $plan)
            ->with('success', 'SKP berhasil dibuat.');
    }

    public function updatePlan(Request $request, SkpPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $data = $request->validate([
            'superior_id'   => ['nullable', 'string', 'exists:users,id'],
            'document_path' => ['nullable', 'string', 'max:500'],
        ]);

        $this->skpService->updatePlan($plan, $data);

        return back()->with('success', 'SKP berhasil diperbarui.');
    }

    public function addIndicator(Request $request, SkpPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $perspectives = array_column(SkpPerspective::cases(), 'value');

        $data = $request->validate([
            'perspective'          => ['required', Rule::in($perspectives)],
            'name'                 => ['required', 'string', 'max:500'],
            'target_value'         => ['required', 'numeric', 'gt:0'],
            'target_unit'          => ['required', 'string', 'max:100'],
            'weight'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'superior_expectation' => ['nullable', 'string'],
            'sort_order'           => ['nullable', 'integer'],
            'parent_indicator_id'  => ['nullable', 'string', 'exists:skp_indicators,id'],
        ]);

        $this->skpService->addIndicator($plan, $data);

        return back()->with('success', 'Indikator berhasil ditambahkan.');
    }

    public function updateIndicator(Request $request, SkpIndicator $indicator): RedirectResponse
    {
        /** @var SkpPlan $plan */
        $plan = SkpPlan::findOrFail($indicator->skp_plan_id);
        $this->authorize('update', $plan);

        $data = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:500'],
            'target_value'         => ['sometimes', 'numeric', 'gt:0'],
            'target_unit'          => ['sometimes', 'string', 'max:100'],
            'weight'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'superior_expectation' => ['nullable', 'string'],
            'sort_order'           => ['nullable', 'integer'],
        ]);

        $this->skpService->updateIndicator($indicator, $data);

        return back()->with('success', 'Indikator berhasil diperbarui.');
    }

    public function deleteIndicator(SkpIndicator $indicator): RedirectResponse
    {
        /** @var SkpPlan $plan */
        $plan = SkpPlan::findOrFail($indicator->skp_plan_id);
        $this->authorize('update', $plan);

        if ($plan->status !== PerformanceStatus::PLANNING) {
            return back()->withErrors(['status' => 'Indikator hanya bisa dihapus saat status Perencanaan.']);
        }

        $this->skpService->deleteIndicator($indicator);

        return back()->with('success', 'Indikator berhasil dihapus.');
    }

    public function addRealization(Request $request, SkpIndicator $indicator): RedirectResponse
    {
        // Resolve the parent plan through SkpPlan (which carries the
        // BelongsToOrganization global scope) so a cross-org indicator yields
        // a 404 rather than allowing a child mutation on another tenant's plan.
        /** @var SkpPlan $plan */
        $plan = SkpPlan::findOrFail($indicator->skp_plan_id);
        $this->authorize('addRealization', $plan);

        $data = $request->validate([
            'realization_value' => ['required', 'numeric', 'min:0'],
            'realization_date'  => ['required', 'date'],
            'description'       => ['nullable', 'string'],
            'task_id'           => ['nullable', 'string', 'exists:tasks,id'],
            'document_id'       => ['nullable', 'string', 'exists:documents,id'],
        ]);

        $this->skpService->addRealization($indicator, $data, $request->user());

        return back()->with('success', 'Realisasi berhasil ditambahkan.');
    }

    public function submit(SkpPlan $plan): RedirectResponse
    {
        $this->authorize('submit', $plan);

        $this->skpService->submitPlan($plan, auth()->user());

        return back()->with('success', 'SKP berhasil diajukan ke atasan.');
    }

    public function approve(SkpPlan $plan): RedirectResponse
    {
        $this->authorize('approve', $plan);

        $this->skpService->approvePlan($plan, auth()->user());

        return back()->with('success', 'SKP berhasil disetujui.');
    }

    public function transition(Request $request, SkpPlan $plan): RedirectResponse
    {
        $this->authorize('approve', $plan);

        $statusValues = array_column(PerformanceStatus::cases(), 'value');

        $data = $request->validate([
            'status' => ['required', Rule::in($statusValues)],
        ]);

        $new = PerformanceStatus::from($data['status']);
        $this->skpService->transition($plan, $new, auth()->user());

        return back()->with('success', 'Status SKP berhasil diubah.');
    }

    public function evaluate(Request $request, SkpPlan $plan): RedirectResponse
    {
        $this->authorize('evaluate', $plan);

        $data = $request->validate([
            'behavior_service'    => ['required', 'numeric', 'min:0', 'max:120'],
            'behavior_commit'     => ['required', 'numeric', 'min:0', 'max:120'],
            'behavior_initiative' => ['required', 'numeric', 'min:0', 'max:120'],
            'behavior_teamwork'   => ['required', 'numeric', 'min:0', 'max:120'],
            'behavior_leadership' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'superior_feedback'   => ['nullable', 'string'],
        ]);

        $this->calcService->evaluate(
            $plan,
            [
                'service'    => $data['behavior_service'],
                'commit'     => $data['behavior_commit'],
                'initiative' => $data['behavior_initiative'],
                'teamwork'   => $data['behavior_teamwork'],
                'leadership' => $data['behavior_leadership'] ?? null,
            ],
            $data['superior_feedback'] ?? null,
            auth()->user()
        );

        // Transition to evaluating if still active. Refresh once (the evaluate
        // call above may have changed state) then reuse the same instance.
        $plan->refresh();
        if ($plan->status === PerformanceStatus::ACTIVE) {
            $this->skpService->transition($plan, PerformanceStatus::EVALUATING, auth()->user());
        }

        return back()->with('success', 'Evaluasi SKP berhasil disimpan.');
    }

    public function destroy(SkpPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $this->skpService->deletePlan($plan, auth()->user());

        return redirect()->route('performance.index')
            ->with('success', 'SKP berhasil dihapus.');
    }

    public function analytics(Request $request): Response
    {
        $this->authorize('viewAny', SkpPlan::class);

        $user = $request->user();

        if (! $user->hasPermissionTo('performance.analytics.view')) {
            abort(403);
        }

        $plans = SkpPlan::with(['user:id,name', 'evaluation'])
            ->withCount('indicators')
            ->when(
                ! $user->hasPermissionTo('performance.view.all'),
                fn ($q) => $q->where('organization_id', $user->organization_id)
            )
            ->get();

        // Predicate distribution
        $predicateDistribution = $plans->groupBy(fn ($p) => $p->evaluation?->predicate?->value ?? 'belum_dievaluasi')
            ->map->count()
            ->toArray();

        $avgScore = $plans->filter(fn ($p) => $p->evaluation?->final_score !== null)
            ->avg(fn ($p) => (float) $p->evaluation->final_score);

        $members = $plans->map(fn ($p) => [
            'user'             => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name] : null,
            'status'           => $p->status?->value,
            'final_score'      => $p->evaluation?->final_score !== null ? (float) $p->evaluation->final_score : null,
            'predicate'        => $p->evaluation?->predicate?->value,
            'indicators_count' => $p->indicators_count,
        ]);

        return Inertia::render('Performance/Analytics', [
            'stats' => [
                'total_plans'            => $plans->count(),
                'avg_final_score'        => $avgScore ? round($avgScore, 2) : null,
                'predicate_distribution' => $predicateDistribution,
            ],
            'members' => $members,
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SkpPlan::class);

        if (! auth()->user()->hasAnyPermission(['performance.view.all', 'admin.organizations.view'])) {
            abort(403);
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'year'       => ['required', 'integer', 'min:2020', 'max:2100'],
            'semester'   => ['nullable', 'integer', 'min:1', 'max:2'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $this->skpService->createPeriod($data, auth()->user());

        return back()->with('success', 'Periode SKP berhasil dibuat.');
    }
}
