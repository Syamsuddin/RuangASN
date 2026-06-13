<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('nip', 18)->nullable()->unique();
            $table->string('nip_lama', 9)->nullable();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('user_type', 30)->default('pns');
            $table->string('status', 30)->default('active');
            $table->string('presence_status', 30)->default('offline');
            $table->string('workspace_mode', 30)->default('default');
            $table->string('avatar_path', 500)->nullable();
            $table->text('bio')->nullable();
            $table->string('password', 255);
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->smallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->text('national_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->char('gender', 1)->nullable();
            $table->char('organization_id', 26)->index();
            $table->char('pemda_id', 26)->index();
            $table->json('notification_settings')->nullable();
            $table->json('ai_preferences')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->string('locale', 10)->default('id');
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('pemda_id')->references('id')->on('organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
