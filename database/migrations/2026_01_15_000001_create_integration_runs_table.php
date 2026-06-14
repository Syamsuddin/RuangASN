<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── integration_runs ───────────────────────────────────────────────
        // Append-only observability ledger: ONE row per sync attempt AND per
        // inbound webhook delivery. payload_excerpt is a REDACTED preview only —
        // never raw secrets / full PII. No updated_at (rows are written once,
        // then finalized in place by the manager within the same request).
        Schema::create('integration_runs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->string('provider', 40)->index();
            $table->string('direction', 20)->default('outbound'); // outbound=we call them, inbound=webhook
            $table->string('operation', 80);                       // sync_asn, sync_surat, webhook_received, send_message…
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('items_processed')->default(0);
            $table->integer('items_failed')->default(0);
            $table->text('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->text('payload_excerpt')->nullable();           // REDACTED preview only
            $table->char('triggered_by', 26)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── webhook_events ─────────────────────────────────────────────────
        // Dedupe + audit of inbound machine-to-machine deliveries. organization_id
        // is nullable (the org may be resolved only after signature verification).
        // (provider, event_id) carries an app-level idempotency guarantee; we use
        // a normal index + a unique guard in code so SQLite (no partial unique)
        // behaves the same as Postgres.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable()->index();
            $table->string('provider', 40);
            $table->string('event_id', 120)->nullable();           // provider idempotency key
            $table->boolean('signature_valid')->default(false);
            $table->boolean('processed')->default(false);
            $table->json('headers')->nullable();                   // redacted
            $table->text('body_excerpt')->nullable();              // redacted
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['provider', 'event_id']);

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('integration_runs');
    }
};
