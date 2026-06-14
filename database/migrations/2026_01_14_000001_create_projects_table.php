<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── projects ───────────────────────────────────────────────────────
        // Project Workspace aggregate root. Multi-tenant via organization_id;
        // soft-deleted; membership in project_members; status flow tracked in
        // project_status_histories. (Blueprint DOMAIN 6.)
        Schema::create('projects', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('pemda_id', 26)->index();
            $table->char('team_id', 26)->nullable()->index();
            // Konten
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->string('status', 30)->default('draft'); // ProjectStatus enum
            // Waktu
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            // Anggaran
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('budget_spent', 15, 2)->default(0);
            // SDM
            $table->char('owner_id', 26)->index();
            $table->char('manager_id', 26)->nullable();
            // Progress
            $table->smallInteger('progress_percent')->default(0);
            // Metadata
            $table->json('tags')->nullable();
            $table->smallInteger('data_classification')->default(2);
            // Audit
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('pemda_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // ── project_members ────────────────────────────────────────────────
        Schema::create('project_members', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('project_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->string('role', 30)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();

            $table->unique(['project_id', 'user_id']);

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // ── project_milestones ─────────────────────────────────────────────
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('project_id', 26)->index();
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending'); // MilestoneStatus enum
            $table->date('due_date');
            $table->timestamp('completed_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        // ── project_risks ──────────────────────────────────────────────────
        Schema::create('project_risks', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('project_id', 26)->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('risk_level', 30)->default('medium'); // RiskLevel enum
            $table->smallInteger('probability')->nullable(); // 1-5
            $table->smallInteger('impact')->nullable();       // 1-5
            $table->text('mitigation')->nullable();
            $table->string('status', 30)->default('open');
            $table->char('owner_id', 26)->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });

        // ── project_status_histories ───────────────────────────────────────
        Schema::create('project_status_histories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('project_id', 26)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->char('changed_by', 26)->index();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // Back-fill the project_id foreign keys on tables that referenced
        // projects before this table existed (created in earlier passes with a
        // bare indexed column). SQLite cannot ALTER-add foreign keys to an
        // existing table, so we only do this on real RDBMS (Postgres prod);
        // tenant isolation is enforced by the global scope regardless.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            foreach (['tasks', 'meetings', 'documents', 'calendar_events', 'reports', 'chat_channels'] as $referencing) {
                if (Schema::hasTable($referencing) && Schema::hasColumn($referencing, 'project_id')) {
                    Schema::table($referencing, function (Blueprint $table) {
                        $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            foreach (['tasks', 'meetings', 'documents', 'calendar_events', 'reports', 'chat_channels'] as $referencing) {
                if (Schema::hasTable($referencing) && Schema::hasColumn($referencing, 'project_id')) {
                    Schema::table($referencing, function (Blueprint $table) {
                        $table->dropForeign(['project_id']);
                    });
                }
            }
        }

        Schema::dropIfExists('project_status_histories');
        Schema::dropIfExists('project_risks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
