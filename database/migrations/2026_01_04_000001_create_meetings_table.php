<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('pemda_id', 26)->index();
            $table->char('project_id', 26)->nullable()->index();
            $table->char('team_id', 26)->nullable()->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('meeting_type', 30)->default('internal');
            $table->string('meeting_mode', 30)->default('offline');
            $table->string('status', 30)->default('draft');
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('online_url', 2000)->nullable();
            $table->char('host_id', 26)->index();
            $table->char('secretary_id', 26)->nullable();
            $table->text('agenda_notes')->nullable();
            $table->json('pre_read_docs')->nullable()->default('[]');
            $table->string('recording_path', 500)->nullable();
            $table->string('transcript_path', 500)->nullable();
            $table->smallInteger('data_classification')->default(2);
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('pemda_id')->references('id')->on('organizations');
            $table->foreign('host_id')->references('id')->on('users');
            $table->foreign('secretary_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meeting_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->string('role', 30)->default('participant');
            $table->string('attendance_status', 30)->default('invited');
            $table->timestamp('response_at')->nullable();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meeting_id', 26)->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->char('presenter_id', 26)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->foreign('presenter_id')->references('id')->on('users');
        });

        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meeting_id', 26)->index();
            $table->char('agenda_item_id', 26)->nullable();
            $table->text('content');
            $table->char('recorded_by', 26);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->foreign('agenda_item_id')->references('id')->on('meeting_agenda_items');
            $table->foreign('recorded_by')->references('id')->on('users');
        });

        Schema::create('meeting_action_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meeting_id', 26)->index();
            $table->char('decision_id', 26)->nullable();
            $table->char('task_id', 26)->nullable()->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->char('assignee_id', 26)->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_task_created')->default(false);
            $table->char('created_by', 26);
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->foreign('decision_id')->references('id')->on('meeting_decisions');
            $table->foreign('task_id')->references('id')->on('tasks');
            $table->foreign('assignee_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meeting_id', 26)->unique();
            $table->text('content')->nullable();
            $table->text('ai_draft')->nullable();
            $table->string('status', 30)->default('draft');
            $table->smallInteger('data_classification')->default(3);
            $table->char('approved_by', 26)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->char('created_by', 26);
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes');
        Schema::dropIfExists('meeting_action_items');
        Schema::dropIfExists('meeting_decisions');
        Schema::dropIfExists('meeting_agenda_items');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
