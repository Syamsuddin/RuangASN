<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── ai_conversations ───────────────────────────────────────────────
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->string('agent_type', 30)->default('general');
            $table->string('title', 500)->nullable();
            $table->string('context_type', 50)->nullable();
            $table->char('context_id', 26)->nullable();
            $table->integer('total_tokens')->default(0);
            $table->string('model_provider', 30)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('user_id')->references('id')->on('users');
        });

        // ── ai_messages ────────────────────────────────────────────────────
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('conversation_id', 26)->index();
            $table->string('role', 20);
            $table->text('content');
            $table->integer('tokens_used')->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('finish_reason', 50)->nullable();
            $table->json('citations')->nullable();
            $table->smallInteger('data_classification')->default(3);
            $table->json('proposed_actions')->nullable();
            $table->boolean('action_confirmed')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->char('confirmed_by', 26)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── ai_memories ────────────────────────────────────────────────────
        Schema::create('ai_memories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('user_id', 26)->nullable();
            $table->string('memory_type', 30);
            $table->string('scope', 30);
            $table->text('content');
            $table->string('embedding_id', 100)->nullable();
            $table->string('source_type', 50)->nullable();
            $table->char('source_id', 26)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // ── ai_interaction_logs (AXIOM-04 & AXIOM-06: setiap aksi AI tercatat)
        Schema::create('ai_interaction_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('user_id', 26);
            $table->char('conversation_id', 26)->nullable();
            $table->char('message_id', 26)->nullable();
            $table->string('agent_type', 30);
            $table->string('interaction', 30);
            $table->string('intent', 100)->nullable();
            $table->string('model_provider', 30)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('status', 30)->default('completed');
            $table->text('error_message')->nullable();
            $table->json('proposed_action')->nullable();
            $table->string('actor_ip', 45)->nullable();
            $table->smallInteger('data_classification')->default(3);
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'created_at'], 'idx_ail_org');
            $table->index(['user_id', 'created_at'], 'idx_ail_user');
            $table->index('conversation_id', 'idx_ail_conversation');

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->nullOnDelete();
            $table->foreign('message_id')->references('id')->on('ai_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interaction_logs');
        Schema::dropIfExists('ai_memories');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
