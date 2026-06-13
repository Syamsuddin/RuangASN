<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── skp_periods ────────────────────────────────────────────────────
        Schema::create('skp_periods', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->smallInteger('year');
            $table->smallInteger('semester')->nullable();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations');
        });

        // ── skp_plans ──────────────────────────────────────────────────────
        Schema::create('skp_plans', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->char('period_id', 26)->index();
            $table->char('superior_id', 26)->nullable();
            $table->string('status', 30)->default('planning');
            $table->string('document_path', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->char('approved_by', 26)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('period_id')->references('id')->on('skp_periods');
            $table->foreign('superior_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['user_id', 'period_id']);
        });

        // ── skp_indicators ─────────────────────────────────────────────────
        Schema::create('skp_indicators', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('skp_plan_id', 26)->index();
            $table->char('parent_indicator_id', 26)->nullable()->index();
            $table->string('perspective', 30);
            $table->string('name', 500);
            $table->decimal('target_value', 12, 4);
            $table->string('target_unit', 100);
            $table->decimal('weight', 5, 2)->default(100);
            $table->decimal('realization_value', 12, 4)->nullable();
            $table->decimal('achievement_pct', 6, 2)->nullable();
            $table->text('superior_expectation')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('skp_plan_id')->references('id')->on('skp_plans')->cascadeOnDelete();
        });

        // Add self-referential FK after table + primary key is fully established
        Schema::table('skp_indicators', function (Blueprint $table) {
            $table->foreign('parent_indicator_id')->references('id')->on('skp_indicators')->nullOnDelete();
        });

        // ── skp_realizations ───────────────────────────────────────────────
        Schema::create('skp_realizations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('indicator_id', 26)->index();
            $table->char('user_id', 26);
            $table->decimal('realization_value', 12, 4);
            $table->date('realization_date');
            $table->text('description')->nullable();
            $table->char('task_id', 26)->nullable();
            $table->char('document_id', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();

            $table->foreign('indicator_id')->references('id')->on('skp_indicators')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── skp_evaluations ────────────────────────────────────────────────
        Schema::create('skp_evaluations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('skp_plan_id', 26)->unique();
            $table->decimal('performance_score', 6, 2)->nullable();
            $table->decimal('behavior_score', 6, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->string('predicate', 30)->nullable();
            // Behavior dimensions are scored on a 0-120 scale (PermenPANRB 6/2022).
            // decimal(6,2) — NOT (4,2) — so scores ≥100 do not overflow NUMERIC on PostgreSQL.
            $table->decimal('behavior_service', 6, 2)->nullable();
            $table->decimal('behavior_commit', 6, 2)->nullable();
            $table->decimal('behavior_initiative', 6, 2)->nullable();
            $table->decimal('behavior_teamwork', 6, 2)->nullable();
            $table->decimal('behavior_leadership', 6, 2)->nullable();
            $table->text('superior_feedback')->nullable();
            $table->char('evaluated_by', 26)->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('skp_plan_id')->references('id')->on('skp_plans');
            $table->foreign('evaluated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skp_evaluations');
        Schema::dropIfExists('skp_realizations');
        Schema::dropIfExists('skp_indicators');
        Schema::dropIfExists('skp_plans');
        Schema::dropIfExists('skp_periods');
    }
};
