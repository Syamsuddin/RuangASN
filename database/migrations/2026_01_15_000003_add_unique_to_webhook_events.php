<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Harden webhook idempotency:
     *   - body_hash (sha256 of the raw signed body) lets dedupe be bound to the
     *     SIGNED payload, not just the attacker-controlled event_id header.
     *   - A UNIQUE index on (provider, event_id, body_hash) makes the dedupe
     *     atomic: the first INSERT wins, a replay collides and is rejected by the
     *     DB. NULL event_id rows are distinct (multiple NULLs allowed on both
     *     Postgres and SQLite), so webhooks lacking an event_id never collide.
     */
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->char('body_hash', 64)->nullable()->after('event_id');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            // Replace the non-unique (provider, event_id) index with a unique
            // guard that also includes body_hash (event_id poisoning defence).
            $table->dropIndex(['provider', 'event_id']);
            $table->unique(['provider', 'event_id', 'body_hash'], 'webhook_events_provider_event_body_unique');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique('webhook_events_provider_event_body_unique');
            $table->index(['provider', 'event_id']);
            $table->dropColumn('body_hash');
        });
    }
};
