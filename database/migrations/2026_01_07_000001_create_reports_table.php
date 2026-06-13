<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('pemda_id', 26)->index();
            $table->char('project_id', 26)->nullable()->index();
            $table->char('team_id', 26)->nullable()->index();
            $table->string('title', 500);
            $table->text('content')->nullable();
            $table->text('ai_draft')->nullable();
            $table->string('report_type', 30)->default('activity');
            $table->string('period_type', 30)->default('monthly');
            $table->string('status', 30)->default('draft');
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->json('data_sources')->nullable()->default('[]');
            $table->smallInteger('data_classification')->default(2);
            $table->char('author_id', 26)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->char('approved_by', 26)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('pemda_id')->references('id')->on('organizations');
            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('report_status_histories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('report_id', 26)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->char('changed_by', 26)->index();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_status_histories');
        Schema::dropIfExists('reports');
    }
};
