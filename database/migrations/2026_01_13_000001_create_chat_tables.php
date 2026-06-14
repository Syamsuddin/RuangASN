<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── chat_channels ──────────────────────────────────────────────────
        // DM + group/team/project/meeting/announcement channels. Multi-tenant
        // via organization_id; soft-deleted; membership lives in
        // chat_channel_members.
        Schema::create('chat_channels', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->string('channel_type', 30);
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->char('team_id', 26)->nullable()->index();
            $table->char('project_id', 26)->nullable()->index(); // NO FK — projects table built in a later pass
            $table->char('meeting_id', 26)->nullable()->index();
            $table->char('created_by', 26)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('meeting_id')->references('id')->on('meetings')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // ── chat_channel_members ───────────────────────────────────────────
        // Blueprint shows a composite PK; we keep a ULID id for Eloquent
        // friendliness and enforce the (channel_id, user_id) UNIQUE constraint.
        Schema::create('chat_channel_members', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('channel_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->string('role', 30)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();

            $table->unique(['channel_id', 'user_id']);

            $table->foreign('channel_id')->references('id')->on('chat_channels')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // ── chat_messages ──────────────────────────────────────────────────
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('channel_id', 26)->index();
            $table->char('sender_id', 26)->index();
            $table->char('parent_id', 26)->nullable(); // self-ref FK added below (thread)
            $table->text('content');
            $table->string('content_type', 30)->default('text');
            $table->json('attachments')->nullable();
            $table->json('mentions')->nullable();
            $table->json('reactions')->nullable();
            $table->smallInteger('data_classification')->default(3);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel_id', 'created_at']);

            $table->foreign('channel_id')->references('id')->on('chat_channels')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Self-referencing thread parent (second Schema::table call so the
        // table exists before the FK references itself).
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('chat_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_channel_members');
        Schema::dropIfExists('chat_channels');
    }
};
