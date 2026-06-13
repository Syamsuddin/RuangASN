<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('recipient_id', 26)->index();
            $table->string('notification_type', 50);
            $table->string('title', 255);
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('channel', 30)->default('in_app');
            $table->string('status', 30)->default('pending');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->smallInteger('retry_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'status']);
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('recipient_id')->references('id')->on('users');
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('event_type', 100)->index();
            $table->string('aggregate_type', 100)->nullable()->index();
            $table->char('aggregate_id', 26)->nullable()->index();
            $table->json('payload');
            $table->char('organization_id', 26)->nullable()->index();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->text('fail_reason')->nullable();
            $table->smallInteger('retry_count')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable()->index();
            $table->char('user_id', 26)->nullable()->index();
            $table->string('action', 50)->index();
            $table->string('auditable_type', 100)->nullable()->index();
            $table->char('auditable_id', 26)->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('app_notifications');
    }
};
