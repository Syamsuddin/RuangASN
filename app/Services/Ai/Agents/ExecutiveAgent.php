<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Gate;

/**
 * Executive Agent (Phase 4, Sprint 19-20). Produces a deterministic executive
 * brief ("Ringkasan Eksekutif") from the org's live AnalyticsService::current()
 * KPIs + recent trend.
 *
 * Read-only and authorized: the user MUST hold analytics.view.opd (checked via
 * the Gate) or the agent yields no executive context and never drafts (AXIOM-04
 * + AXIOM-08 — the AI inherits, never exceeds, the user's permissions). The
 * summary is fully derived from the metrics, so it is deterministic for tests.
 */
class ExecutiveAgent extends BaseAgent implements DraftingAgent
{
    public function __construct(private AnalyticsService $analytics) {}

    public function type(): AiAgentType
    {
        return AiAgentType::EXECUTIVE;
    }

    protected function role(): string
    {
        return 'Anda adalah AI Executive Agent RuangASN yang menyusun ringkasan eksekutif '
            . 'untuk pimpinan (Bupati / Kepala OPD) berdasarkan KPI agregat organisasi (hanya baca).';
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(User $user, array $ctx): array
    {
        if (! $this->authorized($user) || $user->organization === null) {
            return [];
        }

        $m       = $this->analytics->current($user->organization);
        $summary = $this->summarize($m);

        return [[
            'role'    => 'system',
            'content' => "Data KPI eksekutif organisasi (gunakan untuk menjawab):\n" . $summary,
        ]];
    }

    /**
     * Deterministic executive brief, or null when the user is not authorized for
     * the OPD-level view (orchestrator then falls back to the provider, which has
     * no executive context and so cannot leak figures).
     *
     * @param array<string, mixed> $ctx
     */
    public function draft(User $user, string $content, array $ctx): ?string
    {
        if (! $this->authorized($user) || $user->organization === null) {
            return null;
        }

        return $this->summarize($this->analytics->current($user->organization));
    }

    private function authorized(User $user): bool
    {
        return Gate::forUser($user)->allows('analytics.view.opd');
    }

    /**
     * @param array<string, mixed> $m
     */
    private function summarize(array $m): string
    {
        $tasks    = $m['tasks'] ?? [];
        $projects = $m['projects'] ?? [];
        $skp      = $m['skp'] ?? [];
        $meetings = $m['meetings'] ?? [];
        $docs     = $m['documents'] ?? [];

        $completed   = (int) ($tasks['completed'] ?? 0);
        $rate        = (float) ($tasks['completion_rate'] ?? 0);
        $overdue     = (int) ($tasks['overdue'] ?? 0);
        $activeProj  = (int) ($projects['active'] ?? 0);
        $avgSkp      = (float) ($skp['avg_final_score'] ?? 0);
        $meetingDone = (int) ($meetings['completed'] ?? 0);
        $docsPub     = (int) ($docs['published'] ?? 0);

        $lines = [
            'Ringkasan Eksekutif:',
            sprintf('- %d tugas selesai (%.1f%% tingkat penyelesaian).', $completed, $rate),
            sprintf('- %d proyek aktif berjalan.', $activeProj),
            sprintf('- Rata-rata nilai SKP organisasi: %.2f.', $avgSkp),
            sprintf('- %d rapat selesai, %d dokumen terbit.', $meetingDone, $docsPub),
        ];

        if ($overdue > 0) {
            $lines[] = sprintf('- Perhatian: %d tugas terlambat (overdue) memerlukan tindak lanjut.', $overdue);
        } else {
            $lines[] = '- Tidak ada tugas terlambat — seluruh pekerjaan on track.';
        }

        return implode("\n", $lines);
    }
}
