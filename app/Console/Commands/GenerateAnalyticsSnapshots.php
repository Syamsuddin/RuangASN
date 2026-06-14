<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\AnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generate (upsert) the daily analytics snapshot for EVERY organization.
 *
 * Defaults to yesterday (so a 01:00 run captures the full previous day);
 * --date=YYYY-MM-DD overrides. Idempotent: re-running the same date updates the
 * existing snapshot rows in place rather than duplicating them. Iterates orgs
 * without the auth global scope (system context) and pins each compute to that
 * org's id inside the service, so no cross-tenant leakage.
 */
class GenerateAnalyticsSnapshots extends Command
{
    protected $signature = 'analytics:snapshot {--date= : Snapshot date (YYYY-MM-DD); defaults to yesterday}';

    protected $description = 'Compute and persist the daily analytics KPI snapshot for every organization.';

    public function handle(AnalyticsService $analytics): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : Carbon::yesterday();

        $count = 0;

        Organization::withoutGlobalScopes()
            ->orderBy('id')
            ->chunk(100, function ($orgs) use ($analytics, $date, &$count) {
                foreach ($orgs as $org) {
                    $analytics->snapshot($org, $date);
                    $count++;
                }
            });

        $this->info("Generated {$count} analytics snapshot(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
