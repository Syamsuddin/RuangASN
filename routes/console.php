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
