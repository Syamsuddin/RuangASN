<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Report Agent. Given context_type='report' + context_id, loads the Report
 * (authorized via the user's own 'view' policy) and produces a report DRAFT
 * from the report's period + linked data (tasks/meetings within the period
 * range, scoped to the user's organization & visibility).
 *
 * Deterministic: assembled from actual data, no randomness/time-dependence.
 * The draft is returned for human review and is NEVER auto-saved to `content`
 * (AXIOM-04) — it lands in `reports.ai_draft` for the author to copy + edit.
 */
class ReportAgent extends BaseAgent implements DraftingAgent
{
    public function type(): AiAgentType
    {
        return AiAgentType::REPORT;
    }

    protected function role(): string
    {
        return 'Anda adalah AI Report Agent RuangASN yang menyusun draft laporan dari periode '
            . 'dan sumber data terkait (tugas & rapat) untuk ditinjau dan diedit penulis.';
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(User $user, array $ctx): array
    {
        $report = $this->resolveReport($user, $ctx);
        if ($report === null) {
            return [];
        }

        return [[
            'role'    => 'system',
            'content' => "Data laporan untuk penyusunan draft:\n" . $this->reportFacts($user, $report),
        ]];
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function draft(User $user, string $content, array $ctx): ?string
    {
        $report = $this->resolveReport($user, $ctx);
        if ($report === null) {
            return null;
        }

        return $this->renderReport($user, $report);
    }

    /**
     * Load the report and authorize 'view' through the user's OWN policy.
     *
     * @param array<string, mixed> $ctx
     */
    private function resolveReport(User $user, array $ctx): ?Report
    {
        if (($ctx['context_type'] ?? null) !== 'report' || empty($ctx['context_id'])) {
            return null;
        }

        /** @var Report|null $report */
        $report = Report::query()->with('author:id,name')->find($ctx['context_id']);
        if ($report === null) {
            return null;
        }

        Gate::forUser($user)->authorize('view', $report);

        return $report;
    }

    private function reportFacts(User $user, Report $report): string
    {
        $type  = $report->report_type->value ?? 'laporan';
        $start = $report->period_start_date?->toDateString() ?? '-';
        $end   = $report->period_end_date?->toDateString() ?? '-';

        $lines = [
            "Judul: {$report->title}",
            "Jenis: {$type}",
            "Periode: {$start} s/d {$end}",
            'Jumlah tugas dalam periode: ' . $this->tasksInPeriod($user, $report)->count(),
            'Jumlah rapat dalam periode: ' . $this->meetingsInPeriod($user, $report)->count(),
        ];

        $sources = $report->data_sources ?? [];
        if ($sources !== []) {
            $lines[] = 'Sumber data: ' . implode('; ', array_map('strval', $sources));
        }

        return implode("\n", $lines);
    }

    /**
     * Render a structured report draft. Deterministic from report + period data.
     */
    private function renderReport(User $user, Report $report): string
    {
        $type  = $report->report_type->value ?? 'laporan';
        $start = $report->period_start_date?->format('d/m/Y') ?? '-';
        $end   = $report->period_end_date?->format('d/m/Y') ?? '-';

        $tasks    = $this->tasksInPeriod($user, $report);
        $meetings = $this->meetingsInPeriod($user, $report);

        $out   = [];
        $out[] = '<h2>' . e($report->title) . '</h2>';
        $out[] = '<p><strong>Jenis:</strong> ' . e($type)
            . ' &middot; <strong>Periode:</strong> ' . e($start) . ' – ' . e($end) . '</p>';

        // Pendahuluan.
        $out[] = '<h3>Pendahuluan</h3>';
        $out[] = '<p>Laporan ' . e($type) . ' ini menyajikan ringkasan pelaksanaan kegiatan '
            . 'pada periode ' . e($start) . ' hingga ' . e($end) . ', mencakup '
            . $tasks->count() . ' tugas dan ' . $meetings->count() . ' rapat.</p>';

        // Ringkasan kegiatan (tugas).
        $out[] = '<h3>Ringkasan Tugas</h3>';
        if ($tasks->isNotEmpty()) {
            $items = $tasks->take(20)
                ->map(fn (Task $t) => '<li>' . e((string) $t->title)
                    . ' (' . e($t->status->value) . ')</li>')
                ->implode('');
            $out[] = '<ul>' . $items . '</ul>';
        } else {
            $out[] = '<p><em>Tidak ada tugas tercatat pada periode ini.</em></p>';
        }

        // Ringkasan rapat.
        $out[] = '<h3>Ringkasan Rapat</h3>';
        if ($meetings->isNotEmpty()) {
            $items = $meetings->take(20)
                ->map(fn (Meeting $m) => '<li>' . e((string) $m->title)
                    . ' — ' . e($m->scheduled_at?->toDateString() ?? '-') . '</li>')
                ->implode('');
            $out[] = '<ul>' . $items . '</ul>';
        } else {
            $out[] = '<p><em>Tidak ada rapat tercatat pada periode ini.</em></p>';
        }

        // Penutup.
        $out[] = '<h3>Penutup</h3>';
        $out[] = '<p>Demikian draft laporan ini disusun sebagai bahan untuk ditinjau dan '
            . 'disempurnakan oleh penulis sebelum diajukan.</p>';

        return implode("\n", $out);
    }

    /**
     * Tasks within the report period, scoped to the report's organization
     * (the global BelongsToOrganization scope keeps this tenant-safe).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Task>
     */
    private function tasksInPeriod(User $user, Report $report): \Illuminate\Database\Eloquent\Collection
    {
        $query = Task::query()->where('organization_id', $report->organization_id);

        if ($report->period_start_date) {
            $query->where('created_at', '>=', $report->period_start_date->copy()->startOfDay());
        }
        if ($report->period_end_date) {
            $query->where('created_at', '<=', $report->period_end_date->copy()->endOfDay());
        }

        return $query->orderBy('created_at')->limit(50)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Meeting>
     */
    private function meetingsInPeriod(User $user, Report $report): \Illuminate\Database\Eloquent\Collection
    {
        $query = Meeting::query()->where('organization_id', $report->organization_id);

        if ($report->period_start_date) {
            $query->where('scheduled_at', '>=', $report->period_start_date->copy()->startOfDay());
        }
        if ($report->period_end_date) {
            $query->where('scheduled_at', '<=', $report->period_end_date->copy()->endOfDay());
        }

        return $query->orderBy('scheduled_at')->limit(50)->get();
    }
}
