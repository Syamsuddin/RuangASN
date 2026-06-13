<?php

namespace App\Services;

use App\Enums\AiAgentType;
use App\Enums\AuditAction;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\User;
use App\Services\Ai\AiOrchestratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    public function create(array $data, User $author): Report
    {
        return DB::transaction(function () use ($data, $author) {
            $report = Report::create([
                ...$data,
                'organization_id' => $author->organization_id,
                'pemda_id'        => $author->pemda_id,
                'author_id'       => $author->id,
                'created_by'      => $author->id,
                'status'          => ReportStatus::DRAFT->value,
                'data_sources'    => $data['data_sources'] ?? [],
            ]);

            $this->outbox->publish('report.created', $report->fresh()->toArray(), 'Report', $report->id);

            return $report->fresh();
        });
    }

    public function update(Report $report, array $data): Report
    {
        return DB::transaction(function () use ($report, $data) {
            $report->update($data);
            $this->outbox->publish('report.updated', $report->fresh()->toArray(), 'Report', $report->id);

            return $report->fresh();
        });
    }

    public function transition(
        Report $report,
        ReportStatus $new,
        User $actor,
        ?string $notes = null
    ): Report {
        return DB::transaction(function () use ($report, $new, $actor, $notes) {
            if (! $report->canTransitionTo($new, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat beralih dari status {$report->status->value} ke {$new->value}.",
                ]);
            }

            $from = $report->status->value;

            ReportStatusHistory::create([
                'id'          => (string) Str::ulid(),
                'report_id'   => $report->id,
                'from_status' => $from,
                'to_status'   => $new->value,
                'changed_by'  => $actor->id,
                'notes'       => $notes,
            ]);

            $extra = [];
            if ($new === ReportStatus::SUBMITTED) {
                $extra['submitted_at'] = now();
            }
            if ($new === ReportStatus::APPROVED) {
                $extra['approved_by'] = $actor->id;
                $extra['approved_at'] = now();
            }
            if ($new === ReportStatus::PUBLISHED) {
                $extra['published_at'] = now();
            }

            $report->update(array_merge(['status' => $new->value], $extra));

            $this->outbox->publish('report.status_changed', [
                'report_id'       => $report->id,
                'organization_id' => $report->organization_id,
                'from_status'     => $from,
                'to_status'       => $new->value,
                'changed_by'      => $actor->id,
            ], 'Report', $report->id);

            $this->audit->log(
                AuditAction::STATUS_CHANGED,
                'Report',
                $report->id,
                ['status' => $from],
                ['status' => $new->value],
            );

            return $report->fresh();
        });
    }

    public function submit(Report $report, User $actor, ?string $notes = null): Report
    {
        return $this->transition($report, ReportStatus::SUBMITTED, $actor, $notes);
    }

    public function approve(Report $report, User $actor, ?string $notes = null): Report
    {
        return $this->transition($report, ReportStatus::APPROVED, $actor, $notes);
    }

    public function reject(Report $report, User $actor, string $notes): Report
    {
        return $this->transition($report, ReportStatus::REJECTED, $actor, $notes);
    }

    public function publish(Report $report, User $actor, ?string $notes = null): Report
    {
        return $this->transition($report, ReportStatus::PUBLISHED, $actor, $notes);
    }

    /**
     * Generate the AI draft via the ReportAgent (deterministic with the fake
     * provider) and store it in `ai_draft` for HUMAN review. The draft is never
     * written to `content` (AXIOM-04) — the author copies + edits it.
     *
     * @param User|null $actor The user the agent acts as; defaults to the
     *                         report author (who can always view the report).
     */
    public function generateAiDraft(Report $report, ?User $actor = null): Report
    {
        return DB::transaction(function () use ($report, $actor) {
            if ($actor === null) {
                /** @var User|null $author */
                $author = $report->author;
                $actor  = $author;
            }

            $draft = null;
            if ($actor !== null) {
                $draft = app(AiOrchestratorService::class)->generateDraft(
                    AiAgentType::REPORT,
                    $actor,
                    'Susun draft laporan ' . $report->title,
                    ['context_type' => 'report', 'context_id' => $report->id],
                );
            }

            if ($draft === null || trim(strip_tags($draft)) === '') {
                // Defensive fallback so ai_draft is never empty.
                $type  = $report->report_type->value ?? 'laporan';
                $start = $report->period_start_date?->format('d/m/Y') ?? '-';
                $end   = $report->period_end_date?->format('d/m/Y') ?? '-';
                $draft = "<h2>{$report->title}</h2><p>Draft laporan {$type} periode {$start}–{$end}.</p>";
            }

            $report->update(['ai_draft' => $draft]);

            $this->outbox->publish('report.ai_draft_generated', [
                'report_id'       => $report->id,
                'organization_id' => $report->organization_id,
            ], 'Report', $report->id);

            return $report->fresh();
        });
    }
}
