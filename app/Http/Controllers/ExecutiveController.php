<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\Ai\AiOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Executive Dashboard (Phase 4, Sprint 19-20). The Bupati / Kepala OPD view:
 * live KPIs, 30-day trend, OPD heatmap (pemda only) + an on-demand AI executive
 * brief.
 *
 * Gated by analytics.view.opd (OPD scope) OR analytics.view.pemda (cross-OPD).
 * Every metric is org-scoped via AnalyticsService (pinned to the user's org);
 * the cross-OPD heatmap is exposed ONLY to analytics.view.pemda holders.
 */
class ExecutiveController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user?->can('analytics.view.opd') || $user?->can('analytics.view.pemda'),
            403,
        );

        $org = $user->organization;
        abort_if($org === null, 404);

        // Cross-OPD comparison only for the pemda-scoped role.
        $opdComparison = $user->can('analytics.view.pemda')
            ? $this->analytics->opdComparison($org)
            : null;

        return Inertia::render('Executive/Index', [
            'current'         => $this->analytics->current($org),
            'trend'           => $this->analytics->trend($org, 30),
            'opdComparison'   => $opdComparison,
            'aiBriefAvailable' => true,
            'organization'    => [
                'id'   => $org->id,
                'name' => $org->name,
            ],
        ]);
    }

    /**
     * Generate the AI executive brief via the ExecutiveAgent (orchestrator routes
     * the EXECUTIVE intent to it). Returns the deterministic summary text. The
     * agent re-checks analytics.view.opd itself, but we gate the endpoint too.
     */
    public function aiBrief(Request $request, AiOrchestratorService $orchestrator): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can('analytics.view.opd'), 403);

        $message = $orchestrator->sendMessage(
            $user,
            null,
            'Buatkan ringkasan eksekutif untuk pimpinan.',
        );

        return response()->json([
            'brief' => $message->content,
        ]);
    }
}
