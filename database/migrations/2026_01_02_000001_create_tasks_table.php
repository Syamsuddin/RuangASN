<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('pemda_id', 26)->index();
            $table->char('parent_task_id', 26)->nullable()->index();
            $table->char('meeting_id', 26)->nullable()->index();
            $table->char('project_id', 26)->nullable()->index();
            $table->char('skp_indicator_id', 26)->nullable()->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('task_type', 30)->default('personal');
            $table->string('status', 30)->default('draft');
            $table->string('priority', 30)->default('medium');
            $table->char('creator_id', 26)->index();
            $table->char('assignee_id', 26)->nullable()->index();
            $table->char('reviewer_id', 26)->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_pattern')->nullable();
            $table->char('recurring_parent_id', 26)->nullable();
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->decimal('actual_hours', 5, 2)->nullable();
            $table->json('tags')->nullable();
            $table->smallInteger('data_classification')->default(2);
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('pemda_id')->references('id')->on('organizations');
            $table->foreign('creator_id')->references('id')->on('users');
            $table->foreign('assignee_id')->references('id')->on('users');
            $table->foreign('reviewer_id')->references('id')->on('users');
        });

        Schema::create('task_status_histories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('task_id', 26)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->char('changed_by', 26)->index();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users');
        });

        Schema::create('task_evidences', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('task_id', 26)->index();
            $table->char('uploader_id', 26)->index();
            $table->string('evidence_type', 30);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('url', 2000)->nullable();
            $table->smallInteger('data_classification')->default(3);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('uploader_id')->references('id')->on('users');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('task_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->text('content');
            $table->char('parent_id', 26)->nullable()->index();
            $table->json('mentions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('task_checklists', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('task_id', 26)->index();
            $table->string('title', 500);
            $table->boolean('is_done')->default(false);
            $table->char('done_by', 26)->nullable();
            $table->timestamp('done_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_evidences');
        Schema::dropIfExists('task_status_histories');
        Schema::dropIfExists('tasks');
    }
};
