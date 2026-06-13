<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('pemda_id', 26)->index();
            $table->char('meeting_id', 26)->nullable()->index();
            $table->char('project_id', 26)->nullable()->index();
            $table->char('task_id', 26)->nullable()->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('document_type', 30)->default('letter');
            $table->string('status', 30)->default('draft');
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('page_count')->nullable();
            $table->integer('version_number')->default(1);
            $table->char('parent_document_id', 26)->nullable()->index();
            $table->boolean('is_latest')->default(true);
            $table->text('ocr_text')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('ai_tags')->nullable();
            $table->string('ai_category', 100)->nullable();
            $table->string('document_number', 100)->nullable();
            $table->date('document_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('tags')->nullable();
            $table->smallInteger('data_classification')->default(2);
            $table->char('owner_id', 26)->index();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('pemda_id')->references('id')->on('organizations');
            $table->foreign('meeting_id')->references('id')->on('meetings')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Self-referential FK added after table creation
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('parent_document_id')->references('id')->on('documents')->nullOnDelete();
        });

        Schema::create('document_approvals', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('document_id', 26)->index();
            $table->char('approver_id', 26)->index();
            $table->smallInteger('step_number')->default(1);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('approver_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['parent_document_id']);
        });
        Schema::dropIfExists('documents');
    }
};
