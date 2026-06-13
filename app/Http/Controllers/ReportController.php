<?php

namespace App\Http\Controllers;

use App\Enums\DataClassification;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Report::class);

        $user  = $request->user();
        $query = Report::with(['author:id,name'])
            ->when(
                ! $user->hasPermissionTo('report.view.all'),
                fn ($q) => $q->where(function ($sq) use ($user) {
                    $sq->where('author_id', $user->id);
                    if ($user->hasPermissionTo('report.view.team')) {
                        $sq->orWhere('organization_id', $user->organization_id);
                    }
                })
            )
            ->when($request->type, fn ($q, $t) => $q->where('report_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Reports/Index', [
            'reports'  => $query->map(fn ($r) => $this->formatCard($r)),
            'filters'  => $request->only(['type', 'status', 'search']),
            'types'    => array_column(ReportType::cases(), 'value'),
            'statuses' => array_column(ReportStatus::cases(), 'value'),
        ]);
    }

    public function show(Report $report): Response
    {
        $this->authorize('view', $report);

        $report->load([
            'author:id,name',
            'approver:id,name',
            'statusHistories.changedBy:id,name',
        ]);

        $user = auth()->user();

        return Inertia::render('Reports/Show', [
            'report' => new \App\Http\Resources\ReportResource($report),
            'can'    => [
                'update'          => $user->can('update', $report),
                'submit'          => $user->can('submit', $report),
                'approve'         => $user->can('approve', $report),
                'reject'          => $user->can('approve', $report),
                'publish'         => $user->can('publish', $report),
                'generateAiDraft' => $user->can('generateAiDraft', $report),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Report::class);

        $data = $request->validate([
            'title'              => ['required', 'string', 'max:500'],
            'report_type'        => ['required', Rule::in(array_column(ReportType::cases(), 'value'))],
            'period_type'        => ['required', Rule::in(array_column(ReportPeriodType::cases(), 'value'))],
            'period_start_date'  => ['required', 'date'],
            'period_end_date'    => ['required', 'date', 'after_or_equal:period_start_date'],
            'data_classification'=> ['required', Rule::in([1, 2, 3, 4])],
            'content'            => ['nullable', 'string'],
            'data_sources'       => ['nullable', 'array'],
        ]);

        $report = $this->reportService->create($data, $request->user());

        return redirect()->route('reports.show', $report)
            ->with('success', 'Laporan berhasil dibuat.');
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('update', $report);

        $data = $request->validate([
            'title'              => ['sometimes', 'string', 'max:500'],
            'content'            => ['nullable', 'string'],
            'report_type'        => ['sometimes', Rule::in(array_column(ReportType::cases(), 'value'))],
            'period_start_date'  => ['sometimes', 'date'],
            'period_end_date'    => ['sometimes', 'date', 'after_or_equal:period_start_date'],
            'data_classification'=> ['sometimes', Rule::in([1, 2, 3, 4])],
            'data_sources'       => ['nullable', 'array'],
        ]);

        $this->reportService->update($report, $data);

        return back()->with('success', 'Laporan berhasil diperbarui.');
    }

    public function submit(Report $report): RedirectResponse
    {
        $this->authorize('submit', $report);

        $this->reportService->submit($report, auth()->user());

        return back()->with('success', 'Laporan berhasil diajukan.');
    }

    public function transition(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('approve', $report);

        $statusValues = array_column(ReportStatus::cases(), 'value');
        $data = $request->validate([
            'status' => ['required', Rule::in($statusValues)],
            'notes'  => ['nullable', 'string', Rule::requiredIf(
                in_array($request->status, ['revision', 'rejected'])
            )],
        ]);

        $new = ReportStatus::from($data['status']);
        $this->reportService->transition($report, $new, auth()->user(), $data['notes'] ?? null);

        return back()->with('success', 'Status laporan berhasil diubah.');
    }

    public function publish(Report $report): RedirectResponse
    {
        $this->authorize('publish', $report);

        $this->reportService->publish($report, auth()->user());

        return back()->with('success', 'Laporan berhasil dipublikasikan.');
    }

    public function generateAiDraft(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('generateAiDraft', $report);

        $this->reportService->generateAiDraft($report, $request->user());

        return back()->with('success', 'Draft AI berhasil digenerate.');
    }

    public function destroy(Report $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        $report->update(['deleted_by' => auth()->id()]);
        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Laporan berhasil dihapus.');
    }

    private function formatCard(Report $r): array
    {
        $level = $r->data_classification->value;

        $classificationLabels = [1 => 'Publik', 2 => 'Internal', 3 => 'Rahasia', 4 => 'Sangat Rahasia'];

        /** @var \App\Models\User|null $author */
        $author = $r->author;

        return [
            'id'                   => $r->id,
            'title'                => $r->title,
            'report_type'          => $r->report_type,
            'period_type'          => $r->period_type,
            'status'               => $r->status,
            'period_start_date'    => $r->period_start_date?->toDateString(),
            'period_end_date'      => $r->period_end_date?->toDateString(),
            'data_classification'  => $level,
            'classification_label' => $classificationLabels[$level],
            'updated_at'           => $r->updated_at?->toISOString(),
            'author'               => $author ? ['id' => $author->id, 'name' => $author->name] : null,
        ];
    }
}
