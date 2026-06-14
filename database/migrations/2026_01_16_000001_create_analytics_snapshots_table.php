<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── analytics_snapshots ─────────────────────────────────────────────
        // ONE row per organization per day per scope: the daily aggregated KPI
        // bundle (tasks/meetings/documents/reports/projects/skp/users/…) computed
        // by AnalyticsService::computeSnapshot(). metrics is a denormalized JSON
        // bundle — these rows are an append/upsert ANALYTICS ledger, not domain
        // state, so they carry no soft-delete and no version. The unique key makes
        // snapshot() idempotent (updateOrCreate) per org+date+scope.
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->date('snapshot_date')->index();
            $table->string('scope', 20)->default('organization');
            $table->json('metrics');
            $table->timestamp('created_at')->nullable();

            $table->unique(['organization_id', 'snapshot_date', 'scope']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
