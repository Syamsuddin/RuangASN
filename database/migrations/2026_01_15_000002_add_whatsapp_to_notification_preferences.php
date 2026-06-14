<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            // Opt-in WhatsApp delivery channel — off by default (PII over a 3rd
            // party). Honoured by NotificationService alongside email/in_app.
            $table->boolean('whatsapp')->default(false)->after('push');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
    }
};
