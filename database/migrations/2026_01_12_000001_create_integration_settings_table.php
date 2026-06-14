<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── integration_settings ───────────────────────────────────────────
        // Per-organization (pemda/OPD-admin level) external integration config.
        // Non-secret values stored plain (queryable); secrets stored encrypted
        // at rest by the IntegrationSettingsService (Crypt::encryptString).
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->string('group', 40)->index();
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'group', 'key'], 'uniq_org_group_key');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
