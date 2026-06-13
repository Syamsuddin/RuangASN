<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;
use App\Models\SkpIndicator;
use App\Models\SkpPlan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Performance Agent. Answers SKP/kinerja questions from the user's own SkpPlan
 * + indicators (read-only, authorized). Context is the user's most relevant
 * plan(s); each plan is authorized through the user's own 'view' policy so the
 * AI never sees a plan the user couldn't see themselves (AXIOM-08).
 */
class PerformanceAgent extends BaseAgent
{
    public function type(): AiAgentType
    {
        return AiAgentType::PERFORMANCE;
    }

    protected function role(): string
    {
        return 'Anda adalah AI Performance Agent RuangASN yang membantu menganalisis kinerja/SKP '
            . 'berdasarkan rencana SKP dan indikator milik pengguna (hanya baca).';
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(User $user, array $ctx): array
    {
        // The user's own plans (owner scope), authorized individually.
        $plans = SkpPlan::query()
            ->where('user_id', $user->id)
            ->with(['indicators', 'period'])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->filter(fn (SkpPlan $plan) => Gate::forUser($user)->allows('view', $plan));

        if ($plans->isEmpty()) {
            return [];
        }

        $blocks = $plans->map(function (SkpPlan $plan) {
            $period     = $plan->period;
            $periodName = $period->name ?? 'periode tidak diketahui';
            $lines = [
                'Rencana SKP (' . $periodName . '), status: ' . $plan->status->value,
            ];
            /** @var SkpIndicator $ind */
            foreach ($plan->indicators as $ind) {
                $lines[] = sprintf(
                    '- %s: target %s %s, realisasi %s, capaian %s%%',
                    $ind->name,
                    (string) $ind->target_value,
                    (string) $ind->target_unit,
                    $ind->realization_value !== null ? (string) $ind->realization_value : '-',
                    $ind->achievement_pct !== null ? (string) $ind->achievement_pct : '-',
                );
            }

            return implode("\n", $lines);
        })->implode("\n\n");

        return [[
            'role'    => 'system',
            'content' => "Data kinerja/SKP pengguna (gunakan untuk menjawab):\n" . $blocks,
        ]];
    }
}
