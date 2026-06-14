<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

/**
 * Workload Agent (Phase 4, Sprint 19-20). Analyzes the user's OWN task load —
 * open / in-progress / overdue counts and a status distribution — and offers
 * deterministic advice.
 *
 * Authorized to the user's own scope only: it counts tasks the user created or
 * is assigned, within the org global scope (BelongsToOrganization). Read-only;
 * never mutates. Fully derived from counts, so the output is deterministic — it
 * also drafts that analysis as the assistant content (DraftingAgent).
 */
class WorkloadAgent extends BaseAgent implements DraftingAgent
{
    /** @var array<int, string> */
    private const TERMINAL = [
        TaskStatus::COMPLETED->value,
        TaskStatus::CANCELLED->value,
        TaskStatus::CLOSED->value,
        TaskStatus::ARCHIVED->value,
    ];

    public function type(): AiAgentType
    {
        return AiAgentType::WORKLOAD;
    }

    protected function role(): string
    {
        return 'Anda adalah AI Workload Agent RuangASN yang menganalisis beban kerja '
            . 'dan distribusi tugas pengguna (hanya baca).';
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(User $user, array $ctx): array
    {
        return [[
            'role'    => 'system',
            'content' => "Data beban kerja pengguna (gunakan untuk menjawab):\n" . $this->analyze($user),
        ]];
    }

    /**
     * Deterministic workload analysis as the assistant content for human review.
     *
     * @param array<string, mixed> $ctx
     */
    public function draft(User $user, string $content, array $ctx): ?string
    {
        return $this->analyze($user);
    }

    private function analyze(User $user): string
    {
        // The user's own tasks (created or assigned), within the org scope.
        $owned = fn () => Task::query()->where(fn ($q) => $q
            ->where('creator_id', $user->id)
            ->orWhere('assignee_id', $user->id));

        $total      = $owned()->count();
        $open       = $owned()->whereNotIn('status', self::TERMINAL)->count();
        $inProgress = $owned()->where('status', TaskStatus::IN_PROGRESS->value)->count();
        $overdue    = $owned()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->whereNotIn('status', self::TERMINAL)
            ->count();

        $lines = [
            'Analisis Beban Kerja:',
            sprintf('- Total tugas: %d (%d masih terbuka, %d sedang dikerjakan).', $total, $open, $inProgress),
            sprintf('- Tugas terlambat: %d.', $overdue),
        ];

        if ($overdue > 0) {
            $lines[] = 'Saran: prioritaskan penyelesaian tugas terlambat terlebih dahulu, '
                . 'lalu redistribusi atau delegasikan tugas terbuka bila beban terlalu tinggi.';
        } elseif ($open > 10) {
            $lines[] = 'Saran: beban tugas terbuka cukup tinggi; pertimbangkan delegasi atau penjadwalan ulang.';
        } else {
            $lines[] = 'Saran: beban kerja terkendali; pertahankan ritme dan selesaikan tugas berprioritas tinggi.';
        }

        return implode("\n", $lines);
    }
}
