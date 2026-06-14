<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Detect tasks with passed due_date and notify assignees (once per task per day)
Schedule::command('tasks:detect-overdue')->dailyAt('07:00');

// Generate next occurrences for recurring tasks
Schedule::command('tasks:generate-recurring')->dailyAt('00:30');

// Aggregate yesterday's KPI snapshot per organization for the Executive dashboard
Schedule::command('analytics:snapshot')->dailyAt('01:00');

// External integration syncs (staggered; harmless stub runs unless INTEGRATIONS_LIVE)
Schedule::command('integrations:sync siasn')->dailyAt('02:00');
Schedule::command('integrations:sync srikandi')->dailyAt('02:15');
Schedule::command('integrations:sync sipd')->dailyAt('02:30');
