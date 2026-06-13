<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26)->unique()->index();
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('push')->default(false);
            $table->boolean('task_assigned')->default(true);
            $table->boolean('task_due')->default(true);
            $table->boolean('meeting_invited')->default(true);
            $table->boolean('document_approval')->default(true);
            $table->boolean('report_status')->default(true);
            $table->string('digest_frequency', 20)->default('realtime');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
