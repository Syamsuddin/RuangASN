<?php

namespace Tests\Feature\Performance;

use App\Enums\PerformancePredicate;
use App\Enums\SkpPerspective;
use App\Models\SkpEvaluation;
use App\Models\SkpIndicator;
use App\Models\SkpPlan;
use App\Services\SkpCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit-style tests for SkpCalculationService — the critical SKP formula gate.
 *
 * Formula (PermenPANRB 6/2022):
 *   1. indicatorAchievement = realization / target × 100
 *   2. performanceScore     = Σ(achievement × weight) / Σ(weight), CAPPED 120
 *   3. behaviorScore        = avg of non-null dimensions (0-120 scale)
 *   4. finalScore           = 0.7 × perf + 0.3 × behavior
 *   5. predicate: ≥90→SANGAT_BAIK, ≥76→BAIK, ≥61→CUKUP, ≥51→KURANG, <51→SANGAT_KURANG
 */
class SkpCalculationTest extends TestCase
{
    use RefreshDatabase;

    private SkpCalculationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(SkpCalculationService::class);
    }

    // ── Helper ──────────────────────────────────────────────────────────────

    private function makeIndicator(float $target, float $realization, float $weight = 100): SkpIndicator
    {
        /** @var SkpIndicator $ind */
        $ind = new SkpIndicator();
        $ind->id               = (string) Str::ulid();
        $ind->target_value     = $target;
        $ind->realization_value = $realization;
        $ind->achievement_pct  = $target > 0 ? round($realization / $target * 100, 2) : 0.0;
        $ind->weight           = $weight;
        $ind->perspective      = SkpPerspective::PENERIMA_LAYANAN;
        $ind->name             = 'Test Indicator';
        $ind->target_unit      = 'dokumen';
        $ind->sort_order       = 0;
        $ind->skp_plan_id      = (string) Str::ulid();
        return $ind;
    }

    private function makeEvaluation(
        ?float $service,
        ?float $commit,
        ?float $initiative,
        ?float $teamwork,
        ?float $leadership = null
    ): SkpEvaluation {
        /** @var SkpEvaluation $eval */
        $eval = new SkpEvaluation();
        $eval->id                   = (string) Str::ulid();
        $eval->skp_plan_id          = (string) Str::ulid();
        $eval->behavior_service     = $service;
        $eval->behavior_commit      = $commit;
        $eval->behavior_initiative  = $initiative;
        $eval->behavior_teamwork    = $teamwork;
        $eval->behavior_leadership  = $leadership;
        return $eval;
    }

    // ── 1. indicatorAchievement ──────────────────────────────────────────

    public function test_indicator_achievement_basic(): void
    {
        $ind = $this->makeIndicator(target: 10, realization: 8);
        $this->assertEqualsWithDelta(80.0, $this->svc->indicatorAchievement($ind), 0.001);
    }

    public function test_indicator_achievement_exact_100(): void
    {
        $ind = $this->makeIndicator(target: 50, realization: 50);
        $this->assertEqualsWithDelta(100.0, $this->svc->indicatorAchievement($ind), 0.001);
    }

    public function test_indicator_achievement_over_100(): void
    {
        // Over-performance: 120% achievable
        $ind = $this->makeIndicator(target: 10, realization: 12);
        $this->assertEqualsWithDelta(120.0, $this->svc->indicatorAchievement($ind), 0.001);
    }

    public function test_indicator_achievement_capped_at_120(): void
    {
        // 200% raw achievement must be capped at 120 (PermenPANRB IKI ceiling).
        $ind = $this->makeIndicator(target: 10, realization: 20);
        $this->assertEqualsWithDelta(120.0, $this->svc->indicatorAchievement($ind), 0.001);
    }

    public function test_indicator_achievement_target_zero_returns_zero(): void
    {
        $ind = $this->makeIndicator(target: 0, realization: 5);
        $this->assertEqualsWithDelta(0.0, $this->svc->indicatorAchievement($ind), 0.001);
    }

    // ── 2. performanceScore ───────────────────────────────────────────────

    public function test_performance_score_simple_equal_weights(): void
    {
        // Two indicators, both 80% achievement, weight 100 each → avg 80%
        $plan = SkpPlan::factory()->create();

        // Insert indicators via DB so performanceScore() can query them
        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PENERIMA_LAYANAN->value,
            'name'              => 'Ind A',
            'target_value'      => 10,
            'target_unit'       => 'dok',
            'weight'            => 100,
            'realization_value' => 8,
            'achievement_pct'   => 80.0,
            'sort_order'        => 0,
        ]);
        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PROSES_BISNIS->value,
            'name'              => 'Ind B',
            'target_value'      => 20,
            'target_unit'       => 'dok',
            'weight'            => 100,
            'realization_value' => 16,
            'achievement_pct'   => 80.0,
            'sort_order'        => 1,
        ]);

        $score = $this->svc->performanceScore($plan);
        $this->assertEqualsWithDelta(80.0, $score, 0.01);
    }

    public function test_performance_score_capped_at_120(): void
    {
        $plan = SkpPlan::factory()->create();

        // All indicators at 150% achievement → would average 150 but must be capped at 120
        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PENERIMA_LAYANAN->value,
            'name'              => 'Ind High',
            'target_value'      => 10,
            'target_unit'       => 'dok',
            'weight'            => 100,
            'realization_value' => 15,
            'achievement_pct'   => 150.0,
            'sort_order'        => 0,
        ]);

        $score = $this->svc->performanceScore($plan);
        $this->assertEqualsWithDelta(120.0, $score, 0.001);
    }

    public function test_performance_score_empty_indicators_returns_zero(): void
    {
        $plan = SkpPlan::factory()->create();
        $score = $this->svc->performanceScore($plan);
        $this->assertEqualsWithDelta(0.0, $score, 0.001);
    }

    public function test_performance_score_weighted_average(): void
    {
        $plan = SkpPlan::factory()->create();

        // Ind A: weight 60, achievement 100  → contributes 6000
        // Ind B: weight 40, achievement 50   → contributes 2000
        // Weighted avg = 8000 / 100 = 80.0
        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PENERIMA_LAYANAN->value,
            'name'              => 'Ind A',
            'target_value'      => 10,
            'target_unit'       => 'dok',
            'weight'            => 60,
            'realization_value' => 10,
            'achievement_pct'   => 100.0,
            'sort_order'        => 0,
        ]);
        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PROSES_BISNIS->value,
            'name'              => 'Ind B',
            'target_value'      => 10,
            'target_unit'       => 'dok',
            'weight'            => 40,
            'realization_value' => 5,
            'achievement_pct'   => 50.0,
            'sort_order'        => 1,
        ]);

        $score = $this->svc->performanceScore($plan);
        $this->assertEqualsWithDelta(80.0, $score, 0.01);
    }

    public function test_performance_score_rederives_from_realization_ignoring_stale_pct(): void
    {
        // FIX 6: stored achievement_pct is stale/wrong (999), but the true
        // achievement from realization/target is 70%. performanceScore() must
        // re-derive from realization_value/target_value, NOT trust the column.
        $plan = SkpPlan::factory()->create();

        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PENERIMA_LAYANAN->value,
            'name'              => 'Stale Ind',
            'target_value'      => 10,
            'target_unit'       => 'dok',
            'weight'            => 100,
            'realization_value' => 7,
            'achievement_pct'   => 999.0, // deliberately wrong / stale
            'sort_order'        => 0,
        ]);

        $score = $this->svc->performanceScore($plan);
        $this->assertEqualsWithDelta(70.0, $score, 0.01);
    }

    public function test_performance_score_excludes_zero_target_indicators(): void
    {
        // FIX 6: a target_value = 0 indicator must NOT dilute the average.
        // Only the valid 80% indicator should count → score = 80.0.
        $plan = SkpPlan::factory()->create();

        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PENERIMA_LAYANAN->value,
            'name'              => 'Valid Ind',
            'target_value'      => 10,
            'target_unit'       => 'dok',
            'weight'            => 100,
            'realization_value' => 8,
            'achievement_pct'   => 80.0,
            'sort_order'        => 0,
        ]);
        SkpIndicator::create([
            'id'                => (string) Str::ulid(),
            'skp_plan_id'       => $plan->id,
            'perspective'       => SkpPerspective::PROSES_BISNIS->value,
            'name'              => 'Zero Target Ind',
            'target_value'      => 0,
            'target_unit'       => 'dok',
            'weight'            => 100,
            'realization_value' => 5,
            'achievement_pct'   => 0.0,
            'sort_order'        => 1,
        ]);

        $score = $this->svc->performanceScore($plan);
        $this->assertEqualsWithDelta(80.0, $score, 0.01);
    }

    // ── 3. behaviorScore ──────────────────────────────────────────────────

    public function test_behavior_score_averages_5_dimensions(): void
    {
        $eval = $this->makeEvaluation(90, 80, 85, 75, 95);
        $expected = round((90 + 80 + 85 + 75 + 95) / 5, 2);
        $this->assertEqualsWithDelta($expected, $this->svc->behaviorScore($eval), 0.01);
    }

    public function test_behavior_score_averages_4_without_leadership(): void
    {
        $eval = $this->makeEvaluation(90, 80, 85, 75, null);
        $expected = round((90 + 80 + 85 + 75) / 4, 2);
        $this->assertEqualsWithDelta($expected, $this->svc->behaviorScore($eval), 0.01);
    }

    public function test_behavior_score_all_same(): void
    {
        $eval = $this->makeEvaluation(100, 100, 100, 100, 100);
        $this->assertEqualsWithDelta(100.0, $this->svc->behaviorScore($eval), 0.001);
    }

    // ── 4. finalScore ─────────────────────────────────────────────────────

    public function test_final_score_formula(): void
    {
        // 0.7 × 80 + 0.3 × 90 = 56 + 27 = 83
        $final = $this->svc->finalScore(80.0, 90.0);
        $this->assertEqualsWithDelta(83.0, $final, 0.001);
    }

    public function test_final_score_zero_behavior(): void
    {
        $final = $this->svc->finalScore(100.0, 0.0);
        $this->assertEqualsWithDelta(70.0, $final, 0.001);
    }

    public function test_final_score_rounded_2dp(): void
    {
        // 0.7 × 77.33 + 0.3 × 83.67 = 54.131 + 25.101 = 79.232 → 79.23
        $final = $this->svc->finalScore(77.33, 83.67);
        $this->assertEqualsWithDelta(79.23, $final, 0.005);
    }

    // ── 5. predicate thresholds ───────────────────────────────────────────

    public function test_predicate_sangat_baik_at_90(): void
    {
        $this->assertEquals(PerformancePredicate::SANGAT_BAIK, $this->svc->predicate(90.0));
    }

    public function test_predicate_sangat_baik_above_90(): void
    {
        $this->assertEquals(PerformancePredicate::SANGAT_BAIK, $this->svc->predicate(110.0));
    }

    public function test_predicate_baik_at_89(): void
    {
        $this->assertEquals(PerformancePredicate::BAIK, $this->svc->predicate(89.0));
    }

    public function test_predicate_baik_at_76(): void
    {
        $this->assertEquals(PerformancePredicate::BAIK, $this->svc->predicate(76.0));
    }

    public function test_predicate_cukup_at_75(): void
    {
        $this->assertEquals(PerformancePredicate::CUKUP, $this->svc->predicate(75.0));
    }

    public function test_predicate_cukup_at_61(): void
    {
        $this->assertEquals(PerformancePredicate::CUKUP, $this->svc->predicate(61.0));
    }

    public function test_predicate_kurang_at_60(): void
    {
        $this->assertEquals(PerformancePredicate::KURANG, $this->svc->predicate(60.0));
    }

    public function test_predicate_kurang_at_51(): void
    {
        $this->assertEquals(PerformancePredicate::KURANG, $this->svc->predicate(51.0));
    }

    public function test_predicate_sangat_kurang_at_50(): void
    {
        $this->assertEquals(PerformancePredicate::SANGAT_KURANG, $this->svc->predicate(50.0));
    }

    public function test_predicate_sangat_kurang_at_zero(): void
    {
        $this->assertEquals(PerformancePredicate::SANGAT_KURANG, $this->svc->predicate(0.0));
    }
}
