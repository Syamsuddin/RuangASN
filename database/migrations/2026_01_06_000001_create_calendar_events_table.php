<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->string('calendar_type', 30)->default('personal');
            $table->char('owner_id', 26)->nullable()->index();
            $table->char('team_id', 26)->nullable()->index();
            $table->char('project_id', 26)->nullable()->index();
            $table->char('meeting_id', 26)->nullable()->index();
            $table->char('task_id', 26)->nullable()->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('location', 500)->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->boolean('all_day')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->string('rrule', 500)->nullable();
            $table->char('recurring_parent_id', 26)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_public')->default(false);
            $table->char('created_by', 26)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('meeting_id')->references('id')->on('meetings')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['start_at', 'end_at']);
        });

        // Self-referential FK added after table creation
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreign('recurring_parent_id')->references('id')->on('calendar_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign(['recurring_parent_id']);
        });
        Schema::dropIfExists('calendar_events');
    }
};
