<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PerformancePredicate;
use App\Models\SkpEvaluation;
use App\Models\SkpIndicator;
use App\Models\SkpPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SkpCalculationService — implements PermenPANRB 6/2022 scoring formulas.
 *
 * FORMULA OVERVIEW
 * ────────────────
 * 1. Indicator achievement  = (realization_value / target_value) × 100
 *    Explicitly CAPPED at 120.0 (PermenPANRB 6/2022 caps IKI achievement so a
 *    single over-performing indicator cannot inflate the score). If
 *    target_value ≤ 0 → returns 0.0 (guard division by zero).
 *
 * 2. Performance score      = Σ(achievement × weight) / Σ(weight)
 *    Weighted average of all indicator achievements, where each achievement is
 *    RE-DERIVED from realization_value/target_value at calculation time (the
 *    stored achievement_pct is never trusted, so stale data cannot skew the
 *    score). Indicators with target_value ≤ 0 are EXCLUDED entirely so they
 *    cannot dilute the average. The overall result is also CAPPED at 120.0.
 *    No (eligible) indicators → 0.0.
 *
 * 3. Behavior score         = average of non-null behavior dimension scores.
 *    Dimensions: service, commit, initiative, teamwork, [leadership optional].
 *    All dimensions on 0-120 scale.
 *
 * 4. Final score            = 0.7 × performance_score + 0.3 × behavior_score
 *    Rounded to 2 decimal places.
 *
 * 5. Predicate thresholds:
 *    ≥ 90   → SANGAT_BAIK
 *    ≥ 76   → BAIK
 *    ≥ 61   → CUKUP
 *    ≥ 51   → KURANG
 *    < 51   → SANGAT_KURANG
 */
class SkpCalculationService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    /**
     * Maximum achievement percentage for a single indicator and for the overall
     * performance score (PermenPANRB 6/2022 caps IKI achievement at 120%).
     */
    private const ACHIEVEMENT_CAP = 120.0;

    /**
     * Calculate achievement percentage for a single indicator.
     * Formula: realization_value / target_value × 100, rounded 2dp,
     * then CAPPED at 120.0 (PermenPANRB 6/2022 IKI achievement ceiling).
     * If target_value ≤ 0 returns 0.0 (cannot divide by zero).
     */
    public function indicatorAchievement(SkpIndicator $indicator): float
    {
        $target = (float) $indicator->target_value;
        if ($target <= 0) {
            return 0.0;
        }

        $achievement = round((float) $indicator->realization_value / $target * 100, 2);

        return min($achievement, self::ACHIEVEMENT_CAP);
    }

    /**
     * Compute performance score for all indicators in a plan.
     *
     * Each indicator's achievement is RE-DERIVED from realization_value/
     * target_value via indicatorAchievement() at calculation time — the stored
     * achievement_pct column is never trusted, so stale/incorrect data cannot
     * skew the score. Indicators with target_value ≤ 0 are EXCLUDED so they
     * cannot dilute the weighted average. Result CAPPED at 120.0.
     * No eligible indicators → 0.0.
     */
    public function performanceScore(SkpPlan $plan): float
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, SkpIndicator> $indicators */
        $indicators = $plan->indicators()->get();

        if ($indicators->isEmpty()) {
            return 0.0;
        }

        $weightedSum  = 0.0;
        $totalWeight  = 0.0;

        foreach ($indicators as $ind) {
            // Exclude indicators with a non-positive target — they have no
            // meaningful achievement and must not dilute the average.
            if ((float) $ind->target_value <= 0) {
                continue;
            }

            $weight       = (float) $ind->weight;
            $achievement  = $this->indicatorAchievement($ind);
            $weightedSum += $achievement * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return 0.0;
        }

        $score = round($weightedSum / $totalWeight, 2);

        // Cap at 120.0 per PermenPANRB 6/2022
        return min($score, self::ACHIEVEMENT_CAP);
    }

    /**
     * Compute behavior score from evaluation dimension columns.
     * Averages only non-null dimensions (leadership is optional for non-leaders).
     * All dimensions on 0-120 scale.
     *
     * If ALL dimensions are null there is no behavior score to compute; returning
     * 0.0 would silently deflate final_score, so we fail loudly instead (O3). The
     * HTTP path is unaffected — the controller requires the 4 core dimensions.
     *
     * @throws \InvalidArgumentException when no behavior dimension is provided.
     */
    public function behaviorScore(SkpEvaluation $eval): float
    {
        $dimensions = array_filter([
            $eval->behavior_service    !== null ? (float) $eval->behavior_service    : null,
            $eval->behavior_commit     !== null ? (float) $eval->behavior_commit     : null,
            $eval->behavior_initiative !== null ? (float) $eval->behavior_initiative : null,
            $eval->behavior_teamwork   !== null ? (float) $eval->behavior_teamwork   : null,
            $eval->behavior_leadership !== null ? (float) $eval->behavior_leadership : null,
        ], fn ($v) => $v !== null);

        if (count($dimensions) === 0) {
            throw new \InvalidArgumentException(
                'behaviorScore membutuhkan minimal satu dimensi perilaku non-null.'
            );
        }

        return round(array_sum($dimensions) / count($dimensions), 2);
    }

    /**
     * Final score formula per PermenPANRB 6/2022:
     * final = 0.7 × performance_score + 0.3 × behavior_score
     * Rounded to 2 decimal places.
     */
    public function finalScore(float $performance, float $behavior): float
    {
        return round(0.7 * $performance + 0.3 * $behavior, 2);
    }

    /**
     * Predicate thresholds per PermenPANRB 6/2022 §...:
     * ≥ 90   → SANGAT_BAIK
     * ≥ 76   → BAIK
     * ≥ 61   → CUKUP
     * ≥ 51   → KURANG
     * < 51   → SANGAT_KURANG
     */
    public function predicate(float $finalScore): PerformancePredicate
    {
        return match (true) {
            $finalScore >= 90 => PerformancePredicate::SANGAT_BAIK,
            $finalScore >= 76 => PerformancePredicate::BAIK,
            $finalScore >= 61 => PerformancePredicate::CUKUP,
            $finalScore >= 51 => PerformancePredicate::KURANG,
            default           => PerformancePredicate::SANGAT_KURANG,
        };
    }

    /**
     * Full evaluate: compute all scores, upsert SkpEvaluation, publish event + audit.
     *
     * @param array<string, float|null> $behaviorScores Keys: service, commit, initiative, teamwork, leadership
     */
    public function evaluate(
        SkpPlan $plan,
        array $behaviorScores,
        ?string $feedback,
        User $evaluator
    ): SkpEvaluation {
        return DB::transaction(function () use ($plan, $behaviorScores, $feedback, $evaluator) {
            // Build temp evaluation object to reuse behaviorScore()
            /** @var SkpEvaluation $tempEval */
            $tempEval = new SkpEvaluation([
                'behavior_service'    => $behaviorScores['service']    ?? null,
                'behavior_commit'     => $behaviorScores['commit']     ?? null,
                'behavior_initiative' => $behaviorScores['initiative'] ?? null,
                'behavior_teamwork'   => $behaviorScores['teamwork']   ?? null,
                'behavior_leadership' => $behaviorScores['leadership'] ?? null,
            ]);

            $perfScore     = $this->performanceScore($plan);
            $behavScore    = $this->behaviorScore($tempEval);
            $final         = $this->finalScore($perfScore, $behavScore);
            $pred          = $this->predicate($final);

            $evaluation = SkpEvaluation::updateOrCreate(
                ['skp_plan_id' => $plan->id],
                [
                    'performance_score'   => $perfScore,
                    'behavior_score'      => $behavScore,
                    'final_score'         => $final,
                    'predicate'           => $pred->value,
                    'behavior_service'    => $behaviorScores['service']    ?? null,
                    'behavior_commit'     => $behaviorScores['commit']     ?? null,
                    'behavior_initiative' => $behaviorScores['initiative'] ?? null,
                    'behavior_teamwork'   => $behaviorScores['teamwork']   ?? null,
                    'behavior_leadership' => $behaviorScores['leadership'] ?? null,
                    'superior_feedback'   => $feedback,
                    'evaluated_by'        => $evaluator->id,
                    'evaluated_at'        => now(),
                ]
            );

            $this->outbox->publish('skp.evaluated', [
                'skp_plan_id'      => $plan->id,
                'organization_id'  => $plan->organization_id,
                'final_score'      => $final,
                'predicate'        => $pred->value,
                'evaluated_by'     => $evaluator->id,
            ], 'SkpPlan', $plan->id);

            $this->audit->log(
                AuditAction::STATUS_CHANGED,
                'SkpPlan',
                $plan->id,
                [],
                ['evaluated_by' => $evaluator->id, 'final_score' => $final, 'predicate' => $pred->value],
            );

            return $evaluation;
        });
    }
}
